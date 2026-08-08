<?php

/**
 * Team Ticket Visibility.
 *
 * Scopes UserRequest / Incident visibility for the dedicated support teams:
 *   - Registry Agent : sees only tickets on a service belonging to the Registry service family
 *   - ICT Agent      : sees only tickets NOT on a Registry service (incl. tickets without any service)
 *
 * Users holding any other profile (or none of these two) are left untouched
 * (their visibility comes from the standard UserRightsProfile add-on).
 *
 * If the service family id below is not "2", adjust REGISTRY_SERVICE_FAMILY_ID.
 * If a user holds BOTH Registry Agent and ICT Agent, they see everything (like other agents).
 */


class TeamTicketVisibility implements \ModuleHandlerApiInterface
{
	/** Service family id that identifies the Registry services in your environment */
	const REGISTRY_SERVICE_FAMILY_ID = 2;

	const REGISTRY_PROFILE_NAME = 'Registry Agent';
	const ICT_PROFILE_NAME = 'ICT Agent';

	const SCOPE_REGISTRY = 1;
	const SCOPE_ICT = 2;
	const SCOPE_UNRESTRICTED = 0;

	public function __construct()
	{
	}

	public static function OnMenuCreation()
	{
	}

	public static function OnMetaModelStarted()
	{
		try {
			// The add-on class below is defined as soon as this file is loaded.
			// The standard "user rights" add-on (which defines UserRightsProfile) is
			// always loaded before the modules datamodel, so it is available here.
			if (class_exists('UserRightsProfile', false) && class_exists('TeamTicketVisibilityAddon', false)) {
				\UserRights::SelectModule('TeamTicketVisibilityAddon');
			}
		} catch (\Exception $e) {
			// Never let a visibility helper break the whole startup
		}
	}
}

if (!class_exists('UserRightsProfile', false)) {
	return;
}

if (!class_exists('TeamTicketVisibilityAddon', false)) {
	class TeamTicketVisibilityAddon extends \UserRightsProfile
	{
		public function GetSelectFilter($oUser, $sClass, $aSettings = array())
		{
			$oFilter = parent::GetSelectFilter($oUser, $sClass, $aSettings);
			if ($oFilter === false) {
				return false;
			}
			if (!$this->IsScopedClass($sClass)) {
				return $oFilter;
			}
			$iScope = $this->GetUserScope($oUser);
			if ($iScope === \TeamTicketVisibility::SCOPE_UNRESTRICTED) {
				return $oFilter;
			}
			$oScopeFilter = $this->BuildScopeFilter($sClass, $iScope);
			if ($oScopeFilter === null) {
				return $oFilter;
			}
			if ($oFilter === true) {
				return $oScopeFilter;
			}
			try {
				return $oFilter->Filter($sClass, $oScopeFilter);
			} catch (\Exception $e) {
				return $oFilter;
			}
		}

		protected function GetUserScope($oUser)
		{
			$bRegistry = \UserRights::HasProfile(\TeamTicketVisibility::REGISTRY_PROFILE_NAME, $oUser);
			$bIct = \UserRights::HasProfile(\TeamTicketVisibility::ICT_PROFILE_NAME, $oUser);
			if ($bRegistry && !$bIct) {
				return \TeamTicketVisibility::SCOPE_REGISTRY;
			}
			if ($bIct && !$bRegistry) {
				return \TeamTicketVisibility::SCOPE_ICT;
			}
			return \TeamTicketVisibility::SCOPE_UNRESTRICTED;
		}

		protected function IsScopedClass($sClass)
		{
			return ($sClass === 'UserRequest')
				|| ($sClass === 'Incident')
				|| is_subclass_of($sClass, 'UserRequest')
				|| is_subclass_of($sClass, 'Incident');
		}

		protected function BuildScopeFilter($sClass, $iScope)
		{
			$iFamilyId = (int)\TeamTicketVisibility::REGISTRY_SERVICE_FAMILY_ID;
			if ($iScope === \TeamTicketVisibility::SCOPE_REGISTRY) {
				$sOql = "SELECT $sClass AS u JOIN Service AS s ON u.service_id = s.id WHERE s.servicefamily_id = $iFamilyId";
			} else {
				$sOql = "SELECT $sClass AS u WHERE (ISNULL(u.service_id)) OR (u.service_id NOT IN (SELECT s FROM Service AS s WHERE s.servicefamily_id = $iFamilyId))";
			}
			try {
				return \DBObjectSearch::FromOQL_AllData($sOql);
			} catch (\Exception $e) {
				return null;
			}
		}
	}
}
