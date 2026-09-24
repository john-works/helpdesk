<?php
/**
 * PPDA Kerberos SSO bootstrap.
 *
 * This endpoint is protected by mod_auth_gssapi (see the .htaccess / itop.conf
 * <Location /sso.php>). When the browser presents a valid Kerberos ticket,
 * Apache sets REMOTE_USER and this script silently creates the iTop session for
 * the matching account.
 *
 * Matching is done across ALL iTop user classes (UserExternal and UserLDAP),
 * because Kerberos already proved the AD password - iTop only needs to find the
 * account. The REMOTE_USER (bare sAMAccountName) is also tried with the domain
 * suffix, since LDAP accounts are stored as e.g. "user@ppda.go.ug".
 *
 * Loaded via the "Sign in with your PPDA domain account" button on the iTop
 * login page (see templates/pages/login/login.html.twig): a top-level
 * navigation, exactly like the timesheet /en/sso flow. Apache challenges this
 * URL with Negotiate; domain workstations answer silently and land logged in,
 * other devices just see the login form again.
 */

use Combodo\iTop\Application\Helper\Session;

require_once(__DIR__.'/approot.inc.php');
require_once(APPROOT.'application/application.inc.php');
require_once(APPROOT.'application/wizardhelper.class.inc.php');
require_once(APPROOT.'application/startup.inc.php');
require_once(APPROOT.'application/loginwebpage.class.inc.php');

function sso_redirect($sUrl)
{
	header('Location: '.$sUrl);
	exit;
}

// If a valid iTop session already exists, nothing to do.
if (Session::IsSet('auth_user'))
{
	// Force the login FSM to the CONNECTED state (see below): a session left in
	// the ERROR state would otherwise re-enter the FSM at START on /pages/UI.php
	// and fail, because REMOTE_USER is only set on this endpoint.
	Session::Set('login_state', LoginWebPage::LOGIN_STATE_CONNECTED);
	sso_redirect('./pages/UI.php');
}

// 1) Who does the web server say we are ?
$sRemoteUser = isset($_SERVER['REMOTE_USER']) ? trim($_SERVER['REMOTE_USER']) : '';
if ($sRemoteUser === '' && isset($_SERVER['REDIRECT_REMOTE_USER']))
{
	$sRemoteUser = trim($_SERVER['REDIRECT_REMOTE_USER']);
}
if ($sRemoteUser === '')
{
	sso_redirect('./pages/UI.php?sso=unavailable');
}

// TEMP trace (remove once SSO is confirmed working): this line is reached ONLY
// when Apache authenticated the request (a valid Kerberos ticket was presented).
@file_put_contents('/tmp/sso_trace.log', date('c')."\t".$sRemoteUser."\t".($_SERVER['REMOTE_ADDR'] ?? '')."\n", FILE_APPEND | LOCK_EX);

// 2) Build the list of candidate iTop logins for this AD identity
$aLogins = array();
$sBareLogin = preg_replace('/@.*$/', '', $sRemoteUser);
$aLogins[$sBareLogin] = $sBareLogin;
$aLogins[$sRemoteUser] = $sRemoteUser;
$sBaseDn = MetaModel::GetModuleSetting('authent-ldap', 'base_dn', '');
if (preg_match_all('/dc=([^,]+)/i', $sBaseDn, $aMatches))
{
	$sDomain = implode('.', $aMatches[1]);
	$aLogins[$sBareLogin.'@'.$sDomain] = $sBareLogin.'@'.$sDomain;
}

// 3) Find an enabled iTop account matching any candidate login
$oUser = null;
foreach (array_values($aLogins) as $sCandidate)
{
	$oSet = new DBObjectSet(DBObjectSearch::FromOQL(
		"SELECT User WHERE login = \"".str_replace('"', '""', $sCandidate)."\" AND status = \"enabled\""
	));
	$oUser = $oSet->Fetch();
	if (!is_null($oUser))
	{
		break;
	}
}

if (is_null($oUser))
{
	// No iTop account matches this AD identity -> normal login form
	sso_redirect('./pages/UI.php?sso=unavailable&login='.urlencode($sBareLogin));
}

// 4) Create the iTop session for this user (works for UserLDAP and UserExternal)
$sLogin = $oUser->Get('login');
if (UserRights::Login($sLogin, 'any'))
{
	Session::Set('auth_user', $sLogin);
	Session::Set('login_mode', 'external');
	Session::Set('can_logoff', true);
	// Resuming the login FSM at "read credentials" fails: LoginExternal re-reads
	// REMOTE_USER on /pages/UI.php, which Apache only sets on /sso.php. Jump
	// straight to the CONNECTED state, whose OnConnected handler only checks that
	// auth_user is present in the session (see LoginExternal::OnConnected ->
	// CheckLoggedUser).
	Session::Set('login_state', LoginWebPage::LOGIN_STATE_CONNECTED);
	UserRights::_InitSessionCache();

	// Signal success: send the user into iTop (same as the timesheet SSO flow)
	sso_redirect('./pages/UI.php');
}

// 5) Unable to login this user (disabled etc.)
sso_redirect('./pages/UI.php?sso=unavailable');