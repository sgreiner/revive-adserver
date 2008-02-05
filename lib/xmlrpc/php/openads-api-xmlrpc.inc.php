<?php

/*
+---------------------------------------------------------------------------+
| OpenX v2.5                                                              |
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
$Id: openads-xmlrpc.inc.php 8911 2007-08-10 09:47:46Z andrew.hill@openads.org $
*/

if (!@include('XML/RPC.php')) {
    die('Error: cannot load the PEAR XML_RPC class');
}

require_once 'XmlRpcUtils.php';

// Include the info-object files
include_once('AdvertiserInfo.php');
include_once('AgencyInfo.php');
include_once('BannerInfo.php');
include_once('CampaignInfo.php');
include_once('PublisherInfo.php');
include_once('ZoneInfo.php');

/**
 * A library class to provide XML-RPC routines on
 * a web server to enable it to manipulate objects in OpenX using the web services API.
 *
 * @package    OpenX
 * @subpackage ExternalLibrary
 * @author     Chris Nutting <chris.nutting@openx.org>
 */

class OA_Api_Xmlrpc
{
    var $host;
    var $basepath;
    var $port;
    var $ssl;
    var $timeout;
    var $username;
    var $password;
    /**
     * The sessionId is set by the logon() method called during the constructor.
     *
     * @var string The remote session ID is used in all subsequent transactions.
     */
    var $sessionId;
    /**
     * Purely for my own use, this parameter lets me pass debug querystring parameters into
     * the remote call to trigger my Zend debugger on the server-side
     *
     * This will be removed before release
     *
     * @var string The querystring parameters required to trigger my remote debugger
     *             or empty for no remote debugging
     */
    var $debug = '';

    /**
     * PHP5 style constructor
     *
     * @param string $host      The name of the host to which to connect.
     * @param string $basepath  The base path to XML-RPC services.
     * @param string $username  The username to authenticate to the web services API.
     * @param string $password  The password for this user.
     * @param int    $port      The port number. Use 0 to use standard ports which
     *                          are port 80 for HTTP and port 443 for HTTPS.
     * @param bool   $ssl       Set to true to connect using an SSL connection.
     * @param int    $timeout   The timeout period to wait for a response.
     */
    function __construct($host, $basepath, $username, $password, $port = 0, $ssl = false, $timeout = 15)
    {
        $this->host = $host;
        $this->basepath = $basepath;
        $this->port = $port;
        $this->ssl  = $ssl;
        $this->timeout = $timeout;
        $this->username = $username;
        $this->password = $password;
        $this->_logon();
    }

    /**
     * PHP4 style constructor
     *
     * @see OA_API_XmlRpc::__construct
     */
    function OA_Api_Xmlrpc($host, $basepath, $username, $password, $port = 0, $ssl = false, $timeout = 15)
    {
        $this->__construct($host, $basepath, $username, $password, $port, $ssl, $timeout);
    }

    /**
     * This private function sends a method call and $data to a specified service and automatically
     * adds the value of the sessionID.
     *
     * @param string $service The name of the remote service file.
     * @param string $method  The name of the remote method to call.
     * @param mixed  $data    The data to send to the web service.
     * @return mixed The response from the server or false in the event of failure.
     */
    function _sendWithSession($service, $method, $data = array())
    {
        return $this->_send($service, $method, array_merge(array($this->sessionId), $data));
    }

    /**
     * This function sends a method call to a specified service.
     *
     * @param string $service The name of the remote service file.
     * @param string $method  The name of the remote method to call.
     * @param mixed  $data    The data to send to the web service.
     * @return mixed The response from the server or false in the event of failure.
     */
    function _send($service, $method, $data)
    {
        $dataMessage = array();
        foreach ($data as $element) {
            if (is_object($element) && is_subclass_of($element, 'OA_Info')) {
                $dataMessage[] = XmlRpcUtils::getEntityWithNotNullFields($element);
            } else {
                $dataMessage[] = XML_RPC_encode($element);
            }
        }
        $message = new XML_RPC_Message($method, $dataMessage);

        $client = new XML_RPC_Client($this->basepath . '/' . $service . $this->debug, $this->host);

        // Send the XML-RPC message to the server.
        $response = $client->send($message, $this->timeout, $this->ssl ? 'https' : 'http');

        // Check for an error response.
        if ($response && $response->faultCode() == 0) {
            $result = XML_RPC_decode($response->value());
        } else {
            die('XML-RPC error (' . $response->faultCode() . ') -> ' . $response->faultString() .
                '. In Method ' . $method . '().');
        }
        return $result;
    }

