<?php
/**
 * Created by PhpStorm.
 * User: dy
 * Date: 2019/1/2
 * Time: 17:48
 */
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Common_Amqp_Send
{

    protected $channel;
    protected $connection;
    public function __construct()
    {

        $this->connection = new AMQPStreamConnection('127.0.0.1',5672,'guest','guest');
        $this->channel    = $this->connection->channel();
        $this->channel->queue_declare('hello',false,false,false,false);

    }


    public function send($params){
        $msg =  new AMQPMessage($params);
        $this->channel->basic_publish($msg,'','hello');
        echo "[x] sent hello world \n";
        $this->channel->close();
        $this->connection->close();
    }
}