<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/WebSocketServer.php';

use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IoServer;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new WebSocketServer()
        )
    ),
    8080
);

$server->run();
?>
