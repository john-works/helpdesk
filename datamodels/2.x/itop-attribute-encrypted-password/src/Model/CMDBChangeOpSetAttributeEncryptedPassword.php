<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

class CMDBChangeOpSetAttributeEncryptedPassword extends CMDBChangeOpSetAttributeOneWayPassword
{
	/**
	 * @inheritDoc
	 */
	public static function Init()
	{
		$aParams = [
			'category' => 'core/cmdb, grant_by_profile',
			'key_type' => '',
			'name_attcode' => 'change',
			'state_attcode' => '',
			'reconc_keys' => [],
			'db_table' => 'priv_changeop_setatt_enc_pwd',
			'db_key_field' => 'id',
			'db_finalclass_field' => '',
		];
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();

		// Display lists
		MetaModel::Init_SetZListItems('details', ['date', 'userinfo', 'attcode']); // Attributes to be displayed for the complete details
		MetaModel::Init_SetZListItems('list', ['date', 'userinfo', 'attcode']); // Attributes to be displayed for a list
	}

}