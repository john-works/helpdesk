<?php
/**
 * Quick script to set a password for an existing iTop user.
 *
 * Usage:
 *   php set_user_password.php <login> <new_password>
 *
 * If the user has a UserLocal account, it sets the password directly.
 * If the user only has a UserLDAP account, it creates a UserLocal copy with the given password.
 */

require_once __DIR__.'/approot.inc.php';
require_once APPROOT.'bootstrap.inc.php';

if ($argc < 3) {
    echo "Usage: php set_user_password.php <login> <new_password>\n";
    exit(1);
}

$sLogin = $argv[1];
$sPassword = $argv[2];

utils::DoNotUseRemoteCallInformations();
metaModel::LoadConfig(APPCONF.'production/config-itop.php');
metaModel::DBOpen();

// --- Check if a UserLocal already exists ---
$aSearch = DBSearch::FromOQL('SELECT UserLocal WHERE login = "'.$sLogin.'"');
$oSet = new DBObjectSet($aSearch);
$oUserLocal = $oSet->Fetch();

if ($oUserLocal) {
    echo "UserLocal found (id=".$oUserLocal->GetKey()."). Setting password...\n";
    $oUserLocal->SetPassword($sPassword);
    echo "Password set successfully for UserLocal login='$sLogin'.\n";
    exit(0);
}

// --- No UserLocal, check if UserLDAP exists ---
$aSearch2 = DBSearch::FromOQL('SELECT UserLDAP WHERE login = "'.$sLogin.'"');
$oSet2 = new DBObjectSet($aSearch2);
$oUserLDAP = $oSet2->Fetch();

if (!$oUserLDAP) {
    echo "Error: No user found with login='$sLogin' (neither UserLocal nor UserLDAP).\n";
    exit(1);
}

echo "UserLDAP found (id=".$oUserLDAP->GetKey()."). Creating UserLocal copy...\n";

$oNewUser = new UserLocal();
$oNewUser->Set('login', $oUserLDAP->Get('login'));
$oNewUser->Set('contactid', $oUserLDAP->Get('contactid'));
$oNewUser->Set('language', $oUserLDAP->Get('language'));
$oNewUser->Set('status', $oUserLDAP->Get('status'));
$oNewUser->Set('password', $sPassword);

$iId = $oNewUser->DBInsert();
echo "Created UserLocal (id=$iId) with password for login='$sLogin'.\n";
echo "NOTE: The old UserLDAP record still exists. You may want to disable or delete it from the iTop admin console.\n";

exit(0);
