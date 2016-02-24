<?php

define('IN_BUFFER_SIZE', 2048);
define('OUT_BUFFER_SIZE', 2048);

class Connection {
    const STATE_READ  = 0;
    const STATE_WRITE = 1;
    const STATE_CLOSE = 2;

    public function __construct($socket) {
        $this->socket = $socket;
        $this->inbuf = '';
        $this->outbuf = '';
        $this->state = self::STATE_READ;
    }

    public function read() {
        print("On read\n");
        $received = socket_read($this->socket, IN_BUFFER_SIZE); 

        if ($received === false) {
            print("Error happens while read\n");//TODO: disconnect
            return false;
        }

        if ($received === "") {
            return false;
        }

        $this->inbuf .= $received;
        return true;
    }

    public function write() {
        print("On write\n");
        $writed = socket_write($this->socket, $this->outbuf, OUT_BUFFER_SIZE);
        if ($writed === false) {
            return false;
        }
        $this->outbuf = substr($this->outbuf, $writed);
        if (strlen($this->outbuf) == 0) {
            $this->state = self::STATE_CLOSE;
        }

        return true;

    }

    public function parse() {
        if (strpos($this->inbuf, "\r\n\r\n") !== false ) {
            $req = explode("\r\n\r\n", $this->inbuf)[0];
            print("New http request\n");
            $content = "{\"status\": \"OK\"}\n"; 
            $resp = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n"
                ."Content-Length: " . strlen($content) . "\r\n\r\n" . $content;
            $this->outbuf .=  $resp;
            $this->inbuf = '';
            $this->state = self::STATE_WRITE;
        }
    }

    public function disconnect() {
        print("On disconnect\n");
    }
}