    /**
     * This method logs on to web services.
     *
     * @return boolean "Was the remote logon() call successful?"
     */
    function _logon()
    {
        $this->sessionId = $this->_send('LogonXmlRpcService.php', 'logon',
                                         array($this->username, $this->password));
        return true;
    }

    /**
     * This method logs off from web wervices.
     *
     * @return boolean "Was the remote logoff() call successful?"
     */
    function logoff()
    {
        return (bool) $this->_sendWithSession('LogonXmlRpcService.php', 'logoff');;
    }

    /**
     * This method returns statistics for an entity.
     *
     * @param string  $serviceFileName
     * @param string  $methodName
     * @param int  $entityId
     * @param Pear::Date  $oStartDate
     * @param Pear::Date  $oEndDate
     * @return array  result data
     */
    function _callStatisticsMethod($serviceFileName, $methodName, $entityId, $oStartDate = null, $oEndDate = null)
    {
        $dataArray = array((int) $entityId);
        if (is_object($oStartDate)) {
            $dataArray[] = XML_RPC_iso8601_encode($oStartDate->getDate(DATE_FORMAT_UNIXTIME));

            if (is_object($oEndDate)) {
                $dataArray[] = XML_RPC_iso8601_encode($oEndDate->getDate(DATE_FORMAT_UNIXTIME));
            }
        }

        $statisticsData = $this->_sendWithSession($serviceFileName,
                                                  $methodName, $dataArray);

        return $statisticsData;
    }

    /**
     * This method sends a call to the AgencyXmlRpcService and
     * passes the AgencyInfo with the session to add an agency.
     *
     * @param OA_Dll_AgencyInfo $oAgencyInfo
     * @return  method result
     */
    function addAgency(&$oAgencyInfo)
    {
        return (int) $this->_sendWithSession('AgencyXmlRpcService.php',
                                             'addAgency', array(&$oAgencyInfo));
    }

    /**
     * This method sends a call to the AgencyXmlRpcService and
     * passes the AgencyInfo object with the session to modify an agency.
     *
     * @param OA_Dll_AgencyInfo $oAgencyInfo
     * @return  method result
     */
    function modifyAgency(&$oAgencyInfo)
    {
        return (bool) $this->_sendWithSession('AgencyXmlRpcService.php', 'modifyAgency',
                                              array(&$oAgencyInfo));
    }

    /**
     * This method  returns the AgencyInfo for a specified agency.
     *
     * @param int $agencyId
     * @return OA_Dll_AgencyInfo
     */
    function getAgency($agencyId)
    {
        $dataAgency = $this->_sendWithSession('AgencyXmlRpcService.php',
                                              'getAgency', array((int) $agencyId));
        $oAgencyInfo = new OA_Dll_AgencyInfo();
        $oAgencyInfo->readDataFromArray($dataAgency);

        return $oAgencyInfo;
    }

    /**
     * This method returns AgencyInfo for all agencies.
     *
     * @param int $agencyId
     * @return array  array OA_Dll_AgencyInfo objects
     */
    function getAgencyList()
    {
        $dataAgencyList = $this->_sendWithSession('AgencyXmlRpcService.php',
                                                  'getAgencyList');
        $returnData = array();
        foreach ($dataAgencyList as $dataAgency) {
            $oAgencyInfo = new OA_Dll_AgencyInfo();
            $oAgencyInfo->readDataFromArray($dataAgency);
            $returnData[] = $oAgencyInfo;
        }

        return $returnData;
    }

    /**
     * This method deletes a specified agency.
     *
     * @param int $agencyId
     * @return  method result
     */
    function deleteAgency($agencyId)
    {
        return (bool) $this->_sendWithSession('AgencyXmlRpcService.php',
                                              'deleteAgency', array((int) $agencyId));
    }

