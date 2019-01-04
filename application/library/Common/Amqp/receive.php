<?php
/**
 * Created by PhpStorm.
 * User: dy
 * Date: 2019/1/2
 * Time: 17:48
 */

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Yaf\Registry;

class Common_Amqp_Receive
{

    private static $instance;

    private $connection;
    private $channel;


    public function __construct()
    {
        $options = Registry::get('config')['amqp'];
        $args = [$options['host'],$options['port'],$options['user'],$options['password']];
        $this->connection = new AMQPStreamConnection(...$args);
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare('hello',false,false,false,false);
        echo " [*] Waiting for messages. To exit press CTRL+C\n";
    }

    /**
     * @return self
     */
    public static function getInstance()
    {
        if(!self::$instance instanceof self){
            return new self();
        }
        return self::$instance;
    }



    public function receive(){
        $callback = function($msg){
            echo "[x] received ".$msg->body.PHP_EOL;
        };
        $this->channel->basic_consume('hello','',false,true,false,false ,$callback);
        while(count($this->channel->callbacks)){
            $this->channel->wait();
        }
    }
}