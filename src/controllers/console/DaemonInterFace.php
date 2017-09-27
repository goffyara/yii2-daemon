<?php
namespace goffyara\daemon\controllers\console;

interface DaemonInterFace
{
    public function actionStart();

    public function actionJob();

    public function actionStop();

    public function actionRestart();

    public function actionStatus();
}
