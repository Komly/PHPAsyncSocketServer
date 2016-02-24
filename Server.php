<?php

require 'Connection.php';

class Server {
    protected $socket = null;
    protected $connections = [];

    public function __construct($host, $port) {
        $this->createSocket($host, $port);
        $this->acceptLoop();
    }

    public function acceptLoop() {
        while(true) {
            print "Poll...\n";
            list($r, $w, $x) = [ [$this->socket], [], [] ];
            foreach($this->connections as $s => $conn) {
                if ($conn->state == Connection::STATE_READ) {
                    array_push($r, $conn->socket);
                } else if ($conn->state == Connection::STATE_WRITE) {
                    array_push($w, $conn->socket);
                } else {
                    $this->disconnectClient($s);
                    socket_close($conn->socket);
                }
            }
            $n = socket_select($r, $w, $x, 1);
            if ($n == 0) {
                continue;
            }
            foreach($r as $s) {
                $s = (int)$s;
                if($s == (int)$this->socket) {
                    $this->acceptClient();
                } else {
                    if ($this->connections[$s]->read()) {
                        $this->connections[$s]->parse();
                    } else {
                        $this->disconnectClient($s);
                    }
                }
            }
            foreach($w as $s) {
                $s = (int)$s;
                if (!$this->connections[$s]->write()) {
                    $this->disconnectClient($s);
                }
            }
        }
    }

    public function disconnectClient($socket) {
        $this->connections[$socket]->disconnect();
        unset($this->connections[$socket]);
    }

    public function acceptClient() {
        $client_sock = socket_accept($this->socket);
        if ($client_sock == false) {
            return;
        }
        socket_set_nonblock($client_sock);

        $conn = new Connection($client_sock);
        $this->connections[(int)$client_sock] = $conn;
            
    }

    public function createSocket($host, $port) {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, 0);
        if ($this->socket === false) {
            $error = socket_strerror(socket_last_error($this->socket));
            print("Error happens: $error\n");
            return false;
        }

        if (socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1) == false) {
            $error = socket_strerror(socket_last_error($this->socket));
            print("Error happens: $error\n");
            return false;
        }

        if (socket_bind($this->socket, $host, $port) === false) {
            $error = socket_strerror(socket_last_error($this->socket));
            print("Error happens: $error\n");
            return false;
        }
        if (socket_listen($this->socket, SOMAXCONN) === false) {
            $error = socket_strerror(socket_last_error($this->socket));
            print("Error happens: $error\n");
            return false;
        }

        return true;
    }
};