    /**
     * This method returns the daily statistics for an agency for a specified time period.
     *
     * @param int $agencyId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function agencyDailyStatistics($agencyId, $oStartDate = null, $oEndDate = null)
    {
        $statisticsData = $this->_callStatisticsMethod('AgencyXmlRpcService.php',
                                                       'agencyDailyStatistics',
                                                       $agencyId, $oStartDate, $oEndDate);

        foreach ($statisticsData as $key => $data) {
            $statisticsData[$key]['day'] = date('Y-m-d',XML_RPC_iso8601_decode(
                                            $data['day']));
        }

        return $statisticsData;
    }

    /**
     * This method returns the advertiser statistics for an agency for a specified time period.
     *
     * @param int $agencyId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function agencyAdvertiserStatistics($agencyId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('AgencyXmlRpcService.php', 'agencyAdvertiserStatistics',
                                            $agencyId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns the campaign statistics for an agency for a specified time period.
     *
     * @param int $agencyId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function agencyCampaignStatistics($agencyId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('AgencyXmlRpcService.php', 'agencyCampaignStatistics',
                                            $agencyId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns the banner statistics for an agency for a specified time period.
     *
     * @param int $agencyId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function agencyBannerStatistics($agencyId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('AgencyXmlRpcService.php', 'agencyBannerStatistics',
                                            $agencyId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns the publisher statistics for an agency for a specified time period.
     *
     * @param int $agencyId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function agencyPublisherStatistics($agencyId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('AgencyXmlRpcService.php', 'agencyPublisherStatistics',
                                            $agencyId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns the zone statistics for an agency for a specified time period.
     *
     * @param int $agencyId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function agencyZoneStatistics($agencyId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('AgencyXmlRpcService.php', 'agencyZoneStatistics',
                                            $agencyId, $oStartDate, $oEndDate);
    }

    /**
     * This method adds an advertiser.
     *
     * @param OA_Dll_AdvertiserInfo $oAdvertiserInfo
     *
     * @return  method result
     */
    function addAdvertiser(&$oAdvertiserInfo)
    {
        return (int) $this->_sendWithSession('AdvertiserXmlRpcService.php',
                                             'addAdvertiser', array(&$oAdvertiserInfo));
    }

    /**
     * This method modifies an advertiser.
     *
     * @param OA_Dll_AdvertiserInfo $oAdvertiserInfo
     *
     * @return  method result
     */
    function modifyAdvertiser(&$oAdvertiserInfo)
    {
        return (bool) $this->_sendWithSession('AdvertiserXmlRpcService.php',
                                              'modifyAdvertiser', array(&$oAdvertiserInfo));
    }

    /**
     * This method returns AdvertiserInfo for a specified advertiser.
     *
     * @param int $advertiserId
     *
     * @return OA_Dll_AdvertiserInfo
     */
    function getAdvertiser($advertiserId)
    {
        $dataAdvertiser = $this->_sendWithSession('AdvertiserXmlRpcService.php',
                                                  'getAdvertiser', array((int) $advertiserId));
        $oAdvertiserInfo = new OA_Dll_AdvertiserInfo();
        $oAdvertiserInfo->readDataFromArray($dataAdvertiser);

        return $oAdvertiserInfo;
    }

    /**
     * This method returns a list of advertisers by Agency ID.
     *
     * @param int $agencyId
     *
     * @return array  array OA_Dll_AgencyInfo objects
     */
    function getAdvertiserListByAgencyId($agencyId)
    {
        $dataAdvertiserList = $this->_sendWithSession('AdvertiserXmlRpcService.php',
                                                      'getAdvertiserListByAgencyId', array((int) $agencyId));
        $returnData = array();
        foreach ($dataAdvertiserList as $dataAdvertiser) {
            $oAdvertiserInfo = new OA_Dll_AdvertiserInfo();
            $oAdvertiserInfo->readDataFromArray($dataAdvertiser);
            $returnData[] = $oAdvertiserInfo;
        }

        return $returnData;
    }

