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
 *  Module       : coreBOS Statistical Manager: convert statistics to modules
 *  Version      : 1.0
 *  Author       : JPL TSolucio, S. L.
 *************************************************************************************************/

/*****  Documentation *****
 * Format expected in the queue
 * JSON object
 *  stat: string, indicates the type of statistical information. accepted values are:
 *        website, webform, social, searchengine, call, webinar, download
 *  access_date: date of event
 *  access_time: time of event
 *  url: URL of event
 *  web: web/domain of event
 *  access_end: only for call  end of call   will be calculated it totaltime is given
 *  totaltime: only for call  duration of call  will be calculated if access_end is given
 *  pbxid: only for call. will be matched for phone numbres on Accounts, Contacts and Leads and saved in relid if found and relid is empty
 *  websiteid: will be matched for websiteid on Accounts, Contacts and Leads and saved in relid if found and relid is empty
 *  relid: Account, Contact or Lead
 *  extcmpid: external campaign identifier, will be searched for on campaigns and saved in cmpid if found and cmpid es empty
 *  cmpid: Campaign
 *  organic: only for search engine: true if organic, false if paid
 *  socialnetwork: only for social: name of social network
 *  socialaction: only for social
 *  submit: only for webform: true if form is submitted, false if only viewed
 *  webinarprovidor: only for webinar: name of webinar providor
 *  attended: only for webinar: true if attended, false if only registered
 *  uniquevisit: only for website: true if it is a first and unique visits, false otherwise
 *  documentid: crm id of the document marked as media download
 *  assignedto: user id to assign new entities to, will be set to admin user if none given
 *  --custom fields--: any list of custom field may be sent in and will be saved
 */
