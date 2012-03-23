<?php
namespace TYPO3\TYPO3\TypoScript;

/*                                                                        *
 * This script belongs to the FLOW3 package "TYPO3.TYPO3".                *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * A TypoScript File object
 *
 * @FLOW3\Scope("prototype")
 */
class File extends \TYPO3\TypoScript\AbstractContentObject {

	/**
	 * Path and filename of the file this TS object is representing.
	 * @var string
	 */
	protected $pathAndFilename;

	/**
	 * Sets the ath and filename of the file this TS object is representing.
	 *
	 * @param string $pathAndFilename
	 * @return void
	 */
	public function setPathAndFilename($pathAndFilename) {
		$this->pathAndFilename = $pathAndFilename;
	}

	/**
	 * @return string
	 */
	public function getPathAndFilename() {
		return $this->pathAndFilename;
	}

	/**
	 * Returns the rendered content of this content object
	 *
	 * @return string The rendered content as a string
	 */
	public function render() {
		if (file_exists($this->pathAndFilename)) {
			return file_get_contents($this->pathAndFilename);
		} else {
			return 'WARNING: File "' . $this->pathAndFilename . '" not found.';
		}
	}

}
?>