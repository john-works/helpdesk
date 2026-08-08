<?php

namespace Combodo\iTop\Oauth2Client\Model;

use AttributeDateTime;
use Combodo\iTop\ItopAttributeEncryptedPassword\Helper\AttributeEncryptedPasswordException;
use Combodo\iTop\ItopAttributeEncryptedPassword\Model\ormEncryptedPassword;
use Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException;
use Combodo\iTop\Oauth2Client\Helper\Oauth2ClientHelper;
use Combodo\iTop\Oauth2Client\Helper\Oauth2ClientLog;
use DateTime;
use DBObjectSet;
use DBSearch;
use Exception;
use MetaModel;
use Oauth2Client;
use utils;

class Oauth2ClientService
{
	private static ?Oauth2ClientService $oInstance;
	private string $sName;
	private string $sProvider;
	private ?Oauth2Client $oOauth2Client;

	protected function __construct()
	{
		Oauth2ClientLog::Enable();
	}

	final public static function GetInstance(): Oauth2ClientService
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new static();
		}

		return static::$oInstance;
	}

	final public static function SetInstance(?Oauth2ClientService $oInstance): void
	{
		static::$oInstance = $oInstance;
	}

	public static function GetHybridauthProvider(Oauth2Client $oObj): string
	{
		$sClassName = Oauth2ClientHelper::GetClassName(get_class($oObj));
		$sProviderClassName = str_replace('Oauth2Client', '', $sClassName);

		return "Hybridauth\\Provider\\$sProviderClassName";
	}

	/**
	 * @param string $sName
	 * @param string $sProvider
	 *
	 * @return void
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public function InitClient(string $sName, string $sProvider)
	{
		try {
			Oauth2ClientLog::Debug(__FUNCTION__, null, [$sName, $sProvider]);
			$this->sName = $sName;
			$this->sProvider = $sProvider;
			$this->oOauth2Client = null;
			$this->GetOauth2Client();
		} catch (Oauth2ClientException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @param \Oauth2Client $oOauth2Client
	 *
	 * @return void
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public function InitClientByOauth2Client(Oauth2Client $oOauth2Client): void
	{
		try {
			Oauth2ClientLog::Debug(__FUNCTION__, null, [$oOauth2Client]);
			$this->sName = $oOauth2Client->Get('name');
			$this->sProvider = $oOauth2Client->Get('provider');
			$this->oOauth2Client = $oOauth2Client;
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @return \Oauth2Client
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public function GetOauth2Client(): Oauth2Client
	{
		try {
			if (isset($this->oOauth2Client)) {
				return $this->oOauth2Client;
			}

			Oauth2ClientLog::Debug(__FUNCTION__, null, [$this->sName, $this->sProvider]);
			$oSearch = DBSearch::FromOQL("SELECT Oauth2Client WHERE name=:name AND provider=:provider");
			$oSet = new DBObjectSet($oSearch, [], ['name' => $this->sName, 'provider' => $this->sProvider]);
			if ($oSet->Count() != 1) {
				throw new Oauth2ClientException("Missing configuration", 0, null,
					['name' => $this->sName, 'provider' => $this->sProvider]);
			}

			/** @var Oauth2Client $oDBObject */
			$oDBObject = $oSet->Fetch();

			$this->oOauth2Client = $oDBObject;

			return $this->oOauth2Client;
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @return \array[][]
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public function GetAuthenticateConfiguration(): array
	{
		try {
			$oOauth2Client = $this->GetOauth2Client();
			$aData = [
				'keys' => [
					'id' => $oOauth2Client->Get('client_id'),
					'secret' => $oOauth2Client->Get('client_secret')->GetPassword(),
				],
				'adapter' => $oOauth2Client->Get('provider'),
				//'expires_in' => date_format(new \DateTime($oExpireAt), 'U') - time(),
				'callback' => Oauth2ClientHelper::GetLandingURL(),
				'debug_mode' => Oauth2ClientLog::GetHybridauthDebugMode(),
				'enabled' => true,
			];
			$sScope = $oOauth2Client->Get('scope');
			if (utils::IsNotNullOrEmptyString($sScope)) {
				$aData['scope'] = $sScope;
			}
			$aModelToHybridauthMapping = $oOauth2Client->GetModelToHybridauthMapping();
			$this->MapAttCodesToConf($oOauth2Client, $aModelToHybridauthMapping, $aData);
			$sProviderName = Oauth2ClientHelper::GetProviderName($this->sProvider);
			$aConf = ['providers' => [$sProviderName => $aData]];

			return $aConf;
		} catch (Oauth2ClientException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @return \array[][]
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public function GetRefreshTokenConfiguration(): array
	{
		try {
			$oOauth2Client = $this->GetOauth2Client();
			$sProviderName = Oauth2ClientHelper::GetProviderName($this->sProvider);
			$aConf = $this->GetAuthenticateConfiguration();
			$aData = $aConf['providers'][$sProviderName];
			$aTokenMapping = $oOauth2Client->GetTokenModelToHybridauthMapping();
			$this->MapAttCodesToConf($oOauth2Client, $aTokenMapping, $aTokens);
			if (count($aTokens) > 0) {
				$aData['tokens'] = $aTokens;
			}
			$aConf = ['providers' => [$sProviderName => $aData]];
			$aConf['authorization_state'] = $oOauth2Client->Get('authorization_state');

			return $aConf;
		} catch (Oauth2ClientException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @param \Oauth2Client $oOauth2Client
	 * @param array $aHybridToAttCodeMapping
	 * @param array|null $aData
	 *
	 * @return void
	 * @throws \Combodo\iTop\ItopAttributeEncryptedPassword\Helper\AttributeEncryptedPasswordException
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	private function MapAttCodesToConf(Oauth2Client $oOauth2Client, array $aHybridToAttCodeMapping, array &$aData = null): void
	{
		try {
			if (is_null($aData)) {
				$aData = [];
			}
			foreach ($aHybridToAttCodeMapping as $sHybridauthId => $sAttCode) {
				$val = $oOauth2Client->Get($sAttCode);
				if ($val instanceof ormEncryptedPassword) {
					$val = $val->GetPassword();
				}

				if (!is_null($val) && MetaModel::GetAttributeDef(get_class($oOauth2Client), $sAttCode) instanceof AttributeDateTime) {
					$oDateTime = DateTime::createFromFormat(AttributeDateTime::GetSQLFormat(), $val);
					$val = $oDateTime->getTimestamp();
				}

				if (utils::IsNotNullOrEmptyString($val)) {
					$aData[$sHybridauthId] = $val;
				}
			}
		} catch (AttributeEncryptedPasswordException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @param array $aTokenResponse
	 * @param string $sDefaultScope
	 *
	 * @return void
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public function SaveTokens(array $aTokenResponse, string $sDefaultScope): void
	{
		try {
			$oOauth2Client = $this->GetOauth2Client();
			Oauth2ClientLog::Debug(__FUNCTION__, null, $aTokenResponse);
			if (utils::IsNullOrEmptyString($oOauth2Client->Get('scope'))) {
				$oOauth2Client->Set('scope', $sDefaultScope);
			}
			$aTokenMapping = $oOauth2Client->GetTokenModelToHybridauthMapping();
			foreach ($aTokenMapping as $sHybridauthId => $sAttCode) {
				$sValue = $aTokenResponse[$sHybridauthId] ?? null;
				if (!is_null($sValue)) {
					$oOauth2Client->Set($sAttCode, $sValue);
				}
			}
			$sAuthState = $aTokenResponse['authorization_state'] ?? '';
			if (utils::IsNotNullOrEmptyString($sAuthState)) {
				$oOauth2Client->Set('authorization_state', $sAuthState);
			}
			$oOauth2Client->DBWrite();
		} catch (Oauth2ClientException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @return string|null
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public function GetAccessToken(): ?string
	{
		try {
			$oOauth2Client = $this->GetOauth2Client();
			$oAccessToken = $oOauth2Client->Get('access_token');
			if (is_null($oAccessToken)) {
				return null;
			}

			return $oAccessToken->GetPassword();
		} catch (Oauth2ClientException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @return bool
	 * @throws \Combodo\iTop\Oauth2Client\Helper\Oauth2ClientException
	 */
	public function IsExpired(): bool
	{
		try {
			$oOauth2Client = $this->GetOauth2Client();
			$oAttDateTime = $oOauth2Client->Get('access_token_expiration');
			if (is_null($oAttDateTime)) {
				throw new Oauth2ClientException('No expiration date found');
			}
			$oDateTime = DateTime::createFromFormat(AttributeDateTime::GetSQLFormat(), $oAttDateTime);

			return $oDateTime < new DateTime();
		} catch (Oauth2ClientException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new Oauth2ClientException(__FUNCTION__.': failed', 0, $e);
		}
	}
}
