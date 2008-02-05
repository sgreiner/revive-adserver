<?php

/*
+---------------------------------------------------------------------------+
| OpenX v${RELEASE_MAJOR_MINOR}                                                              |
| ============                                                              |
|                                                                           |
| Copyright (c) 2003-2008 Openads Limited                                   |
| For contact details, see: http://www.openx.org/                           |
|                                                                           |
| Copyright (c) 2000-2003 the phpAdsNew developers                          |
| For contact details, see: http://www.phpadsnew.com/                       |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

// Require the initialisation file
require_once '../../init.php';

// Required files
require_once MAX_PATH . '/www/admin/config.php';
require_once MAX_PATH . '/www/admin/lib-statistics.inc.php';
require_once MAX_PATH . '/lib/max/other/common.php';
require_once MAX_PATH . '/lib/max/Admin_DA.php';
require_once MAX_PATH . '/lib/max/other/html.php';
require_once MAX_PATH . '/lib/max/other/stats.php';
require_once 'Pager/Pager.php';
require_once MAX_PATH . '/lib/pear/Date.php';

// Security check
OA_Permission::enforceAccount(OA_ACCOUNT_ADMIN, OA_ACCOUNT_MANAGER, OA_ACCOUNT_ADVERTISER, OA_ACCOUNT_TRAFFICKER);

// Get input variables
$pref = $GLOBALS['_MAX']['PREF'];
$hideinactive   = MAX_getStoredValue('hideinactive', ($pref['ui_hide_inactive'] == true));
$listorder      = MAX_getStoredValue('listorder', 'date_time');
$orderdirection = MAX_getStoredValue('orderdirection', 'up');
$aNodes         = MAX_getStoredArray('nodes', array());
$editStatuses   = MAX_getStoredValue('editStatuses', false);
$day            = MAX_getStoredValue('day', null);
$howLong        = MAX_getStoredValue('howLong', 'd');
$hour           = MAX_getStoredValue('hour', null);
$setPerPage     = MAX_getStoredValue('setPerPage', 15);
$pageID         = MAX_getStoredValue('pageID', 1);

if (!empty($day)) {
    // Reset period
    $period_preset = '';
    // Always refresh howLong and hour
    $howLong = MAX_getValue('howLong', 'd');
    $hour    = MAX_getValue('hour');
} else {
    $period_preset  = MAX_getStoredValue('period_preset', 'today');
    $period_start   = MAX_getStoredValue('period_start', date('Y-m-d'));
    $period_end     = MAX_getStoredValue('period_end', date('Y-m-d'));
}

if (is_numeric($hour) && $hour < 10 && strlen($hour) != 2) {
    $hour = '0' . $hour;
}

$expand         = MAX_getValue('expand', '');
$collapse       = MAX_getValue('collapse');

$clientId       = MAX_getValue('clientid');
$campaignId     = MAX_getValue('campaignid');
$bannerId       = MAX_getValue('bannerid');

$affiliateId    = MAX_getValue('affiliateid');
$zoneId         = MAX_getValue('zoneid');

if (OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
    $clientId = $clientid = OA_Permission::getEntityId();
    // Rebuild navigation
    MMM_buildNavigation();
} elseif (OA_Permission::isAccount(OA_ACCOUNT_TRAFFICKER)) {
    $affiliateId = $affiliateid = OA_Permission::getEntityId();
    // Rebuild navigation
    MMM_buildNavigation();
}

// Build $addUrl variable which will be added to any required link on this page, eg: expand, collapse, editStatuses
$entityIds = array(
    'entity'      => 'conversions',
    'clientid'    => $clientid,
    'campaignid'  => $campaignId,
    'bannerid'    => $bannerId,
    'affiliateid' => $affiliateId,
    'zoneid'      => $zoneId,
    'setPerPage'  => $setPerPage,
    'pageID'      => $pageID
);
$addUrl = "entity=conversions&clientid=$clientId&campaignid=$campaignId&bannerid=$bannerId&affiliateid=$affiliateId&zoneid=$zoneId&setPerPage=$setPerPage&pageID=$pageID";

if (!empty($day)) {
    $entityIds += array(
        'day' => $day,
        'hour' => $hour,
        'howLong' => $howLong
    );
    $addUrl .= "&day={$day}&hour={$hour}&howLong={$howLong}";
} else {
    $entityIds += array(
        'period_preset' => $period_preset,
        'period_start' => $period_start,
        'period_end' => $period_end,
    );
    $addUrl .= "&period_preset={$period_preset}&period_start={$period_start}&period_end={$period_end}";
}
// Adjust which nodes are opened closed...
MAX_adjustNodes($aNodes, $expand, $collapse);

if (!OA_Permission::isAccount(OA_ACCOUNT_ADMIN, OA_ACCOUNT_MANAGER)) {
    // editing statuses is allowed only for admin and agency
    $editStatuses = false;
} else {
    if($editStatuses) {
        phpAds_PageShortcut($strShortcutShowStatuses, 'stats.php?entity=conversions&editStatuses=0&'.$addUrl, 'images/icon-zoom.gif');
    } else {
        phpAds_PageShortcut($strShortcutEditStatuses, 'stats.php?entity=conversions&editStatuses=1&'.$addUrl, 'images/icon-edit.gif');
    }
}

// @todo: hack - get edit status working when expading/collapsing
if ($editStatuses) {
    $entityIds['editStatuses'] = 1;
    $addUrl .= '&editStatuses=1';
}

// Display navigation
if(OA_Permission::isAccount(OA_ACCOUNT_TRAFFICKER)) {
    // Navigation for publisher
    $conf = &$GLOBALS['_MAX']['CONF'];
    $conf['logging']['adRequests'] = false;
    $affiliateid = OA_Permission::getEntityId();

    phpAds_PageHeader("1.1");
    echo '<br><br>';
} elseif(OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
    // Navigation for advertiser
    $clientid = OA_Permission::getEntityId();

    phpAds_PageHeader("1.1");
    echo '<br><br>';
} else {
    // Navigation for admin and agency

    phpAds_PageHeader("2.1");
    echo '<br><br>';
}
// Initialise some parameters
$pageName = basename($_SERVER['PHP_SELF']);
$tabindex = 1;
$advertisersHidden = 0;

// Display date filter form
if(empty($day)) {
    $aDates = MAX_getDatesByPeriod($period_preset, $period_start, $period_end);
} else {
    $aDates = array();
    $oDayDate = new Date();
    $oDayDate->setDate($day, DATE_FORMAT_TIMESTAMP);
    if(!empty($hour)) {
        // If hour is set build day date including hour
        $aDates['day_hour'] = $oDayDate->format('%Y-%m-%d').' '.$hour;
    } else {
        // Build month, day, day_begin and day_end dependends on $howLong
        switch($howLong) {
            case 'm':
                $aDates['month'] = $oDayDate->format('%Y-%m');
                break;
            case 'w':
                $aDates['day_begin'] = $oDayDate->format('%Y-%m-%d');
                $oDayDate->addSeconds(60*60*24*7); // Add 7 days
                $aDates['day_end'] = $oDayDate->format('%Y-%m-%d');
                break;
            case 'd':
            default:
                $aDates['day'] = $oDayDate->format('%Y-%m-%d');
                break;
        }
    }
}

$hiddenValues = array(
    'entity'   => 'conversions',
    'clientid' => $clientId,
    'campaignid' => $campaignId,
    'bannerid' => $bannerId,
    'affiliateid' => $affiliateId,
    'zoneid' => $zoneId,
);
if(!empty($period_preset)) {
    MAX_displayDateSelectionForm($period_preset, $period_start, $period_end, $pageName, $tabindex, $hiddenValues);
} else {
    $comma = '';
    foreach($aDates as $dateValue) {
        echo $comma.$dateValue;
        $comma = ' - ';
    }
}

phpAds_ShowBreak();

$aParams = array();
if (!OA_Permission::isAccount(OA_ACCOUNT_ADMIN)) {
    $aParams['agency_id'] = OA_Permission::getAgencyId();
}

$aParams['clientid']    = $clientId;
$aParams['campaignid']  = $campaignId;
$aParams['bannerid']    = $bannerId;
$aZonesIds = null; // Admin_DA class expects null if no zones to be used
if (empty($zoneId) && !empty($affiliateId)) {
    $aZonesIds = Admin_DA::fromCache('getZonesIdsByAffiliateId', $affiliateId);
}
if(!empty($zoneId)) {
    $aZonesIds = array($zoneId);
}
$aParams['zonesIds'] = $aZonesIds;



// Get conversions...



$aParams['perPage'] = '999999';
$aConversions = Admin_DA::fromCache('getConversions', $aParams + $aDates);


$aParams['totalItems'] = count($aConversions);
$aParams['perPage'] = MAX_getStoredValue('setPerPage', 15);

if (!isset($pageID) || $pageID == 1) {
    $aParams['startRecord'] = 0;
} else {
    $aParams['startRecord'] = (MAX_getStoredValue('pageID', 1) * $aParams['perPage']) - $aParams['perPage'];
}



$aConversions = Admin_DA::fromCache('getConversions', $aParams + $aDates);


$aParams['perPage'] = MAX_getStoredValue('setPerPage', 15);
//$aParams['startRecord'] = $_REQUEST['page'];

$pager = & Pager::factory($aParams);
$per_page = $pager->_perPage;
$pager->history = $pager->getPageData();
$pager->pagerLinks = $pager->getLinks();

$pager->pagerLinks = $pager->pagerLinks['all'];
$pager->pagerSelect = preg_replace('/(<select.*?)(>)/i', '$1 onchange="this.form.submit()"$2', $pager->getPerPageSelectBox(15, 120, 15));

// Build the conversions array
if (!empty($aConversions)) {

    if($editStatuses) {
        echo "<form action='connections-modify.php' name='connectionsmodify' id='connectionsmodify' method='POST'>"."\n";
        echo "<input type='hidden' name='clientid' value='$clientId'>"."\n";
        echo "<input type='hidden' name='campaignid' value='$campaignId'>"."\n";
        echo "<input type='hidden' name='bannerid' value='$bannerId'>"."\n";
        echo "<input type='hidden' name='affiliateid' value='$affiliateId'>"."\n";
        echo "<input type='hidden' name='zoneid' value='$zoneId'>"."\n";
        echo "<input type='hidden' name='day' value='$day'>"."\n";
        echo "<input type='hidden' name='hour' value='$hour'>"."\n";
        echo "<input type='hidden' name='howLong' value='$howLong'>"."\n";
        echo "<input type='hidden' name='period_preset' value='$period_preset'>"."\n";
        if ($period_preset == 'specific') {
            echo "<input type='hidden' name='period_start' value='$period_start'>"."\n";
            echo "<input type='hidden' name='period_end' value='$period_end'>"."\n";
        }
        echo "<input type='hidden' name='returnurl' value='stats.php'>"."\n";
        echo "<input type='hidden' name='entity' value='conversions'>"."\n";
    }

    echo "
        <br /><br />
        <table border='0' width='100%' cellpadding='0' cellspacing='0'>";

    $column1 = _getHtmlHeaderColumn($GLOBALS['strDateTime'], 'date_time', $pageName, $entityIds, $listorder, $orderdirection);
    $column2 = _getHtmlHeaderColumn($GLOBALS['strStatus'], 'connection_status', $pageName, $entityIds, $listorder, $orderdirection);
    $column3 = _getHtmlHeaderColumn($GLOBALS['strTrackerID'], 'tracker_id', $pageName, $entityIds, $listorder, $orderdirection);
    $column4 = _getHtmlHeaderColumn($GLOBALS['strTrackerName'], 'trackername', $pageName, $entityIds, $listorder, $orderdirection);
    $column5 = _getHtmlHeaderColumn($GLOBALS['strCampaignID'], 'campaignid', $pageName, $entityIds, $listorder, $orderdirection);
    $column6 = _getHtmlHeaderColumn($GLOBALS['strCampaignName'], 'campaignname', $pageName, $entityIds, $listorder, $orderdirection);

    echo "
        <tr height='1'>
            <td><img src='images/spacer.gif' width='150' height='1' border='0' alt='' title=''></td>
            <td><img src='images/spacer.gif' width='80' height='1' border='0' alt='' title=''></td>
            <td><img src='images/spacer.gif' width='60' height='1' border='0' alt='' title=''></td>
            <td><img src='images/spacer.gif' width='80' height='1' border='0' alt='' title=''></td>
            <td><img src='images/spacer.gif' width='60' height='1' border='0' alt='' title=''></td>
            <td><img src='images/spacer.gif' width='80' height='1' border='0' alt='' title=''></td>
        </tr>
        <tr height='25'>
            <td width='150' style='padding-left: 16px'>&nbsp;&nbsp;$column1</td>
            <td align='center' style='padding: 0 4px'>$column2</td>
            <td align='$phpAds_TextAlignLeft' style='padding: 0 4px'>$column3</td>
            <td align='$phpAds_TextAlignLeft' style='padding: 0 4px'>$column4</td>
            <td align='$phpAds_TextAlignLeft' style='padding: 0 4px'>$column5</td>
            <td align='$phpAds_TextAlignLeft' style='padding: 0 4px'>$column6</td>
        </tr>
        <tr height='1'><td colspan='6' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";

    // Variable to determine if the row should be grey or white...
    $i=0;

    $statusesColors = array(
        MAX_CONNECTION_STATUS_IGNORE      => 'grey',
        MAX_CONNECTION_STATUS_PENDING     => 'darkblue',
        MAX_CONNECTION_STATUS_ONHOLD      => 'blue',
        MAX_CONNECTION_STATUS_APPROVED    => 'green',
        MAX_CONNECTION_STATUS_DISAPPROVED => 'red',
        MAX_CONNECTION_STATUS_DUPLICATE   => 'grey',
    );

    $totalRequests = 0;
    $totalViews = 0;
    $totalClicks = 0;
    $totalConversions = 0;

    // Loop through advertisers
    MAX_sortArray($aConversions, ($listorder == 'id' ? 'date_time' : $listorder), $orderdirection == 'up');
    foreach($aConversions as $conversionId => $conversion) {
        $conversionExpanded = MAX_isExpanded($conversionId, $expand, $aNodes, 'a');

            $bgcolor = ($i++ % 2 == 0) ? " bgcolor='#F6F6F6'" : '';

            $connectionStatus = $GLOBALS['_MAX']['STATUSES'][$conversion['connection_status']];
            $translatedStatus = $GLOBALS[$connectionStatus];

            echo "
        <tr height='25'$bgcolor>
            <td>";
            if ($conversionExpanded) {
                echo "&nbsp;<a href='$pageName?collapse=a$conversionId&$addUrl'><img src='images/triangle-d.gif' align='absmiddle' border='0'></a>&nbsp;";
            } else {
                echo "&nbsp;<a href='$pageName?expand=a$conversionId&$addUrl'><img src='images/$phpAds_TextDirection/triangle-l.gif' align='absmiddle' border='0'></a>&nbsp;";
            }

            $aConversionStatuses = array(
                MAX_CONNECTION_STATUS_IGNORE,
                MAX_CONNECTION_STATUS_PENDING,
                MAX_CONNECTION_STATUS_ONHOLD,
                MAX_CONNECTION_STATUS_APPROVED,
                MAX_CONNECTION_STATUS_DISAPPROVED,
                MAX_CONNECTION_STATUS_DUPLICATE,
            );

            echo "{$conversion['date_time']}</td>";
            if($editStatuses) {
                echo "<td align='center' style='padding: 0 4px'><nobr>";
                if (OA_Permission::isAccount(OA_ACCOUNT_MANAGER)) {
                    // Ignore(1) is always disabled for agencies
                    $disabledStatuses = array(MAX_CONNECTION_STATUS_IGNORE);
                    // final statuses: Ignore(1), Approved(4) and Disapproved(5)
                    $finalAgencyStatuses = array(
                        MAX_CONNECTION_STATUS_IGNORE,
                        MAX_CONNECTION_STATUS_APPROVED,
                        MAX_CONNECTION_STATUS_DISAPPROVED
                    );
                    if(in_array($conversion['connection_status'], $finalAgencyStatuses)) {
                        // Do not allow to modify status, disable others statuses
                        $disabledStatuses = array_diff($aConversionStatuses, array($conversion['connection_status']));
                    }
                    if($conversion['connection_status'] == MAX_CONNECTION_STATUS_PENDING) {
                        // Pending - could change to: Pending(2), OnHold (3), Approved (4), Disapproved (5)
                        $disabledStatuses = array_diff($aConversionStatuses,
                            array(
                                MAX_CONNECTION_STATUS_PENDING,
                                MAX_CONNECTION_STATUS_ONHOLD,
                                MAX_CONNECTION_STATUS_APPROVED,
                                MAX_CONNECTION_STATUS_DISAPPROVED)
                        );
                    }
                    if($conversion['connection_status'] == MAX_CONNECTION_STATUS_ONHOLD) {
                        // OnHold - could change to: OnHold(3), Approved (4), Disapproved (5)
                        $disabledStatuses = array_diff($aConversionStatuses,
                            array(
                                MAX_CONNECTION_STATUS_ONHOLD,
                                MAX_CONNECTION_STATUS_APPROVED,
                                MAX_CONNECTION_STATUS_DISAPPROVED
                            )
                        );
                    }
                    foreach($GLOBALS['_MAX']['STATUSES'] as $statusId => $statusStr) {
                        $disabled = '';
                        $color = '';
                        if(in_array($statusId, $disabledStatuses)) {
                            $color = 'grey';
                            $disabled = ' disabled';
                        }
                        echo "<label style='color: $color;'>&nbsp;<input type='radio' name='statusIds[$conversionId]' value='$statusId' ".($conversion['connection_status']==$statusId?' checked':'')." tabindex='".($tabindex++)."' $disabled>{$GLOBALS[$statusStr]}</label>";
                    }
                } elseif(OA_Permission::isAccount(OA_ACCOUNT_ADMIN)) {
                    // If admin - everything
                    foreach($GLOBALS['_MAX']['STATUSES'] as $statusId => $statusStr) {
                        echo "&nbsp;<label><input type='radio' name='statusIds[$conversionId]' value='$statusId' ".($conversion['connection_status']==$statusId?' checked':'')." tabindex='".($tabindex++)."'>{$GLOBALS[$statusStr]}</label>";
                    }
                }
                echo "</nobr></td>";
            } else {
                echo "<td align='center' style='padding: 0 4px'><span style='color: {$statusesColors[$conversion['connection_status']]}'>{$translatedStatus}</span></td>";
            }
            echo "<td align='$phpAds_TextAlignLeft' style='padding: 0 4px'>{$conversion['tracker_id']}</td>
            <td align='$phpAds_TextAlignLeft' style='padding: 0 4px'>{$conversion['trackername']}</td>
            <td align='$phpAds_TextAlignLeft' style='padding: 0 4px'>{$conversion['campaignid']}</td>
            <td align='$phpAds_TextAlignLeft' style='padding: 0 4px'>{$conversion['campaignname']}</td>
        </tr>";

        if ($conversionExpanded) {
            $aConVariables = Admin_DA::fromCache('getConnectionVariables', $conversionId);

            echo "
            <tr height='1'>
                <td$bgcolor><img src='images/spacer.gif' width='1' height='1'></td>
                <td colspan='5' bgcolor='#888888'><img src='images/break-l.gif' height='1' width='100%'></td>
            </tr>";

            switch ($conversion['connection_action']) {
                case MAX_CONNECTION_AD_CLICK:   $action = 'Click'; break;
                case MAX_CONNECTION_AD_ARRIVAL: $action = 'Arrival'; break;
                case MAX_CONNECTION_MANUAL:     $action = 'Manual'; break;
                default:                        $action = 'View'; break;
            }

            $connectionType = $GLOBALS[$GLOBALS['_MAX']['CONN_TYPES'][$conversion['connection_type']]];

            $eventDateStamp = strtotime($conversion['date_time']);

            $secondsLeft = $eventDateStamp - strtotime($conversion['connection_date_time']);

            $days = intval($secondsLeft / 86400);  // 86400 seconds in a day
            $partDay = $secondsLeft - ($days * 86400);
            $hours = intval($partDay / 3600);  // 3600 seconds in an hour
            $partHour = $partDay - ($hours * 3600);
            $minutes = intval($partHour / 60);  // 60 seconds in a minute
            $seconds = $partHour - ($minutes * 60);

            $windowDelay = $days."d ".$hours."h ".$minutes."m ".$seconds."s";

            echo "
            <tr height='25'$bgcolor>
                <td></td>
                <td colspan='5'>
                    <table width='100%' border='0' cellspacing='' cellpadding='4'>
                        <tr valign='top'>
                            <td width='40%'>
                                <table border='0' cellspacing='0' cellpadding='0'>
                                    <tr><th scope='row' style='text-align: $phpAds_TextAlignLeft'>IP Address:</th><td style='padding-left: 8px'>{$conversion['tracker_ip_address']}</td></tr>
                                    <tr><th scope='row' style='text-align: $phpAds_TextAlignLeft'>{$GLOBALS['strCountry']}:</th><td style='padding-left: 8px'>{$conversion['tracker_country']}</td></tr>
                                    <tr><th scope='row' style='text-align: $phpAds_TextAlignLeft'>{$GLOBALS['strStatsAction']}:</th><td style='padding-left: 8px'>{$action}</td></tr>
                                    <tr><th scope='row' style='text-align: $phpAds_TextAlignLeft'>{$GLOBALS['strConnectionType']}:</th><td style='padding-left: 8px'>{$connectionType}</td></tr>
                                    <tr><th scope='row' style='text-align: $phpAds_TextAlignLeft'>{$GLOBALS['strWindowDelay']}:</th><td style='padding-left: 8px'>{$windowDelay}</td></tr>";
            if (!is_null($conversion['comments'])) {
                echo "
                                    <tr><th scope='row' style='text-align: $phpAds_TextAlignLeft'>{$GLOBALS['strComments']}:</th><td style='padding-left: 8px'>{$conversion['comments']}</td></tr>";
            }
            echo "
                                </table>
                            </td>
                            <td width='60%'>
                                <table border='0' cellspacing='0' cellpadding='0'>
                                    <tr><th scope='col' style='text-align: $phpAds_TextAlignLeft'>{$GLOBALS['strStatsVariables']}:</th><td></td></tr>";
            foreach($aConVariables as $conVariable) {
                // Do not show hidden variables to publishers
                if (OA_Permission::isAccount(OA_ACCOUNT_TRAFFICKER) && $conVariable['hidden'] == 't') {
                    continue;
                }
                echo "<tr><th scope='row' style='text-align: $phpAds_TextAlignLeft; color: darkgrey'>".
                        htmlspecialchars(empty($conVariable['description']) ? $conVariable['name'] : $conVariable['description']).
                        "</th><td style='padding-left: 8px'>".htmlspecialchars($conVariable['value'])."</td></tr>";
            }
            echo "
                                </table>
                            </td>
                        </tr>
                    </table>
                ";
                echo "
                </td>
            </tr>";
        }
        echo "
        <tr height='1'><td colspan='6' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    }

    echo "
        <tr>
            <td colspan='4' align='$phpAds_TextAlignLeft' nowrap>";
    echo "
            </td>
            <td colspan='2' align='$phpAds_TextAlignRight' nowrap><img src='images/triangle-d.gif' align='absmiddle' border='0'>&nbsp;<a href='$pageName?$addUrl&amp;expand=all' accesskey='$keyExpandAll'>$strExpandAll</a>&nbsp;&nbsp;|&nbsp;&nbsp;<img src='images/$phpAds_TextDirection/triangle-l.gif' align='absmiddle' border='0'>&nbsp;<a href='$pageName?$addUrl&amp;expand=none' accesskey='$keyCollapseAll'>$strCollapseAll</a>&nbsp;&nbsp;</td>

        </tr>";




    echo "<form method='get' action='stats-conversions.php?".$_SERVER['QUERY_STRING']."'>";

    $getValues = split('&', $_SERVER['QUERY_STRING']);

    foreach ($getValues as $record) {
        $filed = explode('=', $record);
        echo "<input type='hidden' name='". $filed[0]."' value='". $filed[1]."'>";
    }


    echo "<tr>

            <td colspan='4' align='$phpAds_TextAlignLeft' nowrap> $strItemsPerPage $pager->pagerSelect ";

    echo "

            </td>

            <td colspan='2' align='$phpAds_TextAlignRight' nowrap> $pager->pagerLinks &nbsp;&nbsp;</td>

        </tr>

        </table>

        <br /><br />
        </form>
        ";

    if($editStatuses) {
        echo "<input type='submit' name='submit' value='$strSaveChanges' tabindex='".($tabindex++)."' onClick='document.connectionsmodify.submit()'>"."\n";
        echo "</form>"."\n";
    }
} else {
    echo "
        <br /><br /><div class='errormessage'><img class='errormessage' src='images/info.gif' width='16' height='16' border='0' align='absmiddle'>$strNoStats</div>";
}

// Store preferences
$session['prefs'][$pageName]['hideinactive'] = $hideinactive;
$session['prefs'][$pageName]['listorder'] = $listorder;
$session['prefs'][$pageName]['nodes'] = implode (",", $aNodes);
$session['prefs'][$pageName]['orderdirection'] = $orderdirection;
$session['prefs'][$pageName]['day'] = $day;
$session['prefs'][$pageName]['howLong'] = $howLong;
$session['prefs'][$pageName]['hour'] = $hour;
$session['prefs'][$pageName]['editStatuses'] = $editStatuses;
$session['prefs']['GLOBALS']['period_preset'] = $period_preset;
$session['prefs']['GLOBALS']['period_start'] = $period_start;
$session['prefs']['GLOBALS']['period_end'] = $period_end;
phpAds_SessionDataStore();

// Display page footer
phpAds_PageFooter();

?>
