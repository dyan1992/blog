<?php

use Yaf\Application;
use Yaf\Registry;

class Bootstrap extends \Yaf\Bootstrap_Abstract
{
    private $config;

    public function _initConfig(){
        $this->config = Application::app()->getConfig();
        Registry::set('config',$this->config);
    }

    public function _initError(){
        if (strtolower(ini_get('yaf_environ')) === 'develop') {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
        }
    }
}
