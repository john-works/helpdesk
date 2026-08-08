<?php

class IMAPClient
{
    private $socket;
    private $tag = 0;

    public function connect($host, $port = 143, $ssl = false)
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
            throw new \Exception("Connection failed: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 30);
        $this->readResponse();
        return true;
    }

    public function login($user, $password)
    {
        $response = $this->sendCommand("LOGIN", $this->escape($user) . " " . $this->escape($password));
        if (preg_match("/^.*NO /mi", $response)) {
            throw new \Exception("IMAP LOGIN failed");
        }
        return true;
    }

    public function selectMailbox($mailbox = "INBOX")
    {
        return $this->sendCommand("SELECT", $mailbox);
    }

    public function search($criteria = "UNSEEN")
    {
        $response = $this->sendCommand("SEARCH", $criteria);
        if (preg_match("/\* SEARCH (.*)/", $response, $matches)) {
            $ids = trim($matches[1]);
            if (empty($ids)) {
                return [];
            }
            return explode(" ", $ids);
        }
        return [];
    }

    public function fetchRaw($msgId, $parts = "RFC822")
    {
        return $this->sendCommand("FETCH", "$msgId ($parts)");
    }

    public function fetchHeader($msgId)
    {
        $response = $this->sendCommand("FETCH", "$msgId (BODY.PEEK[HEADER] FLAGS)");
        return $this->parseHeader($response);
    }

    public function fetchBody($msgId)
    {
        $response = $this->sendCommand("FETCH", "$msgId (BODY[])");
        return $this->extractBody($response);
    }

    public function rawCommand($cmd, $args) { return $this->sendCommand($cmd, $args); }
    public function markAsSeen($msgId)
    {
        $this->sendCommand("STORE", "$msgId +FLAGS (\\SEEN)");
    }

    public function copyToFolder($msgId, $folder = "Processed")
    {
        $this->sendCommand("COPY", "$msgId " . $this->escape($folder));
    }

    public function markDeleted($msgId)
    {
        $this->sendCommand("STORE", "$msgId +FLAGS (\\DELETED)");
    }

    public function expunge()
    {
        $this->sendCommand("EXPUNGE");
    }

    public function close()
    {
        if ($this->socket) {
            try {
                $this->sendCommand("LOGOUT");
            } catch (\Exception $e) {}
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function createMailbox($mailbox)
    {
        $this->sendCommand("CREATE", $this->escape($mailbox));
    }

    private function escape($str)
    {
        return "\"" . str_replace(array("\\", "\""), array("\\\\", "\\\""), $str) . "\"";
    }

    private function sendCommand($command, $args = "")
    {
        if (!$this->socket) {
            throw new \Exception("Not connected to IMAP server");
        }

        $this->tag++;
        $tagStr = "TAG" . $this->tag;
        $cmd = "$tagStr $command $args\r\n";

        fwrite($this->socket, $cmd);
        return $this->readResponse($tagStr);
    }

    private function readResponse($expectedTag = null)
    {
        $response = "";
        $done = false;
        $taggedOk = false;

        while (!$done && !feof($this->socket)) {
            $line = fgets($this->socket);
            if ($line === false) break;
            $response .= $line;

            if ($expectedTag) {
                if (preg_match("/^" . preg_quote($expectedTag, "/") . " (OK|NO|BAD|BYE)/", $line)) {
                    $done = true;
                    if (preg_match("/^" . preg_quote($expectedTag, "/") . " OK/", $line)) {
                        $taggedOk = true;
                    }
                }
            } else {
                if (preg_match("/^\* (OK|BYE|PREAUTH)/", $line)) {
                    $done = true;
                    if (preg_match("/^\* OK/", $line)) {
                        $taggedOk = true;
                    }
                }
            }
        }

        if (!$taggedOk) {
            throw new \Exception("IMAP command failed: " . substr($response, 0, 500));
        }

        return $response;
    }

    private function parseHeader($response)
    {
        $header = [];

        if (preg_match("/\* \d+ FETCH \(.*?BODY\[HEADER\] ?\{(?P<size>\d+)\}/s", $response, $m)) {
            $size = (int) $m["size"];
            $pos = strpos($response, $m[0]) + strlen($m[0]);
            $rawHeader = substr($response, $pos, $size);

            $lines = explode("\r\n", $rawHeader);
            $currentField = null;
            foreach ($lines as $line) {
                if (preg_match("/^([A-Z][A-Za-z0-9\-]*):\s?(.*)$/", $line, $matches)) {
                    $currentField = $matches[1];
                    $header[$currentField] = trim($matches[2]);
                } elseif ($currentField && preg_match("/^\s+(.*)$/", $line, $matches)) {
                    $header[$currentField] .= " " . trim($matches[1]);
                }
            }
        }

        if (preg_match("/FLAGS \((.*?)\)/", $response, $m)) {
            $header["_flags"] = $m[1];
        }

        return $header;
    }

    private function extractBody($response)
    {
        if (preg_match("/\* \d+ FETCH \(.*?BODY\[\] ?\{(?P<size>\d+)\}/s", $response, $m)) {
            $size = (int) $m["size"];
            $pos = strpos($response, $m[0]) + strlen($m[0]);
            return substr($response, $pos, $size);
        }

        if (preg_match("/\* \d+ FETCH \(.*?BODY\[TEXT\] ?\{(?P<size>\d+)\}/s", $response, $m)) {
            $size = (int) $m["size"];
            $pos = strpos($response, $m[0]) + strlen($m[0]);
            return substr($response, $pos, $size);
        }

        return $response;
    }

    public function __destruct()
    {
        $this->close();
    }
}