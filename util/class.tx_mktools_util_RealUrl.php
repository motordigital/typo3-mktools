<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Hannes Bochmann <hannes.bochmann@dmk-ebusiness.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

tx_rnbase::load('tx_rnbase_util_DB');
tx_rnbase::load('tx_mktools_util_miscTools');

/**
 * @package TYPO3
 * @subpackage tx_mktools
 * @author Hannes Bochmann <hannes.bochmann@dmk-ebusiness.de>
 * @author Michael Wagner <michael.wagner@dmk-ebusiness.de>
 */
class tx_mktools_util_RealUrl {

	/**
	 * @return array[tx_mktools_model_Pages]
	 */
	public function getPagesWithFixedPostVarType() {
		$options = array(
			'enablefieldsfe'	=> 1,
			'wrapperclass'		=> 'tx_mktools_model_Pages',
			'where'				=> 'tx_mktools_fixedpostvartype > 0'
		);

		return $this->selectPagesByOptions($options);
	}


	/**
	 * @param int $modificationTimeStamp
	 *
	 * @return boolean
	 */
	public function areTherePagesWithFixedPostVarTypeModifiedLaterThan(
		$modificationTimeStamp
	) {
		$options = array(
			'enablefieldsfe'	=> 	1,
			'where'				=> 	'tx_mktools_fixedpostvartype > 0 AND tstamp > ' .
									$modificationTimeStamp
		);

		$result = $this->selectPagesByOptions($options, 'COUNT(uid) AS uid_count');

		return 	(isset($result[0]['uid_count'])) ?
					(boolean) $result[0]['uid_count'] :
					FALSE;
	}

	/**
	 * @param int $modificationTimeStamp
	 *
	 * @return boolean
	 */
	public function areThereFixedPostVarTypesModifiedLaterThan(
		$modificationTimeStamp
	) {
		$options = array(
			'enablefieldsfe'	=> 	1,
			'where'				=> 	'tstamp > ' . $modificationTimeStamp
		);

		$result = $this->getDbUtil()->doSelect(
			'COUNT(uid) AS uid_count', 'tx_mktools_fixedpostvartypes', $options
		);

		return 	(isset($result[0]['uid_count'])) ?
				(boolean) $result[0]['uid_count'] :
				FALSE;
	}

	/**
	 * @param int $realUrlConfigurationLastModified
	 *
	 * @return boolean
	 */
	public function isTemplateFileModifiedLaterThan(
		$realUrlConfigurationLastModified
	) {
		$templateFile = tx_mktools_util_miscTools::getRealUrlConfigurationTemplate();
		if (file_exists($templateFile)) {
			return (
				filemtime($templateFile) >
				$realUrlConfigurationLastModified
			);
		}
		return FALSE;
	}

	/**
	 * @param array $options
	 * @param string $what
	 *
	 * @return array
	 */
	private function selectPagesByOptions(array $options, $what = '*') {
		return $this->getDbUtil()->doSelect(
			$what, 'pages', $options
		);
	}

	/**
	 * @return Tx_Rnbase_Database_Connection
	 */
	protected function getDbUtil() {
		return tx_rnbase::makeInstance('Tx_Rnbase_Database_Connection');
	}

	/**
	 * @return boolean
	 */
	public function needsRealUrlConfigurationToBeGenerated() {
		$realUrlConfigurationFile =
			tx_mktools_util_miscTools::getRealUrlConfigurationFile();

		$realUrlConfigurationLastModified = 0;
		if(file_exists($realUrlConfigurationFile)) {
			$realUrlConfigurationLastModified = filemtime($realUrlConfigurationFile);
		}

		$areTherePagesWithFixedPostVarTypeModifiedLaterThan =
			$this->areTherePagesWithFixedPostVarTypeModifiedLaterThan(
				$realUrlConfigurationLastModified
			);

		$areThereFixedPostVarTypesModifiedLaterThan =
			$this->areThereFixedPostVarTypesModifiedLaterThan(
				$realUrlConfigurationLastModified
			);

		$isTemplateFileModifiedLaterThan =
			$this->isTemplateFileModifiedLaterThan(
				$realUrlConfigurationLastModified
			);

		return 	$areTherePagesWithFixedPostVarTypeModifiedLaterThan ||
				$areThereFixedPostVarTypesModifiedLaterThan ||
				$isTemplateFileModifiedLaterThan;
	}

	/**
	 * @param array[tx_mktools_model_Pages] $pages
	 *
	 * @return boolean
	 */
	public function generateSerializedRealUrlConfigurationFileByPages(array $pages) {
		$configurationFileWritten = FALSE;

		$realUrlConfigurationTemplate = $this->getRealUrlConfigurationTemplateContent();
		if (
			(strlen($realUrlConfigurationTemplate) > 0) &&
			($realUrlConfigurationFile = tx_mktools_util_miscTools::getRealUrlConfigurationFile())
		) {
			//wir brauchen erst eine datei ohne serialisierung damit das array korrekt gebaut wird
			$this->generateRealUrlConfigurationFileWithoutSerialization(
				$this->getFixedPostVarPageStringsByPages($pages)
			);
			$configurationFileWritten =
				$this->generateRealUrlConfigurationFileWithSerialization();
		}

		return (boolean) $configurationFileWritten;
	}

