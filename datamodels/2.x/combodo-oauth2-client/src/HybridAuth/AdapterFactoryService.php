<?php

namespace Combodo\iTop\Oauth2Client\HybridAuth;

use Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException;
use Combodo\iTop\Oauth2Client\Helper\Oauth2ClientLog;
use Exception;
use Hybridauth\Adapter\AdapterInterface;
use Hybridauth\Hybridauth;
use Hybridauth\Logger\Logger;

class AdapterFactoryService
{
	private static ?AdapterFactoryService $oInstance;

	protected function __construct()
	{
	}

	final public static function GetInstance(): AdapterFactoryService
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new static();
		}

		return static::$oInstance;
	}

	final public static function SetInstance(?AdapterFactoryService $oInstance): void
	{
		static::$oInstance = $oInstance;
	}

	/**
	 * @param string $sProviderName
	 * @param array $aConfig
	 * @param \Hybridauth\Logger\Logger|null $oLogger
	 *
	 * @return \Hybridauth\Adapter\AdapterInterface
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public function GetAdapterInterface(string $sProviderName, array $aConfig, Logger $oLogger = null): AdapterInterface
	{
		try {
			if (is_null($oLogger)) {
				$oLogger = new Logger(Oauth2ClientLog::GetHybridauthDebugMode(), APPROOT.'log/hybridauth.log');
			}
			$oHybridauth = new Hybridauth($aConfig, null, null, $oLogger);

			return $oHybridauth->getAdapter($sProviderName);
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}
}
