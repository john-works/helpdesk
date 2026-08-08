<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Oauth2Client\Helper;

use Hybridauth\Logger\Logger;
use LogAPI;

class Oauth2ClientLog extends LogAPI
{
	const CHANNEL_DEFAULT = 'Oauth2';

	protected static $m_oFileLog = null;

	public static function Enable($sTargetFile = null)
	{
		if (empty($sTargetFile)) {
			$sTargetFile = APPROOT.'log/error.log';
		}
		parent::Enable($sTargetFile);
	}

	public static function GetHybridauthDebugMode(): string
	{
		if (Oauth2ClientLog::IsLogLevelEnabled(LogAPI::LEVEL_DEBUG, self::CHANNEL_DEFAULT)) {
			return Logger::DEBUG;
		}

		if (Oauth2ClientLog::IsLogLevelEnabled(LogAPI::LEVEL_INFO, self::CHANNEL_DEFAULT)) {
			return Logger::INFO;
		}

		if (Oauth2ClientLog::IsLogLevelEnabled(LogAPI::LEVEL_ERROR, self::CHANNEL_DEFAULT)) {
			return Logger::ERROR;
		}

		return Logger::NONE;
	}
}