	/**
	 * im template kann $TYPO3_CONF_VARS oder auch
	 * $GLOBALS['TYPO3_CONF_VARS'] verwendet werden
	 *
	 * @return string
	 */
	private function getRealUrlConfigurationTemplateContent() {
		return str_replace(
			'$TYPO3_CONF_VARS', '$GLOBALS[\'TYPO3_CONF_VARS\']',
			file_get_contents(
				tx_mktools_util_miscTools::getRealUrlConfigurationTemplate()
			)
		);
	}

	/**
	 * @param array[tx_mktools_model_Pages] $pages
	 *
	 * @return array
	 */
	private function getFixedPostVarPageStringsByPages(array $pages) {
		$fixedPostVarPageStrings = array();
		foreach ($pages as $page) {
			if ($fixedPostVarType = $page->getFixedPostVarType()) {
				$fixedPostVarPageStrings[] = 	$page->getUid() . " => '" .
												$fixedPostVarType->getIdentifier() . "'";
			}
		}

		return $fixedPostVarPageStrings;
	}

	/**
	 * @param array $fixedPostVarPageStrings
	 *
	 * @return void
	 */
	private function generateRealUrlConfigurationFileWithoutSerialization(
		array $fixedPostVarPageStrings
	) {
		$realUrlConfigurationTemplate = $this->getRealUrlConfigurationTemplateContent();
		$realUrlConfigurationFile =
			tx_mktools_util_miscTools::getRealUrlConfigurationFile();

		$fixedPostVarPageString = implode(',' . LF, $fixedPostVarPageStrings);
		$realUrlConfigurationFileContent = str_replace(
			'###FIXEDPOSTVARPAGES###',
			$fixedPostVarPageString,
			$realUrlConfigurationTemplate
		);
		$realUrlConfigurationFileContent =
			$this->addDoNotEditHint($realUrlConfigurationFileContent);

		file_put_contents(
			$realUrlConfigurationFile, $realUrlConfigurationFileContent
		);
	}

	/**
	 * @param array $fixedPostVarPageStrings
	 *
	 * @return boolean
	 */
	private function generateRealUrlConfigurationFileWithSerialization(
	) {
		$realUrlConfigurationFile = tx_mktools_util_miscTools::getRealUrlConfigurationFile();
		include $realUrlConfigurationFile;
		$serializedContent = 	"<?php\n" .
								'$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\']' .
								'[\'realurl\'] = unserialize(\'' .
								serialize(
									$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']
								) . '\');';
		$serializedContent = $this->addDoNotEditHint($serializedContent);

		return file_put_contents(
			$realUrlConfigurationFile, $serializedContent
		);
	}

	/**
	 * @param string $initialString
	 *
	 * @return string
	 */
	private function addDoNotEditHint($initialString) {
		$editedString = str_replace(
			'<?php',
			"<?php\n//MKTOOLS HINWEIS:\n//DIESE DATEI WURDE AUTOMATISCH " .
			"GENERIERT UND SOLLTE DAHER NICHT BEARBEITET WERDEN.\n//" .
			"BITTE NUR DAS TEMPLATE FÜR DIE KONFIG BEARBEITEN.",
			$initialString
		);

		if($editedString == $initialString) {
			$editedString = str_replace(
				'<?',
				"<?\n//MKTOOLS HINWEIS:\n//DIESE DATEI WURDE AUTOMATISCH " .
				"GENERIERT UND SOLLTE DAHER NICHT BEARBEITET WERDEN.\n//" .
				"BITTE NUR DAS TEMPLATE FÜR DIE KONFIG BEARBEITEN.",
				$initialString
			);
		}

		return $editedString;
	}

	/**
	 * Anpassung realurl
	 */
	public function registerXclass() {
		if (!tx_rnbase_util_Extensions::isLoaded('realurl')) {
			return ;
		}

		// kann schon durch autoloading da sein aber auch eine andere Klasse sein
		// als die von mktools
		if (class_exists('ux_tx_realurl')) {
			$reflector = new ReflectionClass("ux_tx_realurl");
			$rPath = realpath($reflector->getFileName());
			$tPath =  realpath(
				tx_rnbase_util_Extensions::extPath('mktools', '/xclasses/class.ux_tx_realurl.php')
			);
			// notice werfen wenn bisherige XClass nicht die von mktools ist
			if (strpos($rPath, $tPath) === FALSE) {
				throw new LogicException(
					'There allready exists an ux_tx_realurl XCLASS!' .
					' Remove the other XCLASS or the deacivate the realurl' .
					' handling in mktools',
					intval(ERROR_CODE_MKTOOLS  . '130')
				);
			}
			unset($reflector, $rPath, $tPath);
		} else {
			require_once tx_rnbase_util_Extensions::extPath('mktools', 'xclasses/class.ux_tx_realurl.php');
		}

		if (tx_rnbase_util_TYPO3::isTYPO60OrHigher()) {
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['tx_realurl'] = array(
				'className' => 'ux_tx_realurl'
			);
		} else {
			$GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl.php']
				= tx_rnbase_util_Extensions::extPath('mktools', 'xclasses/class.ux_tx_realurl.php');
		}
	}
}
