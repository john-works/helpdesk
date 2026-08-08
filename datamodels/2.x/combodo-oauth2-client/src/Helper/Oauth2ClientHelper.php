<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Oauth2Client\Helper;

use Exception;
use utils;

class Oauth2ClientHelper
{
	const MODULE_NAME = 'combodo-oauth2-client';

	public static function GetClassName(string $sProvider): string
	{
		$i = strrpos($sProvider, '\\');
		if ($i === false) {
			return $sProvider;
		}

		return substr($sProvider, $i + 1);
	}

	public static function GetProviderName(string $sProvider): string
	{
		return mb_strtolower(static::GetClassName($sProvider));
	}


	/**
	 * @return string
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public static function GetLandingURL(): string
	{
		try {
			return utils::GetAbsoluteUrlModulesRoot().Oauth2ClientHelper::MODULE_NAME."/landing.php";
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}


	/**
	 * @param string $sName
	 * @param string $sProvider
	 * @param string $sAction
	 *
	 * @return string
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public static function GetConnectUrl(string $sName, string $sProvider, string $sAction): string
	{
		try {
			$sName = urlencode($sName);
			$sProvider = urlencode(base64_encode($sProvider));

			$sUrl = utils::GetAbsoluteUrlModulesRoot().Oauth2ClientHelper::MODULE_NAME."/connect.php?name=$sName&provider=$sProvider&action=$sAction";

			return $sUrl;
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}
}