    /**
     * This method deletes an advertiser.
     *
     * @param int $advertiserId
     * @return  method result
     */
    function deleteAdvertiser($advertiserId)
    {
        return (bool) $this->_sendWithSession('AdvertiserXmlRpcService.php',
                                              'deleteAdvertiser', array((int) $advertiserId));
    }

    /**
     * This method returns daily statistics for an advertiser for a specified period.
     *
     * @param int $advertiserId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function advertiserDailyStatistics($advertiserId, $oStartDate = null, $oEndDate = null)
    {
        $statisticsData = $this->_callStatisticsMethod('AdvertiserXmlRpcService.php',
                                                       'advertiserDailyStatistics',
                                                       $advertiserId, $oStartDate, $oEndDate);

        foreach ($statisticsData as $key => $data) {
            $statisticsData[$key]['day'] = date('Y-m-d',XML_RPC_iso8601_decode(
                                            $data['day']));
        }

        return $statisticsData;
    }

    /**
     * This method returns campaign statistics for an advertiser for a specified period.
     *
     * @param int $advertiserId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function advertiserCampaignStatistics($advertiserId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('AdvertiserXmlRpcService.php',
                                            'advertiserCampaignStatistics',
                                            $advertiserId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns banner statistics for an advertiser for a specified period.
     *
     * @param int $advertiserId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function advertiserBannerStatistics($advertiserId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('AdvertiserXmlRpcService.php',
                                            'advertiserBannerStatistics',
                                            $advertiserId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns publisher statistics for an advertiser for a specified period.
     *
     * @param int $advertiserId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function advertiserPublisherStatistics($advertiserId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('AdvertiserXmlRpcService.php',
                                            'advertiserPublisherStatistics',
                                            $advertiserId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns zone statistics for an advertiser for a specified period.
     *
     * @param int $advertiserId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function advertiserZoneStatistics($advertiserId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('AdvertiserXmlRpcService.php',
                                            'advertiserZoneStatistics',
                                            $advertiserId, $oStartDate, $oEndDate);
    }

    /**
     * This method adds a campaign to the campaign object.
     *
     * @param OA_Dll_CampaignInfo $oCampaignInfo
     *
     * @return  method result
     */
    function addCampaign(&$oCampaignInfo)
    {
        return (int) $this->_sendWithSession('CampaignXmlRpcService.php',
                                             'addCampaign', array(&$oCampaignInfo));
    }

    /**
     * This method modifies a campaign.
     *
     * @param OA_Dll_CampaignInfo $oCampaignInfo
     *
     * @return  method result
     */
    function modifyCampaign(&$oCampaignInfo)
    {
        return (bool) $this->_sendWithSession('CampaignXmlRpcService.php',
                                              'modifyCampaign', array(&$oCampaignInfo));
    }

    /**
     * This method returns CampaignInfo for a specified campaign.
     *
     * @param int $campaignId
     *
     * @return OA_Dll_CampaignInfo
     */
    function getCampaign($campaignId)
    {
        $dataCampaign = $this->_sendWithSession('CampaignXmlRpcService.php',
                                                'getCampaign', array((int) $campaignId));
        $oCampaignInfo = new OA_Dll_CampaignInfo();
        $oCampaignInfo->readDataFromArray($dataCampaign);

        return $oCampaignInfo;
    }

    /**
     * This method returns a list of campaigns for an advertiser.
     *
     * @param int $campaignId
     *
     * @return array  array OA_Dll_CampaignInfo objects
     */
    function getCampaignListByAdvertiserId($advertiserId)
    {
        $dataCampaignList = $this->_sendWithSession('CampaignXmlRpcService.php',
                                                    'getCampaignListByAdvertiserId', array((int) $advertiserId));
        $returnData = array();
        foreach ($dataCampaignList as $dataCampaign) {
            $oCampaignInfo = new OA_Dll_CampaignInfo();
            $oCampaignInfo->readDataFromArray($dataCampaign);
            $returnData[] = $oCampaignInfo;
        }
    }

    /**
     * This method deletes a campaign from the campaign object.
     *
     * @param int $campaignId
     * @return  method result
     */
    function deleteCampaign($campaignId)
    {
        return (bool) $this->_sendWithSession('CampaignXmlRpcService.php',
                                              'deleteCampaign', array((int) $campaignId));
    }

