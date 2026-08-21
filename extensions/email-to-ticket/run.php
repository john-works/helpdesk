<?php
define("PHP_PATH", "C:\\wamp64\\bin\\php\\php8.1.33\\php.exe");
define("SCRIPT", __DIR__ . "\\process.php");
define("POLL_INTERVAL", 5);

$daemonLock = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'email-to-ticket-daemon.lock', 'c');
if ($daemonLock === false || !flock($daemonLock, LOCK_EX | LOCK_NB)) {
    if (is_resource($daemonLock)) {
        fclose($daemonLock);
    }
    exit(0);
}
register_shutdown_function(function () use ($daemonLock) {
    flock($daemonLock, LOCK_UN);
    fclose($daemonLock);
});

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
