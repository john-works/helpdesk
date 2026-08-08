<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     https://www.combodo.com/documentation/combodo-software-license.html
 *
 */

namespace Combodo\iTop\Oauth2Client;

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\Application\WebPage\WebPage;
use Combodo\iTop\Oauth2Client\Controller\Oauth2ClientController;
use Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException;
use Combodo\iTop\Oauth2Client\Model\Oauth2ClientService;
use Combodo\iTop\Oauth2Client\Service\Oauth2Service;
use iTopStandardURLMaker;

/**
 *  Return from OpenID Provider after a successful login
 */
require_once('../../approot.inc.php');
require_once(APPROOT.'bootstrap.inc.php');
require_once(APPROOT.'application/startup.inc.php');

try {
	$sName = \utils::ReadParam('name');
	$sProvider = base64_decode(\utils::ReadParam('provider'));
	$sAction = \utils::ReadParam('action');
	Session::Set('oauth2_client_name', $sName);
	Session::Set('oauth2_client_provider', $sProvider);
	Session::Set('transaction_id', \utils::GetNewTransactionId());

	Oauth2Service::GetInstance()->Init($sName, $sProvider);
	$oOauth2Client = Oauth2ClientService::GetInstance()->GetOauth2Client();

	if ($sAction === Oauth2ClientController::ACTION_AUTHENTICATE) {
		// IDP can remain here in case of headless.
		// Otherwise IDP will call back landing.php
		$sToken = Oauth2Service::GetInstance()->Authenticate();
	} else {
		$sToken = Oauth2Service::GetInstance()->GetAccessToken();
	}

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
