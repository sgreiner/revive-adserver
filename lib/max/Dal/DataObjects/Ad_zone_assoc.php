<?php

/*
+---------------------------------------------------------------------------+
| OpenX  v${RELEASE_MAJOR_MINOR}                                                              |
| ============                                                              |
|                                                                           |
| Copyright (c) 2003-2008 Openads Limited                                   |
| For contact details, see: http://www.openx.org/                           |
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

/**
 * Table Definition for ad_zone_assoc
 */
require_once 'DB_DataObjectCommon.php';

class DataObjects_Ad_zone_assoc extends DB_DataObjectCommon
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    var $__table = 'ad_zone_assoc';                   // table name
    var $ad_zone_assoc_id;                // int(9)  not_null primary_key auto_increment
    var $zone_id;                         // int(9)  multiple_key
    var $ad_id;                           // int(9)  multiple_key
    var $priority;                        // real(22)
    var $link_type;                       // int(6)  not_null
    var $priority_factor;                 // real(22)
    var $to_be_delivered;                 // int(1)  not_null

    /* ZE2 compatibility trick*/
    function __clone() { return $this;}

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('DataObjects_Ad_zone_assoc',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function _auditEnabled()
    {
        return true;
    }

    function _getContextId()
    {
        return $this->ad_zone_assoc_id;
    }

    function _getContext()
    {
        return 'Ad Zone Association';
    }

    /**
     * A private method to return the account ID of the
     * account that should "own" audit trail entries for
     * this entity type; NOT related to the account ID
     * of the currently active account performing an
     * action.
     *
     * @return integer The account ID to insert into the
     *                 "account_id" column of the audit trail
     *                 database table.
     */
    function getOwningAccountId()
    {
        if (!empty($this->zone_id)) {
            // Return the manager from the trafficker/zone side
            return $this->_getOwningAccountIdFromParent('zones', 'zone_id');
        } else {
            // Return the manager from the advertiser/banner side
            return $this->_getOwningAccountIdFromParent('banners', 'ad_id');
        }
    }

    /**
     * build an agency specific audit array
     *
     * @param integer $actionid
     * @param array $aAuditFields
     */
    function _buildAuditArray($actionid, &$aAuditFields)
    {
        $aAuditFields['key_desc']     = 'Ad #'.$this->ad_id.' -> Zone #'.$this->zone_id;
        switch ($actionid)
        {
            case OA_AUDIT_ACTION_UPDATE:
                        $aAuditFields['bannerid']            = $this->bannerid;
                        break;
            case OA_AUDIT_ACTION_INSERT:
            case OA_AUDIT_ACTION_DELETE:
                        $aAuditFields['to_be_delivered']     = $this->_formatValue('to_be_delivered');
                        break;
        }
    }

    function _formatValue($field)
    {
        switch ($field)
        {
            case 'to_be_delivered':
                return $this->_boolToStr($this->$field);
            default:
                return $this->$field;
        }
    }
}

?>