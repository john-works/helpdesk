<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\ItopAttributeEncryptedPassword\Model\ormEncryptedPassword;

class AttributeEncryptedPassword extends AttributeEncryptedString implements iAttributeNoGroupBy
{
	const SEARCH_WIDGET_TYPE = self::SEARCH_WIDGET_TYPE_RAW;

	/**
	 * @param string $sCode
	 * @param array $aParams
	 *
	 * @throws \Exception
	 * @noinspection SenselessProxyMethodInspection
	 */
	public function __construct($sCode, $aParams)
	{
		parent::__construct($sCode, $aParams);
	}

	public static function ListExpectedParams()
	{
		return ['depends_on', 'sql', 'is_null_allowed', 'on_target_delete', 'allowed_values'];
	}

	public function GetEditClass()
	{
		return 'Encrypted Password';
	}

	public static function IsBasedOnDBColumns()
	{
		return true;
	}

	public static function IsScalar()
	{
		return true;
	}

	public function IsWritable()
	{
		return true;
	}

	public function GetDefaultValue(DBObject $oHostObject = null)
	{
		return '';
	}

	public function IsNullAllowed()
	{
		return $this->GetOptional('is_null_allowed', false);
	}

	/**
	 * Facilitate things: allow the user to Set the value from a string or from an ormEncryptedPassword (already encrypted)
	 *
	 * @param $proposedValue
	 * @param $oHostObj
	 *
	 * @return \Combodo\iTop\ItopAttributeEncryptedPassword\Model\ormEncryptedPassword
	 * @throws \Combodo\iTop\ItopAttributeEncryptedPassword\Helper\AttributeEncryptedPasswordException
	 */
	public function MakeRealValue($proposedValue, $oHostObj)
	{
		$oPassword = $proposedValue;
		if ($oPassword instanceof ormEncryptedPassword) {
			return clone $proposedValue;
		}

		if (!is_string($proposedValue)) {
			throw new \CoreException(Dict::S('Core:AttributeEncryptedPassword:WrongType'));
		}

		if ($proposedValue === ormEncryptedPassword::STARS) {
			//when editing CMDB object without modifying current field we need to keep previous pwd value
			return $oHostObj->Get($this->m_sCode);
		}

		$oPassword = new ormEncryptedPassword('');
		$oPassword->SetPassword($proposedValue);

		return $oPassword;
	}

	public function FromSQLToValue($aCols, $sPrefix = '')
	{
		return new ormEncryptedPassword($aCols[$sPrefix]);
	}

	/**
	 * Encrypt the value before storing it in the database
	 *
	 * @param $value
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function GetSQLValues($value)
	{
		$oPassword = $this->MakeRealValue($value, $this);

		$aValues = [];
		$aValues[$this->Get('sql')] = $oPassword->GetEncryptedPassword();

		return $aValues;
	}

	protected function GetSQLCol($bFullSpec = false)
	{
		return 'BLOB';
	}

	public function GetMaxSize()
	{
		return 65535;
	}

	public function GetFilterDefinitions()
	{
		// Note: due to this, you will get an error if a an encrypted field is declared as a search criteria (see ZLists)
		// not allowed to search on encrypted fields !
		return [];
	}

	public function GetImportColumns()
	{
		$aColumns = [];
		$aColumns[$this->GetCode()] = 'TEXT'.CMDBSource::GetSqlStringColumnDefinition();

		return $aColumns;
	}

	/**
	 * @param $aCols
	 * @param $sPrefix
	 *
	 * @return \Combodo\iTop\ItopAttributeEncryptedPassword\Model\ormEncryptedPassword
	 * @throws \Combodo\iTop\ItopAttributeEncryptedPassword\Helper\AttributeEncryptedPasswordException
	 * @throws \MissingColumnException
	 */
	public function FromImportToValue($aCols, $sPrefix = '')
	{
		if (!isset($aCols[$sPrefix])) {
			$sAvailable = implode(', ', array_keys($aCols));
			throw new MissingColumnException("Missing column '$sPrefix' from {$sAvailable}");
		}
		$sClearPwd = $aCols[$sPrefix];

		$oPassword = new ormEncryptedPassword('');
		$oPassword->SetPassword($sClearPwd);

		return $oPassword;
	}

	public function GetBasicFilterOperators()
	{
		return [];
	}

	public function GetBasicFilterLooseOperator()
	{
		return '=';
	}

	public function GetBasicFilterSQLExpr($sOpCode, $value)
	{
		return 'true';
	}

	public function GetAsHTML($sValue, $oHostObject = null, $bLocalize = true)
	{
		if (is_object($sValue)) {
			return $sValue->GetAsHTML();
		}

		return '';
	}

	public function GetAsCSV($sValue, $sSeparator = ',', $sTextQualifier = '"', $oHostObject = null, $bLocalize = true, $bConvertToPlainText = false)
	{
		return ''; // Not exportable in CSV
	}

	public function GetAsXML($sValue, $oHostObject = null, $bLocalize = true)
	{
		return ''; // Not exportable in XML
	}

	public function GetValueLabel($sValue, $oHostObj = null)
	{
		// Don't display anything in "group by" reports
		return ormEncryptedPassword::STARS;
	}

	/**
	 * @inheritDoc
	 */
	public function HasAValue($proposedValue): bool
	{
		if ($proposedValue instanceof ormEncryptedPassword) {
			return $proposedValue->HasAValue();
		}

		// On object creation, the attribute value is "" instead of an ormEncryptedPassword...
		if (is_string($proposedValue)) {
			return utils::IsNotNullOrEmptyString($proposedValue);
		}

		return parent::HasAValue($proposedValue);
	}

	protected function GetChangeRecordAdditionalData(CMDBChangeOp $oMyChangeOp, DBObject $oObject, $original, $value): void
	{
		if (is_null($original)) {
			$original = '';
		}
		$oMyChangeOp->Set('prev_pwd', $original);
	}

	protected function GetChangeRecordClassName(): string
	{
		return CMDBChangeOpSetAttributeEncryptedPassword::class;
	}
}
