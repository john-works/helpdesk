<?php
define("PHP_PATH", "C:\\wamp64\\bin\\php\\php8.1.33\\php.exe");
define("SCRIPT", __DIR__ . "\\process.php");
define("POLL_INTERVAL", 5);

echo date("Y-m-d H:i:s") . " Email-to-Ticket daemon started (polling every " . POLL_INTERVAL . "s)\n";

while (true) {
    echo date("Y-m-d H:i:s") . " Polling...\n";
    $output = [];
    $exitCode = 0;
    exec(PHP_PATH . " " . SCRIPT . " 2>&1", $output, $exitCode);
    foreach ($output as $line) {
        echo $line . "\n";
    }
    sleep(POLL_INTERVAL);
}
