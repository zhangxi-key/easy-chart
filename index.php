<?php
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

#在线人数
$global_uid = 0;

#在线人数列表
$onlineUserList = ['type'=>'userList','list'=>[]];

$http_worker = new Worker('websocket://0.0.0.0:2000');

$http_worker->onConnect = function ($connection){
    global  $global_uid;
    #客户端连接时，在线人数加一，并且给当前连接分配一个id
    $connection->uid =  ++$global_uid;
};

$http_worker->onMessage = function ($connection,$message){
    global $http_worker,$onlineUserList,$global_uid;
    #接收的数据转数组
    $message = json_decode($message,true);
    #根据类型执行不同任务
    switch ($message["type"]){
        #客户端登录
        case "login":
            #将客户端登录数据追加到在线人数列表数据中
            $onlineUserList['list'][] = ['uid'=>$global_uid,'name'=>$message['name']];
            #广播给所有客户端
            foreach ($http_worker->connections as $conn)
            {
                $conn->send(json_encode($onlineUserList));
            }
            break;
        #群聊
        case "send":
            $send_message = [
                'type'=>'msg',
                'name'=>$message['name'],
                'content'=>$message['content'],
                'time' =>date('H:i:s',time())
            ];
            foreach ($http_worker->connections as $conn)
            {
                $conn->send(json_encode($send_message));
            }
            break;
            # ...
        default:
            break;
    }
};

$http_worker->onClose = function($connection){
    global $http_worker,$onlineUserList;
    #循环在线人数列表，客户端断开时，根据客户端断开id移除在线人数列表中的断开的客户端数据
    foreach ($onlineUserList['list'] as $key => &$value)
    {
        if($value['uid'] == $connection->uid)
        {
            unset($onlineUserList['list'][$key]);
        }
    }
    #广播给所有客户端
    foreach ($http_worker->connections as $conn){
        $conn->send(json_encode(array_merge($onlineUserList)));
    }
};

Worker::runAll();

