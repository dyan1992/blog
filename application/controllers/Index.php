<?php
/**
 * Created by PhpStorm.
 * User: dy
 * Date: 2018/12/28
 * Time: 15:13
 */
class IndexController extends \Yaf\Controller_Abstract
{
    public function indexAction(){
        Common_Amqp_Receive::getInstance()->receive();
        //$this->display('index');
    }

    public function listAction(){
        (new Common_Amqp_Send())->send('hello world');
    }
}