$Vtiger_Utils_Log = FALSE;
include_once('vtlib/Vtiger/Module.php');
// Retrieve user information
$user = CRMEntity::getInstance('Users');
$user->id=$user->getActiveAdminId();
$user->retrieve_entity_info($user->id, 'Users');
$_REQUEST['assigntype'] = 'U';
$cbsqueue = $adb->query('select * from vtiger_cbsqueue');
while ($st = $adb->fetch_array($cbsqueue)) {
	$stat = json_decode($st['cbsstat'],TRUE);
	if (!is_null($stat)) {
		switch ($stat['stat']) {
			case 'website':
				include_once 'modules/cbsWebsiteAccess/cbsWebsiteAccess.php';
				$focus = new cbsWebsiteAccess();
				$mod = 'cbsWebsiteAccess';
				break;
			case 'webform':
				include_once 'modules/cbsWebFormAccess/cbsWebFormAccess.php';
				$focus = new cbsWebFormAccess();
				$mod = 'cbsWebFormAccess';
				break;
			case 'social':
				include_once 'modules/cbsSocialAccess/cbsSocialAccess.php';
				$focus = new cbsSocialAccess();
				$mod = 'cbsSocialAccess';
				break;
			case 'searchengine':
				include_once 'modules/cbsSEAccess/cbsSEAccess.php';
				$focus = new cbsSEAccess();
				$mod = 'cbsSEAccess';
				break;
			case 'call':
				include_once 'modules/cbsConferenceCalls/cbsConferenceCalls.php';
				$focus = new cbsConferenceCalls();
				$mod = 'cbsConferenceCalls';
				break;
			case 'webinar':
				include_once 'modules/cbsWebinarAccess/cbsWebinarAccess.php';
				$focus = new cbsWebinarAccess();
				$mod = 'cbsWebinarAccess';
				break;
			case 'download':
				$drs = $adb->pquery('select mediadownload from vtiger_notes where notesid=?',array($stat['documentid']));
				if ($drs and $adb->num_rows($drs)==1) {
					$md = $adb->query_result($drs,0,0);
					$md =empty($md) ? 1 : $md + 1;
					$adb->pquery('update vtiger_notes set mediadownload=? where notesid=?',array($md,$stat['documentid']));
				}
				continue;
				break;
			default:
				continue;
				break;
		}
		$focus->mode = '';
		$focus->column_fields['assigned_user_id'] = $user->id;
		foreach ($stat as $key => $value) {
			if ($key=='stat') continue;
			if ($key=='relid' and empty($stat['relid'])) {
				if ($stat['stat']=='call' and !empty($stat['pbxid'])) {
					$crs = $adb->pquery('select crmid
						from vtiger_account
						inner join vtiger_crmentity on crmid=accountid
						where deleted=0 and (phone=? or otherphone=?) limit 1',array($stat['pbxid'],$stat['pbxid']));
					if ($crs and $adb->num_rows($crs)==1) {
						$focus->column_fields['relid'] = $adb->query_result($crs,0,0);
					} else {
						$crs = $adb->pquery('select crmid
							from vtiger_contactdetails
							inner join vtiger_crmentity on crmid=contactid
							where deleted=0 and (phone=? or mobile=?) limit 1',array($stat['pbxid'],$stat['pbxid']));
						if ($crs and $adb->num_rows($crs)==1) {
							$focus->column_fields['relid'] = $adb->query_result($crs,0,0);
						} else {
							$crs = $adb->pquery('select crmid
								from vtiger_leadaddress
								inner join vtiger_crmentity on crmid=leadaddressid
								where deleted=0 and (phone=? or mobile=?) limit 1',array($stat['pbxid'],$stat['pbxid']));
							if ($crs and $adb->num_rows($crs)==1) {
								$focus->column_fields['relid'] = $adb->query_result($crs,0,0);
							}
						}
					}
				} elseif (!empty($stat['websiteid'])) {
					$crs = $adb->pquery('select crmid
						from vtiger_account
						inner join vtiger_crmentity on crmid=accountid
						where deleted=0 and websiteid=? limit 1',array($stat['websiteid']));
					if ($crs and $adb->num_rows($crs)==1) {
						$focus->column_fields['relid'] = $adb->query_result($crs,0,0);
					} else {
						$crs = $adb->pquery('select crmid
							from vtiger_contactdetails
							inner join vtiger_crmentity on crmid=contactid
							where deleted=0 and websiteid=? limit 1',array($stat['websiteid']));
						if ($crs and $adb->num_rows($crs)==1) {
							$focus->column_fields['relid'] = $adb->query_result($crs,0,0);
						} else {
							$crs = $adb->pquery('select crmid
								from vtiger_leaddetails
								inner join vtiger_crmentity on crmid=leadid
								where deleted=0 and websiteid=? limit 1',array($stat['websiteid']));
							if ($crs and $adb->num_rows($crs)==1) {
								$focus->column_fields['relid'] = $adb->query_result($crs,0,0);
							}
						}
					}
				}
				continue;
			}
			if ($key=='cmpid' and empty($stat['cmpid']) and !empty($stat['extcmpid'])) {
				$crs = $adb->pquery('select campaignid
					from vtiger_campaigns
					inner join vtiger_crmentity on crmid=campaignid 
					where deleted=0 and extcmpid=? limit 1',array($stat['extcmpid']));
				if ($crs and $adb->num_rows($crs)==1) {
					$focus->column_fields['cmpid'] = $adb->query_result($crs,0,0);
				}
				continue;
			}
			if (empty($value)) continue;
			$field = $key;
			if ($key=='assignedto') $field = 'assigned_user_id';
			if ($key=='url') $field = 'access_url';
			if ($key=='socialnetwork'or $key=='webinarprovidor') $field = 'srvprovidor';
			if ($key=='web') $field = 'access_web';
			if ($key=='uniquevisit') $field = 'sitevisit';
			$focus->column_fields[$field] = $value;
		}
		$focus->save($mod);
		unset($focus);
	}
	$adb->pquery('DELETE FROM cbsstat WHERE cbstatid = ?',array($st['cbstatid']));
}
?>