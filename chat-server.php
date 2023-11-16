<?php

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require 'vendor/autoload.php';

class ChatServer implements MessageComponentInterface
{
    protected $clients;
    protected $admin;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        $this->admin = null;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);

        // Check if the connection is from the admin
        $queryParams = $conn->httpRequest->getUri()->getQuery();
        if (strpos($queryParams, 'admin=true') !== false) {
            $this->admin = $conn;
            $this->admin->send("You are now connected as the admin.");
        } else {
            $conn->send("Welcome to the chat.");
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        if ($this->admin === $conn) {
            $this->admin = null;
            $this->broadcast("The admin has left the chat.");
        } else {
            $this->broadcast("A user has left the chat.");
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // Broadcast the message to all clients and the admin
        foreach ($this->clients as $client) {
            $client->send($msg);
        }

        if ($this->admin !== null) {
            $this->admin->send($msg);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function broadcast($message)
    {
        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    8080
);

$server->run();
