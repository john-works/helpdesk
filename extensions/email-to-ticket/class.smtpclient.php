<?php

class SMTPClient
{
    private $socket;
    private $connected = false;

    public function connect($host, $port = 465, $ssl = true)
    {
        $protocol = $ssl ? "tls://" : "";
        $errno = 0;
        $errstr = "";

        $this->socket = @stream_socket_client(
            $protocol . $host . ":" . $port,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT
        );

        if (!$this->socket) {
            throw new \Exception("SMTP connect failed: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 30);
        $this->readResponse();
        $this->sendCommand("EHLO " . (isset($_SERVER["HOSTNAME"]) ? $_SERVER["HOSTNAME"] : "localhost"));
        $this->connected = true;
        return true;
    }

    public function login($user, $password)
    {
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($user));
        $response = $this->sendCommand(base64_encode($password));
        if (preg_match("/^235/", $response)) {
            return true;
        }
        throw new \Exception("SMTP AUTH failed: " . substr($response, 0, 200));
    }

    public function send($fromAddr, $fromName, $toAddr, $ccAddr, $subject, $htmlBody)
    {
        $this->sendCommand("MAIL FROM:<" . $fromAddr . ">");
        foreach (array_merge([$toAddr], array_filter([$ccAddr])) as $recipient) {
            $this->sendCommand("RCPT TO:<" . $recipient . ">");
        }

        $this->sendCommand("DATA");

        $message = "";
        $message .= "From: " . $this->encodeHeader($fromName) . " <" . $fromAddr . ">\r\n";
        $message .= "To: <" . $toAddr . ">\r\n";
        if (!empty($ccAddr)) {
            $message .= "Cc: <" . $ccAddr . ">\r\n";
        }
        $message .= "Subject: " . $this->encodeHeader($subject) . "\r\n";
        $message .= "Date: " . date("r") . "\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n";
        $message .= "\r\n";
        $message .= $htmlBody;

        $message = str_replace("\r\n", "\n", $message);
        $message = str_replace("\n", "\r\n", $message);
        $message .= "\r\n.\r\n";

        $response = $this->sendRaw($message);
        if (preg_match("/^250/", $response)) {
            return true;
        }
        throw new \Exception("SMTP DATA failed: " . substr($response, 0, 300));
    }

    public function close()
    {
        if ($this->connected && $this->socket) {
            try {
                $this->sendCommand("QUIT");
            } catch (\Exception $e) {}
        }
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
    }

    private function encodeHeader($str)
    {
        if (preg_match('/[^\x00-\x7F]/', $str)) {
            return "=?UTF-8?B?" . base64_encode($str) . "?=";
        }
        return $str;
    }

    private function sendCommand($command)
    {
        return $this->sendRaw($command . "\r\n");
    }

    private function sendRaw($data)
    {
        if (!$this->socket) {
            throw new \Exception("SMTP not connected");
        }
        fwrite($this->socket, $data);
        return $this->readResponse();
    }

    private function readResponse()
    {
        $response = "";
        while ($line = fgets($this->socket)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === " ") break;
        }
        if (empty($response)) {
            throw new \Exception("SMTP connection lost");
        }
        return $response;
    }

    public function __destruct()
    {
        $this->close();
    }
}
