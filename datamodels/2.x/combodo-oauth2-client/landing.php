<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     https://www.combodo.com/documentation/combodo-software-license.html
 *
 */

namespace Combodo\iTop\Oauth2Client;

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\Application\WebPage\WebPage;
use Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException;
use Combodo\iTop\Oauth2Client\Helper\Oauth2ClientLog;
use Combodo\iTop\Oauth2Client\Model\Oauth2ClientService;
use Combodo\iTop\Oauth2Client\Service\Oauth2Service;
use iTopStandardURLMaker;

/**
 *  Return from OpenID Provider after a successful login
 */
require_once('../../approot.inc.php');
require_once(APPROOT.'bootstrap.inc.php');
require_once(APPROOT.'application/startup.inc.php');

Oauth2ClientLog::Enable();
Oauth2ClientLog::Debug('---------------------------------');
Oauth2ClientLog::Debug($_SERVER['REQUEST_URI']);

Oauth2ClientLog::Debug("--> Entering Oauth2 landing page");
$sSessionLog = session_id().' '.\utils::GetSessionLog();
Oauth2ClientLog::Debug("SESSION: $sSessionLog");

try {
	$sTransactionId = Session::Get('transaction_id');
	if (empty($sTransactionId) || !\utils::IsTransactionValid($sTransactionId, false)) {
		\LoginWebPage::HTTP401Error();
	}

	$sName = Session::Get('oauth2_client_name');
	$sProvider = Session::Get('oauth2_client_provider');

	Oauth2Service::GetInstance()->Init($sName, $sProvider);
	$oOauth2Client = Oauth2ClientService::GetInstance()->GetOauth2Client();

	$sToken = Oauth2Service::GetInstance()->AuthenticateFinish();

	$oOauth2Client::SetSessionMessage(get_class($oOauth2Client), $oOauth2Client->GetKey(), 1, \Dict::Format("Oauth2Client:UI:Message:ValidationOK", $sToken), WebPage::ENUM_SESSION_MESSAGE_SEVERITY_OK, 1);
} catch (Oauth2ClientException $e) {
	if (!is_null($oOauth2Client)) {
		$sMsg = $e->sError ?? $e->getMessage();
		$oOauth2Client::SetSessionMessage(get_class($oOauth2Client), $oOauth2Client->GetKey(), 1, \Dict::Format("Oauth2Client:UI:Message:ValidationError", $sMsg), WebPage::ENUM_SESSION_MESSAGE_SEVERITY_ERROR, 1);
	}
} catch (\Exception $e) {
	//exception instantiated to generate log.
	$e = new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
	if (!is_null($oOauth2Client)) {
		$oOauth2Client::SetSessionMessage(get_class($oOauth2Client), $oOauth2Client->GetKey(), 1, \Dict::Format("Oauth2Client:UI:Message:ValidationError", $e->getMessage()), WebPage::ENUM_SESSION_MESSAGE_SEVERITY_ERROR, 1);
	}
}

if (!is_null($oOauth2Client)) {
	$sUrl = iTopStandardURLMaker::MakeObjectURL(get_class($oOauth2Client), $oOauth2Client->GetKey());
	header('HTTP/1.1 307 Temporary Redirect');
	header("Location: $sUrl");
}