    /**
     * This method returns daily statistics for a campaign for a specified period.
     *
     * @param int $campaignId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function campaignDailyStatistics($campaignId, $oStartDate = null, $oEndDate = null)
    {
        $statisticsData = $this->_callStatisticsMethod('CampaignXmlRpcService.php',
                                                       'campaignDailyStatistics',
                                                       $campaignId, $oStartDate, $oEndDate);

        foreach ($statisticsData as $key => $data) {
            $statisticsData[$key]['day'] = date('Y-m-d',XML_RPC_iso8601_decode(
                                            $data['day']));
        }

        return $statisticsData;
    }

    /**
     * This method returns banner statistics for a campaign for a specified period.
     *
     * @param int $campaignId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function campaignBannerStatistics($campaignId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('CampaignXmlRpcService.php',
                                            'campaignBannerStatistics',
                                            $campaignId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns publisher statistics for a campaign for a specified period.
     *
     * @param int $campaignId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function campaignPublisherStatistics($campaignId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('CampaignXmlRpcService.php',
                                            'campaignPublisherStatistics',
                                            $campaignId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns zone statistics for a campaign for a specified period.
     *
     * @param int $campaignId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function campaignZoneStatistics($campaignId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('CampaignXmlRpcService.php',
                                            'campaignZoneStatistics',
                                            $campaignId, $oStartDate, $oEndDate);
    }

    /**
     * This method adds a banner to the banner object.
     *
     * @param OA_Dll_BannerInfo $oBannerInfo
     *
     * @return  method result
     */
    function addBanner(&$oBannerInfo)
    {
        return (int) $this->_sendWithSession('BannerXmlRpcService.php',
                                             'addBanner', array(&$oBannerInfo));
    }

    /**
     * This method modifies a banner.
     *
     * @param OA_Dll_BannerInfo $oBannerInfo
     *
     * @return  method result
     */
    function modifyBanner(&$oBannerInfo)
    {
        return (bool) $this->_sendWithSession('BannerXmlRpcService.php',
                                              'modifyBanner', array(&$oBannerInfo));
    }

    /**
     * This method returns BannerInfo for a specified banner.
     *
     * @param int $bannerId
     *
     * @return OA_Dll_BannerInfo
     */
    function getBanner($bannerId)
    {
        $dataBanner = $this->_sendWithSession('BannerXmlRpcService.php',
                                                'getBanner', array((int) $bannerId));
        $oBannerInfo = new OA_Dll_BannerInfo();
        $oBannerInfo->readDataFromArray($dataBanner);

        return $oBannerInfo;
    }

    /**
     * This method returns a list of banners for a specified campaign.
     *
     * @param int $banenrId
     *
     * @return array  array OA_Dll_CampaignInfo objects
     */
    function getBannerListByCampaignId($campaignId)
    {
        $dataBannerList = $this->_sendWithSession('BannerXmlRpcService.php',
                                                  'getBannerListByCampaignId', array((int) $campaignId));
        $returnData = array();
        foreach ($dataBannerList as $dataBanner) {
            $oBannerInfo = new OA_Dll_BannerInfo();
            $oBannerInfo->readDataFromArray($dataBanner);
            $returnData[] = $oBannerInfo;
        }

        return $returnData;
    }

    /**
     * This method deletes a banner from the banner object.
     *
     * @param int $bannerId
     * @return  method result
     */
    function deleteBanner($bannerId)
    {
        return (bool) $this->_sendWithSession('BannerXmlRpcService.php',
                                              'deleteBanner', array((int) $bannerId));
    }

