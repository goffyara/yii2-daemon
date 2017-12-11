<?php
namespace app\commands;

use Yii;
use goffyara\daemon\controllers\console\DaemonController;

class CreateFileController extends DaemonController
{
    private $count = 1;
    public $sleep = 2;

    public function actionJob()
    {
        touch(Yii::getAlias('@runtime/' . $this->count . '.txt'));
        $this->count++;
    }
}