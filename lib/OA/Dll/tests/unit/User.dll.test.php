<?php

/*
+---------------------------------------------------------------------------+
| OpenX v2.5                                                              |
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

require_once MAX_PATH . '/lib/OA/Dll/Agency.php';
require_once MAX_PATH . '/lib/OA/Dll/AgencyInfo.php';
require_once MAX_PATH . '/lib/OA/Dll/User.php';
require_once MAX_PATH . '/lib/OA/Dll/UserInfo.php';
require_once MAX_PATH . '/lib/OA/Dll/tests/util/DllUnitTestCase.php';

/**
 * A class for testing DLL User methods
 *
 * @package    OpenXDll
 * @subpackage TestSuite
 * @author     Matteo Beccati <matteo.beccati@openx.org>
 *
 */


class OA_Dll_UserTest extends DllUnitTestCase
{
    /**
     * @var int
     */
    var $accountId;

    /**
     * Errors
     *
     */
    var $unknownIdError = 'Unknown userId Error';

    /**
     * The constructor method.
     */
    function OA_Dll_UserTest()
    {
        $this->UnitTestCase();
        Mock::generatePartial(
            'OA_Dll_Agency',
            'PartialMockOA_Dll_Agency',
            array('checkPermissions')
        );
        Mock::generatePartial(
            'OA_Dll_User',
            'PartialMockOA_Dll_User',
            array('checkPermissions')
        );
    }

    function setUp()
    {
        $agencyId = DataGenerator::generateOne('agency');
        $doAgency = OA_Dal::staticGetDO('agency', $agencyId);
        $this->accountId = (int)$doAgency->account_id;
    }

    function tearDown()
    {
        DataGenerator::cleanUp();
    }

    /**
     * A method to test Add, Modify and Delete.
     */
    function XXXtestAddModifyDelete()
    {
        $dllUserPartialMock = new PartialMockOA_Dll_User($this);

        $dllUserPartialMock->setReturnValue('checkPermissions', true);
        $dllUserPartialMock->expectCallCount('checkPermissions', 5);


        $oUserInfo = new OA_DLL_UserInfo();

        $oUserInfo->contactName         = 'test User';
        $oUserInfo->emailAddress        = 'test@example.com';
        $oUserInfo->username            = 'foo-'.time();
        $oUserInfo->password            = 'fooPwd';
        $oUserInfo->defaultAccountId    = $this->accountId;

        // Add
        $this->assertTrue($dllUserPartialMock->modify($oUserInfo),
                          $dllUserPartialMock->getLastError());

        // Modify
        $oUserInfo->userName = 'modified User';

        $this->assertTrue($dllUserPartialMock->modify($oUserInfo),
                          $dllUserPartialMock->getLastError());

        // Delete
        $this->assertTrue($dllUserPartialMock->delete($oUserInfo->userId),
            $dllUserPartialMock->getLastError());

        // Modify not existing id
        $this->assertTrue((!$dllUserPartialMock->modify($oUserInfo) &&
                          $dllUserPartialMock->getLastError() == $this->unknownIdError),
            $this->_getMethodShouldReturnError($this->unknownIdError));

        // Delete not existing id
        $this->assertTrue((!$dllUserPartialMock->delete($oUserInfo->userId) &&
                           $dllUserPartialMock->getLastError() == $this->unknownIdError),
            $this->_getMethodShouldReturnError($this->unknownIdError));

        $dllUserPartialMock->tally();
    }