    /**
     * This method returns daily statistics for a banner for a specified period.
     *
     * @param int $bannerId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function bannerDailyStatistics($bannerId, $oStartDate = null, $oEndDate = null)
    {
        $statisticsData = $this->_callStatisticsMethod('BannerXmlRpcService.php',
                                                       'bannerDailyStatistics',
                                                       $bannerId, $oStartDate, $oEndDate);

        foreach ($statisticsData as $key => $data) {
            $statisticsData[$key]['day'] = date('Y-m-d',XML_RPC_iso8601_decode(
                                            $data['day']));
        }

        return $statisticsData;
    }

    /**
     * This method returns publisher statistics for a banner for a specified period.
     *
     * @param int $bannerId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function bannerPublisherStatistics($bannerId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('BannerXmlRpcService.php',
                                            'bannerPublisherStatistics',
                                            $bannerId, $oStartDate, $oEndDate);

    }

    /**
     * This method returns zone statistics for a banner for a specified period.
     *
     * @param int $bannerId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function bannerZoneStatistics($bannerId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('BannerXmlRpcService.php',
                                            'bannerZoneStatistics',
                                            $bannerId, $oStartDate, $oEndDate);

    }

    /**
     * This method adds a publisher to the publisher object.
     *
     * @param OA_Dll_PublisherInfo $oPublisherInfo
     * @return  method result
     */
    function addPublisher(&$oPublisherInfo)
    {
        return (int) $this->_sendWithSession('PublisherXmlRpcService.php',
                                             'addPublisher', array(&$oPublisherInfo));

        return $returnData;
    }

    /**
     * This method modifies a publisher.
     *
     * @param OA_Dll_PublisherInfo $oPublisherInfo
     * @return  method result
     */
    function modifyPublisher(&$oPublisherInfo)
    {
        return (bool) $this->_sendWithSession('PublisherXmlRpcService.php', 'modifyPublisher',
                                              array(&$oPublisherInfo));
    }

    /**
     * This method returns PublisherInfo for a specified publisher.
     *
     * @param int $publisherId
     * @return OA_Dll_PublisherInfo
     */
    function getPublisher($publisherId)
    {
        $dataPublisher = $this->_sendWithSession('PublisherXmlRpcService.php',
                                                 'getPublisher', array((int) $publisherid));
        $oPublisherInfo = new OA_Dll_PublisherInfo();
        $oPublisherInfo->readDataFromArray($dataPublisher);

        return $oPublisherInfo;
    }

    /**
     * This method returns a list of publishers for a specified agency.
     *
     * @param int $agencyId
     * @return array  array OA_Dll_PublisherInfo objects
     */
    function getPublisherListByAgencyId($agencyId)
    {
        $dataPublisherList = $this->_sendWithSession('PublisherXmlRpcService.php',
                                                     'getPublisherListByAgencyId', array((int) $agencyId));
        $returnData = array();
        foreach ($dataPublisherList as $dataPublisher) {
            $oPublisherInfo = new OA_Dll_PublisherInfo();
            $oPublisherInfo->readDataFromArray($dataPublisher);
            $returnData[] = $oPublisherInfo;
        }

        return $returnData;
    }

    /**
     * This method deletes a publisher from the publisher object.
     *
     * @param int $publisherId
     * @return  method result
     */
    function deletePublisher($publisherId)
    {
        return (bool) $this->_sendWithSession('PublisherXmlRpcService.php',
                                              'deletePublisher', array((int) $publisherId));
    }

    /**
     * This method returns daily statistics for a publisher for a specified period.
     *
     * @param int $publisherId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function publisherDailyStatistics($publisherId, $oStartDate = null, $oEndDate = null)
    {
        $statisticsData = $this->_callStatisticsMethod('PublisherXmlRpcService.php',
                                                       'publisherDailyStatistics',
                                                       $publisherId, $oStartDate, $oEndDate);

        foreach ($statisticsData as $key => $data) {
            $statisticsData[$key]['day'] = date('Y-m-d',XML_RPC_iso8601_decode(
                                            $data['day']));
        }

        return $statisticsData;
    }

    /**
     * This method returns zone statistics for a publisher for a specified period.
     *
     * @param int $publisherId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function publisherZoneStatistics($publisherId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('PublisherXmlRpcService.php',
                                            'publisherZoneStatistics',
                                            $publisherId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns advertiser statistics for a specified period.
     *
     * @param int $publisherId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function publisherAdvertiserStatistics($publisherId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('PublisherXmlRpcService.php',
                                            'publisherAdvertiserStatistics',
                                            $publisherId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns campaign statistics for a publisher for a specified period.
     *
     * @param int $publisherId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function publisherCampaignStatistics($publisherId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('PublisherXmlRpcService.php',
                                            'publisherCampaignStatistics',
                                            $publisherId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns banner statistics for a publisher for a specified period.
     *
     * @param int $publisherId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function publisherBannerStatistics($publisherId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('PublisherXmlRpcService.php',
                                            'publisherBannerStatistics',
                                            $publisherId, $oStartDate, $oEndDate);
    }

    /**
     * This method adds a zone to the zone object.
     *
     * @param OA_Dll_ZoneInfo $oZoneInfo
     * @return  method result
     */
    function addZone(&$oZoneInfo)
    {
        return (int) $this->_sendWithSession('ZoneXmlRpcService.php',
                                             'addZone', array(&$oZoneInfo));
    }

