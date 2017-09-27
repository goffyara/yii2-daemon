<?php

namespace goffyara\daemon\controllers\console;

use yii;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\helpers\Console;
use yii\helpers\VarDumper;
use app\models\Gamblers;


/**
 * Создает дочерние процессы из контроллеров,
 * алиасы которых указаны в $daemons
 */
class ParentController extends DaemonController
{
    public $_gpid;
    public $daemons = [];
    public $jobs = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $daemons = [];
        foreach ($this->daemons as $daemonAlias) {
            //если наследуется от DaemonController, добавить в список
            $className = $this->getControllerClassName($daemonAlias);
            $daemonClassName = 'app\modules\daemon\controllers\console\DaemonController';
            if (class_exists($className) && is_subclass_of($className, $daemonClassName)) {
                array_push($daemons, $daemonAlias);
            }
        }

        $this->daemons = $daemons;
    }

    /**
     * @inheritdoc
     * + обработка сигнала SIGCHLD и убийство детей
     */
    protected function signalHandler($signal, $pid = null, $status = null)
    {
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
                // kill all children
                foreach ($this->jobs as $pid) {
                    posix_kill($pid, SIGINT);
                }
                break;
            case SIGCHLD:
                //if children stop, not start more
                Yii::trace('получен сигнал SIGCHLD в' . $this->id . PHP_EOL);
                if (!$pid) {
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                while ($pid > 0) {
                    //алиас демона по пиду
                    $daemonAlias = array_search($pid, $this->jobs);
                    //поиск в массиве демонов по алиасу
                    $key = array_search($daemonAlias, $this->daemons);
                    if ($key !== false) {
                        //убрать из списка запускаемых
                        unset($this->daemons[$key]);
                        //удалить пид файл процесса
                        $this->proccessUnregister($daemonAlias);
                        Yii::trace('дочерний процесс (SIGCHLD) --> ' . $daemonAlias . PHP_EOL);
                    }
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }

                break;
        }
        parent::signalHandler($signal, $pid = null, $status = null);
    }

    public function actionJob()
    {
        // stop, if no working daemons
        if (empty($this->daemons)) {
            $this->actionStop();
        }

        $this->_gpid = posix_getpgrp();

        foreach ($this->daemons as $daemon) {
        Yii::trace('Обработка worker ' . $daemon . PHP_EOL);

            if (key_exists($daemon, $this->jobs)) {
                continue;
            }

            $pid = pcntl_fork();

            if ($pid == -1) {
                Yii::error('Не удалось породить дочерний процесс ' . $daemon . PHP_EOL);
                return static::EXIT_CODE_ERROR;
            } else if ($pid) {
                //Код Родительского процесса
                $this->jobs[$daemon] = $pid;
            } else {
                // Код дочернего процесса
                $Daemon = Yii::$app->createControllerByID($daemon);
                $Daemon->actionStart();
                //завершить дочерний процесс, не выполняя дальнейшее
                exit();
                return static::EXIT_CODE_NORMAL;
            }
        }
    }

    /**
     * Имя класса контроллера по алиасу
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    private function getControllerClassName($id)
    {
        $pos = strrpos($id, '/');
        if ($pos === false) {
            $prefix = '';
            $className = $id;
        } else {
            $prefix = substr($id, 0, $pos + 1);
            $className = substr($id, $pos + 1);
        }

        if (!preg_match('%^[a-z][a-z0-9\\-_]*$%', $className)) {
            return null;
        }
        if ($prefix !== '' && !preg_match('%^[a-z0-9_/]+$%i', $prefix)) {
            return null;
        }

        $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $className))) . 'Controller';
        $className = ltrim($this->module->controllerNamespace . '\\' . str_replace('/', '\\', $prefix)  . $className, '\\');

        return $className;
    }
}
