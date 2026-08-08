<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ItopAttributeEncryptedPassword\Model;

use Combodo\iTop\ItopAttributeEncryptedPassword\Helper\AttributeEncryptedPasswordException;
use Exception;
use MetaModel;
use SimpleCrypt;
use utils;

class ormEncryptedPassword
{
	public const STARS = '*****';
	private string $sEncryptedPassword;


	/**
	 * Constructor, initializes the password from the encrypted values
	 */
	public function __construct($sEncryptedValue)
	{
		$this->sEncryptedPassword = $sEncryptedValue;
	}

	/**
	 * Encrypts the clear text password, with a unique salt
	 *
	 * @param string $sClearTextPassword
	 *
	 * @throws \Combodo\iTop\ItopAttributeEncryptedPassword\Helper\AttributeEncryptedPasswordException
	 */
	public function SetPassword(#[\SensitiveParameter] string $sClearTextPassword): void
	{
		try {
			$oSimpleCrypt = new SimpleCrypt(MetaModel::GetConfig()->GetEncryptionLibrary());
			$this->sEncryptedPassword = $oSimpleCrypt->Encrypt(MetaModel::GetConfig()->GetEncryptionKey(), $sClearTextPassword);
		} catch (Exception $e) {
			throw new AttributeEncryptedPasswordException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * Print the password: displays some stars
	 *
	 * @return string
	 */
	public function __toString()
	{
		return self::STARS; // Password can not be read
	}

	public function HasAValue(): bool
	{
		return utils::IsNotNullOrEmptyString($this->GetPassword());
	}

	/**
	 * Displays the password: displays some stars
	 *
	 * @return string
	 */
	public function GetAsHTML(): string
	{
		return self::STARS; // Password can not be read
	}

	/**
	 * Get decrypted password
	 *
	 * @return string
	 * @throws \Combodo\iTop\ItopAttributeEncryptedPassword\Helper\AttributeEncryptedPasswordException
	 */
	public function GetPassword(): string
	{
		if ($this->sEncryptedPassword === '') {
			return '';
		}
		try {
			$oSimpleCrypt = new SimpleCrypt(MetaModel::GetConfig()->GetEncryptionLibrary());

			return $oSimpleCrypt->Decrypt(MetaModel::GetConfig()->GetEncryptionKey(), $this->sEncryptedPassword);
		} catch (Exception $e) {
			throw new AttributeEncryptedPasswordException(__FUNCTION__.': failed', 0, $e);
		}
	}

	/**
	 * @return string
	 */
	public function GetEncryptedPassword(): string
	{
		return $this->sEncryptedPassword;
	}

	public function GetDisplayValue(): string
	{
		return self::STARS;
	}

}