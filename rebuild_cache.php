<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');

$bBypassMaintenance = true;
require_once __DIR__.'/approot.inc.php';
require_once APPROOT.'setup/runtimeenv.class.inc.php';

echo "Starting cache rebuild...\n";
$oRuntimeEnv = new RunTimeEnvironment();
$aModules = $oRuntimeEnv->CompileFrom('production');
echo "Done! Modules compiled: " . count($aModules) . "\n";
