<?php
require_once '/var/www/html/itop/approot.inc.php';
require_once APPROOT.'/application/startup.inc.php';

// Check available modules
if (class_exists('ConfigEditorBase')) {
    echo "Configuration Editor module is AVAILABLE\n";
} else {
    echo "Configuration Editor module is NOT available\n";
}

// Check for other configuration methods
if (method_exists('MetaModel', 'GetConfig')) {
    echo "MetaModel::GetConfig() is available\n";
}
?>
