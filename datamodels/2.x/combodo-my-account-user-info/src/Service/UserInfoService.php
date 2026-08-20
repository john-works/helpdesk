<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MyAccount\UserInfo\Service;

use DBObject;
use MetaModel;
use User;
use UserRights;
use utils;

class UserInfoService
{
	private static UserInfoService $oInstance;

	private function __construct()
	{
	}

	final public static function GetInstance(): UserInfoService
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new UserInfoService();
		}

		return static::$oInstance;
	}

	public function GetUserInfoParams(): array
	{
		$aParams = [];
		/** @var \User $oUser */
		$oUser = UserRights::GetUserObject();

		$this->ProvideHtmlUserInfo($oUser, $aParams);
		$this->ProvideHtmlContactInfo($oUser, $aParams);

		return $aParams;
	}


	public function ProvideHtmlUserInfo(User $oUser, &$aParams): void
	{
		if (is_null($oUser)) {
			return;
		}

		if (UserRights::IsActionAllowed(User::class, UR_ACTION_READ, \DBObjectSet::FromObject($oUser)) != UR_ALLOWED_YES) {
			return;
		}

		$aParams['user_link'] = $this->GetEditLink($oUser);

		$oProfileSet = $oUser->Get('profile_list');
		$aProfiles = [];
		while (($oProfile = $oProfileSet->Fetch()) != null) {
			$aProfiles[] = $oProfile->GetAsHTML('profile');
		}
		$sProfileListHtml = implode('<BR>', $aProfiles);

		$oAllowedOrgList = $oUser->Get('allowed_org_list');
		$aAllowedOrgs = [];
		while (($oUserOrg = $oAllowedOrgList->Fetch()) != null) {
			$aAllowedOrgs[] = $oUserOrg->GetAsHTML('allowed_org_name');
		}
		$sAllowedOrgHtml = implode('<BR>', $aAllowedOrgs);

		$aUserInfo = [
			'login' => null,
			'profile_list' => $sProfileListHtml,
			'org_id' => null,
			'allowed_org_list' => $sAllowedOrgHtml,
		];

		$this->ConvertToHtml($aParams, $aUserInfo, 'user', $oUser);
	}

	public function ProvideHtmlContactInfo(User $oUser, &$aParams): void
	{
		if (is_null($oUser)) {
			return;
		}

		$iPersonId = $oUser->Get('contactid');
		if (0 === $iPersonId) {
			return;
		}

		$oPerson = MetaModel::GetObject('Person', $iPersonId);

		$aParams['contact_link'] = $this->GetEditLink($oPerson);
		$aContactInfo = [
			'first_name' => null,
			'name' => null,
			'email' => null,
			'phone' => null,
			'location_name' => null,
		];

		$aParams['contact']['picture'] = UserRights::GetUserPictureAbsUrl($oUser->Get('login'));//$oPerson->GetAsHTML('picture');
		$this->ConvertToHtml($aParams, $aContactInfo, 'contact', $oPerson);
	}

	public function ConvertToHtml(&$aParams, $aData, $sKey, DBObject $oObject)
	{
		foreach ($aData as $sAttCode => $sAttHtml) {
			if ($sAttHtml) {
				$aParams[$sKey][MetaModel::GetLabel(get_class($oObject), $sAttCode)] = $sAttHtml;
			} else {
				$aParams[$sKey][MetaModel::GetLabel(get_class($oObject), $sAttCode)] = $oObject->GetAsHTML($sAttCode);
			}
		}
	}


	private function GetEditLink(DBObject $oObject): ?string
	{
		$sClass = get_class($oObject);
		if (UserRights::IsActionAllowed($sClass, UR_ACTION_MODIFY, \DBObjectSet::FromObject($oObject)) != UR_ALLOWED_YES) {
			return false;
		}

		return sprintf('%spages/UI.php?operation=modify&class=%s&id=%s',
			utils::GetAbsoluteUrlAppRoot(), $sClass, $oObject->GetKey());
	}

}