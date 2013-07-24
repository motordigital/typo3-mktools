<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Hannes Bochmann <hannes.bochmann@das-medienkombinat.de>
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
require_once(t3lib_extMgm::extPath('rn_base') . 'class.tx_rnbase.php');
tx_rnbase::load('tx_rnbase_util_DB');
tx_rnbase::load('tx_mktools_util_miscTools');

/**
 * @author Hannes Bochmann
 * @package TYPO3
 * @subpackage tx_mktools
 */
class tx_mktools_util_RealUrl {
	
	/**
	 * @return array[tx_mktools_model_Pages]
	 */
	public static function getPagesWithFixedPostVarType() {
		$options = array(
			'enablefieldsfe'	=> 1,
			'wrapperclass'		=> 'tx_mktools_model_Pages',
			'where'				=> 'tx_mktools_fixedpostvartype > 0'
		);
		
		return static::selectPagesByOptions($options);
	}
	

	/**
	 * @param int $modificationTimeStamp
	 * 
	 * @return boolean
	 */
	public static function areTherePagesWithFixedPostVarTypeModifiedLaterThan(
		$modificationTimeStamp
	) {
		$options = array(
			'enablefieldsfe'	=> 	1,
			'where'				=> 	'tx_mktools_fixedpostvartype > 0 AND tstamp > ' . 
									$modificationTimeStamp
		);
		
		$result = static::selectPagesByOptions($options, 'COUNT(uid) AS uid_count');
		
		return (isset($result[0]['uid_count'])) ? (boolean) $result[0]['uid_count'] : false;
	}
	
	/**
	 * @param array $options
	 * @param string $what
	 * 
	 * @return array
	 */
	private static function selectPagesByOptions(array $options, $what = '*') {
		$dbUtil = static::getDbUtil();
		
		return $dbUtil::doSelect(
			$what, 'pages', $options
		);
	}
	
	/**
	 * @return tx_rnbase_util_DB
	 */
	protected static function getDbUtil() {
		return tx_rnbase_util_DB;
	}
	
	/**
	 * @return boolean
	 */
	public static function needsRealUrlConfigurationToBeGenerated() { 
		$realUrlConfigurationFile = 
			tx_mktools_util_miscTools::getRealUrlConfigurationFile();	
			
		$realUrlConfigurationLastModified = 0;
		if(file_exists($realUrlConfigurationFile)) {
			$realUrlConfigurationLastModified = filemtime($realUrlConfigurationFile);
		}
		
		return static::areTherePagesWithFixedPostVarTypeModifiedLaterThan(
			$realUrlConfigurationLastModified
		);
	}
	
	/**
	 * @param array[tx_mktools_model_Pages] $pages
	 * 
	 * @return boolean
	 */
	public static function generateSerializedRealUrlConfigurationFileByPages(array $pages) {
		$configurationFileWritten = false;
		
		$fixedPostVarPageStrings = array(); 
		foreach ($pages as $page) {
			if($fixedPostVarType = $page->getFixedPostVarType()) {
				$fixedPostVarPageStrings[] = 	$page->getUid() . " => '" . 
												$fixedPostVarType->getIdentifier() . "'";
			}
		}
		
		$realUrlConfigurationTemplate = file_get_contents(
			tx_mktools_util_miscTools::getRealUrlConfigurationTemplate()
		);
		if(
			!empty($fixedPostVarPageStrings) &&
			(strlen($realUrlConfigurationTemplate) > 0) && 
			($realUrlConfigurationFile = tx_mktools_util_miscTools::getRealUrlConfigurationFile())
		) {
			$fixedPostVarPageString = join(",\n", $fixedPostVarPageStrings);
			$realUrlConfigurationFileContent = str_replace(
				'###FIXEDPOSTVARPAGES###', 
				$fixedPostVarPageString, 
				$realUrlConfigurationTemplate
			);
			$realUrlConfigurationFileContent = self::addDoNotEditHint($realUrlConfigurationFileContent);
			
			file_put_contents(
				$realUrlConfigurationFile, $realUrlConfigurationFileContent
			);
			
			include $realUrlConfigurationFile;
			$serializedContent = '<?php
$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'realurl\'] = unserialize(\'' . serialize($TYPO3_CONF_VARS['EXTCONF']['realurl']) . '\');';
			$serializedContent = self::addDoNotEditHint($serializedContent);
			
			$configurationFileWritten = file_put_contents(
				$realUrlConfigurationFile, $serializedContent
			);
		}
		
		return (boolean) $configurationFileWritten;
	}
	
	/**
	 * @param string $initialString
	 * 
	 * @return string
	 */
	private static function addDoNotEditHint($initialString) {
		$editedString = str_replace(
			'<?php', 
			"<?php\n//MKTOOLS HINWEIS:/n//DIESE DATEI WURDE AUTOMATISCH GENERIERT UND SOLLTE DAHER NICHT BEARBEITET WERDEN.\n//BITTE NUR DAS TEMPLATE FÜR DIE KONFIG BEARBEITEN.", 
			$initialString
		);
		
		if($editedString == $initialString) {
			$editedString = str_replace(
				'<?', 
				"<?\n//MKTOOLS HINWEIS:/n//DIESE DATEI WURDE AUTOMATISCH GENERIERT UND SOLLTE DAHER NICHT BEARBEITET WERDEN.\n//BITTE NUR DAS TEMPLATE FÜR DIE KONFIG BEARBEITEN.", 
				$initialString
			);
		}
		
		return $editedString;
	}
}