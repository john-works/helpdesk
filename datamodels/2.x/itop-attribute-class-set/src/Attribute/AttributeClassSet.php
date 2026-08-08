<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\ItopAttributeClassSet\Helper\ItopAttributeClassSetException;
use Combodo\iTop\ItopAttributeClassSet\Helper\ItopAttributeClassSetLog;


class AttributeClassSet extends AttributeSet
{
	const SEARCH_WIDGET_TYPE = self::SEARCH_WIDGET_TYPE_STRING;

	public function __construct($sCode, array $aParams)
	{
		ItopAttributeClassSetLog::Enable();
		$this->aCSSClasses[] = 'attribute-class-attcode-set';
		$aParams['allowed_values'] = new ValueSetEnumClasses($aParams['class_category'] ?? 'business', $aParams['more_values'] ?? '');
		parent::__construct($sCode, $aParams);
	}

	/**
	 * @return array|string[]
	 */
	public static function ListExpectedParams()
	{
		return array_merge(parent::ListExpectedParams(), ['class_category', 'max_items' /*, 'more_values'*/]);
	}

	/**
	 * @return int
	 * @throws \Combodo\iTop\ItopAttributeClassSet\Helper\ItopAttributeClassSetException
	 */
	public function GetMaxSize()
	{
		try {
			return max(255, 15 * $this->GetMaxItems());
		} catch (Exception $e) {
			throw new ItopAttributeClassSetException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @param \DBObject|null $oHostObject
	 *
	 * @return int|string|null
	 * @throws \Combodo\iTop\ItopAttributeClassSet\Helper\ItopAttributeClassSetException
	 */
	public function GetDefaultValue(DBObject $oHostObject = null)
	{
		try {
			$sDefault = parent::GetDefaultValue($oHostObject);
			if (!$this->IsNullAllowed() && $this->IsNull($sDefault)) {
				// For this kind of attribute specifying null as default value
				// is authorized even if null is not allowed

				// Pick the first one...
				$aClasses = $this->GetAllowedValues();
				$sDefault = key($aClasses);
			}

			return $sDefault;
		} catch (Exception $e) {
			throw new ItopAttributeClassSetException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @return bool
	 */
	public function RequiresIndex()
	{
		return true;
	}

	/**
	 * @return string
	 */
	public function GetBasicFilterLooseOperator()
	{
		return '=';
	}

	/**
	 * @param $value
	 * @param \DBObject $oHostObject
	 * @param bool $bLocalize
	 *
	 * @return string|null
	 *
	 * @throws \Exception
	 */
	public function GetAsHTML($value, $oHostObject = null, $bLocalize = true)
	{
		try {
			if ($value instanceof ormSet) {
				$value = $value->GetValues();
			}
			if (is_array($value)) {
				if ($bLocalize) {
					$aLocalizedValues = [];
					foreach ($value as $sClassName) {
						try {
							$sValue = MetaModel::GetName($sClassName);
							$aLocalizedValues[] = '<span class="attribute-set-item" data-code="'.$sClassName.'" data-label="'.$sValue.'" data-description="" data-tooltip-content="'.$sValue.'">'.$sValue.'</span>';
						} catch (Exception $e) {
							// Ignore bad values
						}
					}
					$value = $aLocalizedValues;
				}
				$value = implode('', $value);
			}

			return '<span class="'.implode(' ', $this->aCSSClasses).'">'.$value.'</span>';
		} catch (Exception $e) {
			throw new ItopAttributeClassSetException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @param $proposedValue
	 *
	 * @return bool
	 */
	public function IsNull($proposedValue)
	{
		return empty($proposedValue);
	}

}