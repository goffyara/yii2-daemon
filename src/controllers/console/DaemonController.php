<?php

namespace goffyara\daemon\controllers\console;

use yii;
use yii\console\Controller;
use yii\helpers\Console;
use yii\helpers\FileHelper;

abstract class DaemonController extends Controller implements DaemonInterFace
{
    public $defaultAction = 'start';
    public $sleep = 5;
    public $noCheck = false;
    public $_pid;
    private $_gpid;
    protected $_stop = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        declare(ticks=1);

        $this->_pid = getmypid();

        pcntl_signal(SIGTERM, [$this, 'signalHandler']);
        pcntl_signal(SIGINT,[$this, 'signalHandler']);
        pcntl_signal(SIGCHLD, [$this, 'signalHandler']);
    }

    /**
     * Обработка сигналов
     * @param  [type] $signal  [description]
     * @param  [type] $pid    [description]
     * @param  [type] $status [description]
     * @return [type]         [description]
     */
    protected function signalHandler($signal, $pid = null, $status = null)
    {
        switch ($signal) {
            case SIGTERM:
                $this->stdout('получен сигнал SIGTERM процессом ' . $this->id . '(' . getmypid() . ')' . PHP_EOL, Console::FG_YELLOW);
            case SIGINT:
                $this->stdout('получен сигнал SIGINT процессом ' . $this->id  . '(' . getmypid() . ')' . PHP_EOL, Console::FG_YELLOW);
                $this->_stop = true;
                break;
        }
    }

    public function actionStart()
    {
        $this->stdout('Start daemon ' . $this->id . PHP_EOL, Console::FG_GREEN);
        //get pid from file
        $pid = $this->getPid();

        #если уже запущен, ничего не делать
        if ($this->checkProcess($pid)) {
            $this->stdout('Процесс уже запущен' . PHP_EOL, Console::FG_YELLOW);
            return static::EXIT_CODE_NORMAL;
        }

        $this->proccessRegister($this->_pid);

        while (!$this->_stop) {
            $this->actionJob();
            //вывод логов не дожидаясь окончания работы
            Yii::$app->log->logger->flush(true);
            sleep($this->sleep);
        }

        $this->proccessUnregister($this->id);
        $this->stdout('демон корректно остановлен ' .$this->id . PHP_EOL, Console::FG_GREEN);
        return static::EXIT_CODE_NORMAL;
    }


    public function actionJob()
    {
        // daemon Job code
    }

    /**
     * Остановка
     * @return
     */
    public function actionStop()
    {
        $pid = $this->getPid();
        if (!$this->checkProcess($pid)) {
            $this->stdout('Процесс уже остановлен ' . $this->id  . PHP_EOL, Console::FG_YELLOW);
            return static::EXIT_CODE_NORMAL;
        }

        if (posix_kill($pid, SIGINT)) {
            $this->stdout('Процесс успешно остановлен' . PHP_EOL, Console::FG_GREEN);
            return static::EXIT_CODE_NORMAL;
        }

        $this->stderr('Ошибка при остановке' . PHP_EOL, Console::FG_RED);
        return static::EXIT_CODE_ERROR;
    }

    public function actionRestart()
    {
        // daemon Restart code
    }

    public function actionStatus()
    {
        // daemon Status code
    }

    private function proccessRegister($pid)
    {
        $this->pidToFile($pid);
    }

    protected function proccessUnregister($alias)
    {
        $this->deletePidFile($alias);
    }

    public function hasStopSignal()
    {
        pcntl_signal_dispatch();
        if ($this->_stop) {
            return true;
        }

        return false;
    }

    private function getPid()
    {
        if (!file_exists($this->getPidFilePath($this->id))) {
            return false;
        }
        //пид процесса
        $pid = file_get_contents($this->getPidFilePath($this->id));
        return $pid;
    }

    /**
     * Если удалось получить пид процесса и
     * проверить то, что он запущен
     * @return boolean
     */
    private function checkProcess($pid)
    {
        if ($this->noCheck) {
            return false;
        }

        //Если файла нет, то и процесса нет
        if (empty($pid)) {
            return false;
        }
        // жив ли процесс с пидом из файла
        $procIsLive = posix_kill($pid, 0);

        if ($procIsLive) {
            return true;
        }

        return false;
    }

    private function pidToFile($pid)
    {
        $path = Yii::$app->getModule('daemon')->path;
        FileHelper::createDirectory($path);
        $pidFile = "$path/{$this->id}.pid";

        file_put_contents($pidFile, $pid);
    }

    private function deletePidFile($alias = null)
    {
        if (empty($alias)) {
            $alias = $this->id;
        }
        $pidFile = $this->getPidFilePath($alias);
        if (file_exists($pidFile)) {
            $this->stdout('Удаление файла ' . $pidFile . PHP_EOL);
            unlink($pidFile);
        } else {
            $this->stdout('Ужe удален файл ' . $pidFile . PHP_EOL);
        }
    }

    private function existsPidFile()
    {
        $file = $this->getPidFilePath($this->id);
        return file_exists($file);
    }

    private function getPidFilePath($alias)
    {
        return Yii::$app->getModule('daemon')->path . '/' . $alias .'.pid';
    }
}