    /**
     * A method to test get and getList method.
     */
    function testGetAndGetList()
    {
        $dllUserPartialMock = new PartialMockOA_Dll_User($this);
        $dllAgencyPartialMock     = new PartialMockOA_Dll_Agency($this);

        $dllAgencyPartialMock->setReturnValue('checkPermissions', true);
        $dllAgencyPartialMock->expectCallCount('checkPermissions', 1);

        $dllUserPartialMock->setReturnValue('checkPermissions', true);
        $dllUserPartialMock->expectCallCount('checkPermissions', 7);

        $oAgencyInfo             = new OA_Dll_AgencyInfo();
        $oAgencyInfo->agencyName = 'agency name';
        $this->assertTrue($dllAgencyPartialMock->modify($oAgencyInfo),
                          $dllAgencyPartialMock->getLastError());

        $oUserInfo1                     = new OA_Dll_UserInfo();
        $oUserInfo1->contactName        = 'test name 1';
        $oUserInfo1->emailAddress       = 'name@domain.com';
        $oUserInfo1->username           = 'user1-'.time();
        $oUserInfo1->password           = 'pwd';
        $oUserInfo1->defaultAccountId   = $oAgencyInfo->accountId;

        $oUserInfo2                     = new OA_Dll_UserInfo();
        $oUserInfo2->contactName        = 'test name 2';
        $oUserInfo2->emailAddress       = 'name@domain.com';
        $oUserInfo2->username           = 'user2'.time();
        $oUserInfo2->password           = 'pwd';
        $oUserInfo2->defaultAccountId   = $oAgencyInfo->accountId;

        // Add
        $this->assertTrue($dllUserPartialMock->modify($oUserInfo1),
                          $dllUserPartialMock->getLastError());

        $this->assertTrue($dllUserPartialMock->modify($oUserInfo2),
                          $dllUserPartialMock->getLastError());

        $oUserInfo1Get = null;
        $oUserInfo2Get = null;
        // Get
        $this->assertTrue($dllUserPartialMock->getUser($oUserInfo1->userId,
                                                                   $oUserInfo1Get),
                          $dllUserPartialMock->getLastError());
        $this->assertTrue($dllUserPartialMock->getUser($oUserInfo2->userId,
                                                                   $oUserInfo2Get),
                          $dllUserPartialMock->getLastError());

        // Check field value
        $this->assertFieldEqual($oUserInfo1, $oUserInfo1Get, 'contactName');
        $this->assertFieldEqual($oUserInfo1, $oUserInfo1Get, 'emailAddress');
        $this->assertFieldEqual($oUserInfo1, $oUserInfo1Get, 'username');
        $this->assertFieldEqual($oUserInfo1, $oUserInfo1Get, 'defaultAccountId');

        $this->assertFieldEqual($oUserInfo2, $oUserInfo2Get, 'contactName');
        $this->assertFieldEqual($oUserInfo2, $oUserInfo2Get, 'emailAddress');
        $this->assertFieldEqual($oUserInfo2, $oUserInfo2Get, 'username');
        $this->assertFieldEqual($oUserInfo2, $oUserInfo2Get, 'defaultAccountId');

        // Get List
        $aUserList = array();
        $this->assertTrue($dllUserPartialMock->getUserListByAccountId($oAgencyInfo->accountId,
                                                                                 $aUserList),
                          $dllUserPartialMock->getLastError());
        $this->assertEqual(count($aUserList) == 2,
                           '2 records should be returned');
        $oUserInfo1Get = $aUserList[0];
        $oUserInfo2Get = $aUserList[1];
        if ($oUserInfo1->userId == $oUserInfo2Get->userId) {
            $oUserInfo1Get = $aUserList[1];
            $oUserInfo2Get = $aUserList[0];
        }
        // Check field value from list
        $this->assertFieldEqual($oUserInfo1, $oUserInfo1Get, 'username');
        $this->assertFieldEqual($oUserInfo2, $oUserInfo2Get, 'username');


        // Delete
        $this->assertTrue($dllUserPartialMock->delete($oUserInfo1->userId),
            $dllUserPartialMock->getLastError());

        // Get not existing id
        $this->assertTrue((!$dllUserPartialMock->getUser($oUserInfo1->userId,
                                                                     $oUserInfo1Get) &&
                          $dllUserPartialMock->getLastError() == $this->unknownIdError),
            $this->_getMethodShouldReturnError($this->unknownIdError));

        $dllUserPartialMock->tally();
    }
}

?>