    /**
     * This method modifies a zone.
     *
     * @param OA_Dll_ZoneInfo $oZoneInfo
     * @return  method result
     */
    function modifyZone(&$oZoneInfo)
    {
        return (bool) $this->_sendWithSession('ZoneXmlRpcService.php', 'modifyZone',
                                              array(&$oZoneInfo));
    }

    /**
     * This method returns ZoneInfo for a specified zone.
     *
     * @param int $zoneId
     * @return OA_Dll_ZoneInfo
     */
    function getZone($zoneId)
    {
        $dataZone = $this->_sendWithSession('ZoneXmlRpcService.php',
                                                 'getZone', array((int) $zoneid));
        $oZoneInfo = new OA_Dll_ZoneInfo();
        $oZoneInfo->readDataFromArray($dataZone);

        return $oZoneInfo;
    }

    /**
     * This method returns a list of zones for a specified publisher.
     *
     * @param int $publisherId
     * @return array  array OA_Dll_ZoneInfo objects
     */
    function getZoneListByPublisherId($publisherId)
    {
        $dataZoneList = $this->_sendWithSession('ZoneXmlRpcService.php',
                                                'getZoneListByPublisherId', array((int) $publisherId));
        $returnData = array();
        foreach ($dataZoneList as $dataZone) {
            $oZoneInfo = new OA_Dll_ZoneInfo();
            $oZoneInfo->readDataFromArray($dataZone);
            $returnData[] = $oZoneInfo;
        }

        return $returnData;
    }

    /**
     * This method deletes a zone from the zone object.
     *
     * @param int $zoneId
     * @return  method result
     */
    function deleteZone($zoneId)
    {
        return (bool) $this->_sendWithSession('ZoneXmlRpcService.php',
                                              'deleteZone', array((int) $zoneId));
    }

    /**
     * This method returns daily statistics for a zone for a specified period.
     *
     * @param int $zoneId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function zoneDailyStatistics($zoneId, $oStartDate = null, $oEndDate = null)
    {
        $statisticsData = $this->_callStatisticsMethod('ZoneXmlRpcService.php',
                                                       'zoneDailyStatistics',
                                                       $zoneId, $oStartDate, $oEndDate);

        foreach ($statisticsData as $key => $data) {
            $statisticsData[$key]['day'] = date('Y-m-d',XML_RPC_iso8601_decode(
                                            $data['day']));
        }

        return $statisticsData;
    }

    /**
     * This method returns advertiser statistics for a zone for a specified period.
     *
     * @param int $zoneId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function zoneAdvertiserStatistics($zoneId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('ZoneXmlRpcService.php',
                                            'zoneAdvertiserStatistics',
                                            $zoneId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns campaign statistics for a zone for a specified period.
     *
     * @param int $zoneId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function zoneCampaignStatistics($zoneId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('ZoneXmlRpcService.php',
                                            'zoneCampaignStatistics',
                                            $zoneId, $oStartDate, $oEndDate);
    }

    /**
     * This method returns publisher statistics for a zone for a specified period.
     *
     * @param int $zoneId
     * @param Pear::Date $oStartDate
     * @param Pear::Date $oEndDate
     * @return array  result data
     */
    function zoneBannerStatistics($zoneId, $oStartDate = null, $oEndDate = null)
    {
        return $this->_callStatisticsMethod('ZoneXmlRpcService.php',
                                            'zoneBannerStatistics',
                                            $zoneId, $oStartDate, $oEndDate);
    }


}

?>