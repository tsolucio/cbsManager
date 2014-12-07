<?php
/*************************************************************************************************
 * Copyright 2014 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS customizations.
 * You can copy, adapt and distribute the work under the "Attribution-NonCommercial-ShareAlike"
 * Vizsage Public License (the "License"). You may not use this file except in compliance with the
 * License. Roughly speaking, non-commercial users may share and modify this code, but must give credit
 * and share improvements. However, for proper details please read the full License, available at
 * http://vizsage.com/license/Vizsage-License-BY-NC-SA.html and the handy reference for understanding
 * the full license at http://vizsage.com/license/Vizsage-Deed-BY-NC-SA.html. Unless required by
 * applicable law or agreed to in writing, any software distributed under the License is distributed
 * on an  "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and limitations under the
 * License terms of Creative Commons Attribution-NonCommercial-ShareAlike 3.0 (the License).
 *************************************************************************************************
 *  Module       : coreBOS Statistical Manager: queue statistics
 *  Version      : 1.0
 *  Author       : JPL TSolucio, S. L.
 *************************************************************************************************/

/*****  Documentation *****
 * Must be called with two GET parameters
 *  accesskey: created in the application by the cbsManager module
 *       this must also correspond with the IP and domain authorized in that record.
 *       The IP MUST be preset always
 *  stat: a JSON object with the statistical information to save
 */

$accesskey = trim(filter_input(INPUT_GET, 'accesskey',FILTER_SANITIZE_MAGIC_QUOTES));
if (!empty($accesskey)) {
	/******
	 * Default database connection information
	 * THIS IS NOT REQUIRED IF EXECUTING FROM INSIDE coreBOS
	 ******/
	$dbconfig['db_server'] = 'your_server';
	$dbconfig['db_port'] = ':3306';
	$dbconfig['db_username'] = 'your_user';
	$dbconfig['db_password'] = 'your_password';
	$dbconfig['db_name'] = 'your_database';
	/****************************************************************/
	if (file_exists('config.inc.php')) {
		include_once 'config.inc.php';
	}
	if (file_exists('../../config.inc.php')) {
		include_once '../../config.inc.php';
	}
	$uri = 'mysql:host='.$dbconfig['db_server'].';port='.$dbconfig['db_port'].';dbname='.$dbconfig['db_name'];
	$dbh = new PDO($uri, $dbconfig['db_username'], $dbconfig['db_password'], array( PDO::ATTR_PERSISTENT => false));
	$sql = "SELECT authfrom
			FROM vtiger_cbsmanager
			INNER JOIN vtiger_crmentity on crmid=cbsmanagerid
			WHERE deleted=0 and active='1' and accesskey=:accesskey limit 1";
	$sth = $dbh->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
	$ok = $sth->execute(array(':accesskey' => $accesskey));
	if ($ok) {
		$ath = $sth->fetchColumn(0);
		if ($ath) {
			$sth->closeCursor();
			$acceptedDomains = explode(',',$ath);
			$referer=get_domain($_SERVER['HTTP_REFERER']);
			if(($referer && in_array($referer,$acceptedDomains) && in_array($_SERVER['REMOTE_ADDR'],$acceptedDomains)) || in_array($_SERVER['REMOTE_ADDR'],$acceptedDomains)) {
				$stat = trim(filter_input(INPUT_GET, 'stat'));
				$sql = 'INSERT INTO vtiger_cbsqueue VALUES(:stat)';
				$sth = $dbh->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
				$ok = $sth->execute(array(':stat' => $stat));
			}
		}
	}
}

// Helper functions

function get_domain($url) {
  $pieces = parse_url($url);
  $domain = isset($pieces['host']) ? $pieces['host'] : '';
  if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
     return $regs['domain'];
  }
  return false;
}
?>