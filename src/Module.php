<?php
namespace goffyara\daemon;

use Yii;

class Module extends \yii\base\Module
{
    /**
     * пусть где будут храниться пид файлы
     * @var string
     */
    public $path = '@runtime/daemons';
    /**
     * масссив ролей, у которых будет доступ
     * к web интерфейсу
     * (не используется)
     * @var array
     */
    public $webRoles = [];

    public function init()
    {
        parent::init();
        $this->controllerNamespace = 'app\modules\daemon\controllers\console';
        $this->path = Yii::getAlias($this->path);
    }
}