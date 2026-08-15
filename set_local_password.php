<?php
// Set or reset a local login password for a UserLDAP account.
//
// The password is stored hashed (bcrypt) in priv_user_ldap.local_password_hash and is
// verified as a fallback when LDAP authentication fails for the account.
//
// Usage: php set_local_password.php <login> <new-password>
// Example: php set_local_password.php rkemigisa@ppda.go.ug 'S3cur3-Pass!'

require_once(__DIR__.'/approot.inc.php');
require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/startup.inc.php');

if (PHP_SAPI !== 'cli')
{
    exit("This script must be run from the command line.\n");
}

if ($argc < 3)
{
    fwrite(STDERR, "Usage: php ".basename(__FILE__)." <login> <new-password>\n");
    exit(1);
}

$sLogin = $argv[1];
$sPassword = $argv[2];

try
{
    $oUser = MetaModel::GetObjectByColumn('UserLDAP', 'login', $sLogin, false);
    if ($oUser === null || $oUser === false)
    {
        fwrite(STDERR, "No UserLDAP account found with login '$sLogin'.\n");
        exit(1);
    }

    $oUser->Set('local_password', $sPassword);
    $oUser->DBUpdate();

    echo "OK: local login password set for UserLDAP account '$sLogin' (id ".$oUser->GetKey().").\n";
    echo "This password will be accepted as a fallback when LDAP authentication fails.\n";
}
catch (Exception $e)
{
    fwrite(STDERR, "ERROR: ".$e->getMessage()."\n");
    exit(1);
}
