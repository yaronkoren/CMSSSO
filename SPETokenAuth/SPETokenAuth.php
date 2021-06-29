<?php

/*
 * Copyright (c) 2014 The MITRE Corporation
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */
use MediaWiki\Logger\LoggerFactory;
class SPETokenAuthentication extends SpecialPage {

	/**
	 * @since 1.0
	 *
	 * @param &$id
	 * @param &$username
	 * @param &$realname
	 * @param &$email
	 */

	public static function Instance(){
        static $inst = null;
        if ($inst === null) {
            $inst = new SPETokenAuthentication();
        }
        return $inst;
    }
	public function getDataAraay(){
        global $wgCrossSiteSSOProvider, $wgCrossSiteSSOConsumer, $wgCrossSiteSSO, $wgSecretPhrase, $wgIv, $wgSecretKey, $wgEmergencyContact, $wgEnableTestData;
        $data = array();
        if(
            isset($_COOKIE['email'])
            && isset($_COOKIE['emeta_id'])
            && isset($_COOKIE['ERIGHTS'])
            && isset($_COOKIE['first_name'])
            && isset($_COOKIE['is_org'])
            && isset($_COOKIE['last_name'])
            && isset($_COOKIE['sm_constitid'])
            && isset($_COOKIE['status'])
            && isset($_COOKIE['student'])
            && stristr($wgCrossSiteSSOProvider, $_SERVER['HTTP_HOST'])
        ){
            //user is logging in from SSO provider i.e. *.spe.org domain
            $this->logDebug("User is on *.spe.org. Using cookie authentication instead of token authentication");
            // User is from *.spe.org
            // let us make sure that minimum required cookies exists
            if(
                !empty(trim($_COOKIE['email'])) && strlen(trim($_COOKIE['email'])) > 0
                &&  !empty(trim($_COOKIE['first_name'])) && strlen(trim($_COOKIE['first_name'])) > 0
                && !empty(trim($_COOKIE['last_name'])) && strlen(trim($_COOKIE['last_name'])) > 0
                && !empty(trim($_COOKIE['sm_constitid'])) && strlen(trim($_COOKIE['sm_constitid'])) > 0
                && !empty(trim($_COOKIE['status'])) && strlen(trim($_COOKIE['status'])) > 0
                //    && $_COOKIE['student'] != '' && strlen($_COOKIE['student']) > 0
            ){
                //Company cookie is no more avaialable. Get the company name from the web service instead..
                $constitId = $_COOKIE['sm_constitid'];
                $memberDataJsonArray = SPEHelperMethods::Instance()->GetUserPersonifyData($constitId);
                $memberDataJsonArray = array();
                //We have a real data
                if(is_array($memberDataJsonArray) &&  count($memberDataJsonArray) > 0 && strlen($memberDataJsonArray->Company) > 0)
                {
                    $data['company'] = $memberDataJsonArray->Company;
                }
                else
                {
                    //do we want to handle the non-availability of the company
                    $data['company'] = 'Company:unknown';
                }

                $data['email'] = strtolower(trim($_COOKIE['email']));
                $data['emeta_id'] = trim($_COOKIE['emeta_id']);
                $data['erights'] = trim($_COOKIE['ERIGHTS']);
                $data['first_name'] = trim($_COOKIE['first_name']);
                $data['is_org'] = trim($_COOKIE['is_org']);
                $data['last_name'] = trim($_COOKIE['last_name']);
                $data['sm_constitid'] = trim($_COOKIE['sm_constitid']);
                $data['status'] = trim($_COOKIE['status']);
                $data['student'] = (strlen(trim($_COOKIE['student'])) > 0 ? trim($_COOKIE['student']) : 'false');
                $this->logDebug( 'Cookie data for the user is:'.print_r($data, true));
            }
            else{
                $this->logDebug("Cookie exists but missing the key data.. Will have to log the client IP address with data available in cookie");
                $availableDataArray = array("Email"=>trim(strtolower($_COOKIE['email'])),
                    "First Name"=> trim($_COOKIE['first_name']),
                    "Last Name"=>trim($_COOKIE['last_name']),
                    "Constit ID"=>trim($_COOKIE['sm_constitid']),
                    "Membership Status"=>trim($_COOKIE['status']),
                    "Student Member" => (strlen(trim($_COOKIE['student'])) > 0 ? trim($_COOKIE['student']) : 'false'),
                    "Time activity logged" => date("m/d/Y H:i:s"),
                    "IP Address"=> SPEUserHelperMethods::Instance()->getClientIP()
                );
                $availableDataJsonString = json_encode($availableDataArray, JSON_PRETTY_PRINT);
                $this->logDebug(sprintf("Here is available user data: %s", $availableDataJsonString));
                UserMailer::send(
                    new MailAddress( $wgEmergencyContact ),
                    new MailAddress( $wgEmergencyContact ),
                    'Invalid Cookie supplied by the user',
                    sprintf("User Data %s", $availableDataJsonString) );
                unset($availableDataArray);
                emptyArray($data);
            }
        }
        else if($wgCrossSiteSSO === true && stristr($wgCrossSiteSSOConsumer, $_SERVER['HTTP_HOST'])){
            $encryptedToken = $_COOKIE['token'];
            if(!empty(trim($encryptedToken))){
                $token = openssl_decrypt(base64_decode($encryptedToken), 'des-cfb', $wgSecretPhrase, false, $wgIv);
                $this->logDebug("Decrypted the token to the following Value: $token");
                $cookieData = explode("::", $token);
                if( count($cookieData) == 11 )
                {
                    $this->logDebug("Cookie Data count is 11 so entering into the code to setup the data array");
                    $cookieDataTrimmed = $cookieData;
                    array_pop($cookieDataTrimmed);
                    $verificationData = implode(",", $cookieDataTrimmed);
                    if($verificationData === $cookieData[10])
                    {
                        $this->logDebug("Verification Data and Cookie array element# 11 is the same.. Setting up the data array now. This is a final step to setup a data array");
                        $data['company'] = $cookieData[0];
                        $data['email'] = $cookieData[1];
                        $data['emeta_id'] = $cookieData[2];
                        $data['erights'] = $cookieData[3];
                        $data['first_name'] = $cookieData[4];
                        $data['is_org'] = $cookieData[5];
                        $data['last_name'] = $cookieData[6];
                        $data['sm_constitid'] = $cookieData[7];
                        $data['status'] = $cookieData[8];
                        $data['student'] = $cookieData[9];
                        $this->logDebug( 'Cookie data for the Cross-Domain user is:'.print_r($data, true));
                    }
                    else
                    {
                        $this->logDebug("Verification Data is not the same. The cookie data array is: ".$cookieData[10]. " and token value is: $verificationData");
                        $this->logDebug("Unsetting the data array");
                        SPEHelperMethods::Instance()->EmptyArray($data);
                    }
                }
                else
                {
                    $this->logDebug("Cookie data count is not the same.. Unsetting the data array");
                    SPEHelperMethods::Instance()->EmptyArray($data);
                }
            }
        }
        return $data;
    }
    public function logDebug($message, $level="debug")
    {
        global $wgSSODebug;
        if(strtolower($wgSSODebug) == 'on')
        {
            $context = array();
            //wfDebugLog( 'sso', $message, false );
            $logger = LoggerFactory::getInstance( 'sso' );
            $context['private'] = true;
            $logger->debug($message, $context);
        }

    }
    public function verifyAndAssignUsername($username){
        $newUsername = $username;
        SPETokenAuthentication::Instance()->logDebug("Let us verify that username: $username we are going to use to create a new member is available in DB");
        $userId = SPEUserHelperMethods::Instance()->findUserIdFromUsername($username);
        if($userId != null)
        {
            SPETokenAuthentication::Instance()->logDebug("We have the user with the same username: $username exists at user_id: $userId");
            SPETokenAuthentication::Instance()->logDebug("Let us create a new one for this user");
            for($i=1; $i<1000; $i++)
            {
                $newUsername = $newUsername.$i;
                SPETokenAuthentication::Instance()->logDebug("Trying to lock the username: $newUsername");
                $userId = SPEUserHelperMethods::Instance()->findUserIdFromUsername($newUsername);
                if($userId != null)
                {
                    SPETokenAuthentication::Instance()->logDebug("The username: $newUsername generated by system already exists at user_id: $userId.. Moving on with our iteration");
                    continue;
                }
                else
                {
                    SPETokenAuthentication::Instance()->logDebug("The username: $newUsername generated by system seems to be unique. Returning this");
                    break;	//break doesn't make sense here... but just to make sure that it breaks for the compiler.
                }
            }
        }

        return $newUsername;
    }
    public function getUserNameForNewUser($data){
        global $wgAuth;
        $username = $data['first_name'].$data['last_name'];
        /*
         * Remove special utf-8 and non-utf-8 characters.. since Mediawiki chokes on it..
         * The problem was surfaced when user with name Ákos Gyurkó Tried to login and it failed in function
         * User::newFromName( $this->mUsername ); in SpecialUserLogin.php
         */
        $username = mb_convert_encoding($username, 'UTF-8', 'UTF-8');
        $username = preg_replace('/[^(\x20-\x7F)]*/','', $username);
        /*
         * 2013-10-06 10:18am
         * I observed that there are still some user which has space and . in his/her username.
         * This is less than desirable. Get rid of all characters except letteres and numbers.
         */
        $username=preg_replace('/[^a-zA-Z0-9]/','',$username);

        //$username = $wgAuth->getCanonicalName( $username );
        $username = User::getCanonicalName($username);
        $username = ucwords(strtolower($username));
        /*
         * Check if usernmae is already taken..
         * If it is already taken, create a new one for it..
         */
        $username = $this::verifyAndAssignUsername($username);
        return $username;
    }
    public function findUser($data)
    {
        /*
         * As per my discussion with Bei, constitid is only the key field in emeta which
         * identifies the uniqueness of a record. So we also will search by the email address only.
         * I also was checking for the email address in OR statement but I think that is not needed.
         * If we need OR statement for the email address, uncomment the line for email.
         */
        $userId = null;

        try {
            $dbr = wfGetDB(DB_REPLICA);
            $constit_id = intval($data['sm_constitid']);
            $userIdFromDb = $dbr->selectField('customer', 'user_id',
                array(
                    //'email_address'=>$data['email'],
                    'CONVERT(customer_id, UNSIGNED INTEGER)' => $constit_id,
                )
            );
            if (!is_null($userIdFromDb) && $userIdFromDb != 0 && $userIdFromDb != "0"){
                $userId = $userIdFromDb;
            }
            else {
                /*
                 * We will search one more time in the user table using the user's Full name..
                 * and email address. Chances are there that users are created before the existence of a customer table
                 * Instead of creating the new one,
                 */
                $userIdFromDb = $dbr->selectField('user', 'user_id',
                    array(
                        'user_email' => $data['email'],
                        'user_real_name' => ucwords(strtolower($data['first_name'])) . " " . ucwords(strtolower($data['last_name'])),
                    )
                );
                if ($userIdFromDb != '')
                    $userId = $userIdFromDb;
            }
        } catch (DBUnexpectedError $e) {
            SPETokenAuthentication::Instance()->logDebug("Database Error occurred in getting users: ".$e->getMessage());
        }


        return $userId;

    }
    public function addOrUpdateCustomerData($user_id, $data){
        global $wgBusinessUserEmail, $wgBusinessUserFirstName, $wgBusinessUserLastName;
        global $wgSiteAdminEmail, $wgNotifyBusUserOnPermissionsChanged, $user;
        $dbr = wfGetDB(DB_MASTER);
        $user = new User();
        $user->mId = $user_id;
        $user->loadfromDatabase();
        $user->invalidateCache();

        $this->logDebug("As I have a valid user in the database with user id $user_id, Entered in updateData to check and see if I need to update ths user data");
        //get the data for the selected user..

        $customer_map = array(
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email_address' => 'email',
            'company_name' => 'company',
            'membership_status' => 'status',
            'student'=>'student',
            'erights'=>'erights',
            'is_org'=>'is_org',
            'emeta_id'=>'emeta_id',
            'customer_id'=>'sm_constitid'
        );


        /*
         * The code below is disabled because, we need to check by user_id as we already have it..
         * Checking by constit id can be fatal due to leading zero mismatch
        $constit_id = intval($data['sm_constitid']);
        $qry = $dbr->select('customer','*',
                            array('CONVERT(customer_id, UNSIGNED INTEGER)'=>$constit_id,)
                            );
        */
        $qry = $dbr->select('customer','*',
            array('user_id'=>$user_id,)
        );
        $row = $dbr->fetchRow($qry);
        $this->logDebug("I found the following data for the user in the database: ".print_r($row, true));
        if (!$row)
        {
            /*
             * We have a valid cookie and valid user_id, we will create a customer table row..
             */
            $this->logDebug("Since we don't have a valid customer table row, we will create it. We have a valid cookie and we have a valid user_id");
            $password = SPEUserHelperMethods::Instance()->randomPassword();
            //$dbr->begin();
            $result = $dbr->insert('customer',
                array(
                    'user_id' => $user_id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email_address' => $data['email'],
                    'company_name' => $data['company'],
                    'customer_id' => $data['sm_constitid'],
                    'membership_status' => $data['status'],
                    'country_flag' => '',
                    'password' => SPEUserHelperMethods::Instance()->encryptOpenssl($password, 'des-cfb'),
                    'student' => $data['student'],
                    'erights' => $data['erights'],
                    'is_org' => $data['is_org'],
                    'emeta_id' => $data['emeta_id'],
                )
            );
            if (!$result)
            {
                $this->logDebug('ERROR: Supplemental Table insert failed.. What to do.. do we logout the user at this point of time??');
                Return "Error updating user data. Please contact customer support";
            }
            else
            {
                $this->logDebug("Successfully created record in supplemental table customer for the user_id: $user_id since it wasn't exist");
            }

        }
        else
        {
            $this->logDebug("We have a valid customer table row for the user: $user_id. Checking to see if I need to update the row");
            $ret = '';
            foreach ($customer_map as $key => $value) {
                $this->logdebug("Cookie Data: ".strtolower($data[$value])."   DB value: ".strtolower($row[$key]));
                if (strtolower($data[$value]) !== strtolower($row[$key]))
                {
                    $this->logDebug("We need to update the value from ".$row[$key]. " To ". $data[$value]." for the user $user_id");
                    //How inefficeint update is this?? TO DO..
                    $ret = $dbr->update('customer',
                        array(
                            $key => $data[$value]
                        ),
                        array(
                            'user_id' => $user_id
                        )
                    );
                }
            }
            if (!$ret)
                $this->logDebug("customer table data are up-to-date. Nothing to update for the user: $user_id in customer table");
        }
        /*
         * Work on updating the user groups.
         */
        //get the group that user suppose to be in..
        $userPermissions = $this->determineUserGroup($data, $user_id);
        //Iterate and get rid of user permissions:
        if(is_array($userPermissions) && (count($userPermissions['add']) > 0 || count($userPermissions['remove']) > 0))
        {
            //Send email to the business users if user is a moderator/staff/sysop
            if($wgNotifyBusUserOnPermissionsChanged == true && SPEUserHelperMethods::Instance()->isUserPrivileged($user_id))
            {
                //Prepare the Email Body..
                //$body = "Dear $wgBusinessUserFirstName $wgBusinessUserLastName, \n";
                $body = "The user mentioned below has recently his permissions changed and he is in one of the following privileged groups.
         Please adjust his permission accordingly if default is not appropriate.</br>";
                $body .= "UserName: ".$user->getName() ."</br>";
                $body .= "User First Name: ".$row['first_name'] ."</br>";
                $body .= "User Last Name: ".$row['last_name'] ."</br>";
                $body .= "One of the privileged group user was in: moderator, staff";


                $m = new HtmlMailer($wgBusinessUserEmail, $wgBusinessUserFirstName." ".$wgBusinessUserLastName);
                $transport = $m->getTransport();
                $m->setSubject("User permissions updated")
                    ->addTo($wgBusinessUserEmail)
                    ->setViewParam('message', $body)
                    ->setViewParam('email_to_name', $wgBusinessUserFirstName." ".$wgBusinessUserLastName)
                    ->sendHTMLEmail('templates/EmailTemplate.php', $transport);
                /*
                UserMailer::send(
                    new MailAddress( $wgBusinessUserEmail ),
                    new MailAddress( $wgSiteAdminEmail ),
                    'User permissions updated',
                    $body,
                    new MailAddress( $wgSiteAdminEmail ));
                 */
            }


            if(is_array($userPermissions['remove']) && count($userPermissions['remove']) > 0)
            {
                foreach($userPermissions['remove'] as $key=>$value)
                {
                    $dbr->delete('user_groups', array('ug_user'=>$user_id, 'ug_group'=>$value));
                    // Remember that the user was in this group
                    $dbr->insert( 'user_former_groups', array('ufg_user'  => $user_id, 'ufg_group' => $value,)
                        , __METHOD__, array( 'IGNORE' ));
                    $this->logDebug("Deleted the user group $value and inserted the row in table called 'user_former_group' for the tracking purpose");
                }
            }

            if(is_array($userPermissions['add']) && count($userPermissions['add']) > 0)
            {
                foreach($userPermissions['add'] as $key=>$value)
                {
                    $this->logDebug("We will add the Usergroup: $value since it doesn't exists");
                    $dbr->insert( 'user_groups', array('ug_user'  => $user_id, 'ug_group' => $value,)
                        , __METHOD__);
                }
            }
        }
        else
            $this->logDebug("There is no update to the user permissions. Skipping it.");

        /*
         * We need to update the user_touched in user table. While we are doing that,
         * We are taking an opportunity to check if email address has changed.
         */
        $user_array = array();
        $user_array['user_touched'] = Date('YmdHis');
        $user_array['user_email'] = $data['email'];
        $this->logDebug("User table updates looks like this: ". print_r($user_array, true));

        $result =  $dbr->update( 'user',
            /* SET */ $user_array,
            /* WHERE */ array( 'user_id' => $user_id)
        );

        //Commit the database transactions and return
        //$dbr->commit();
        return "success";
    }
    private function determineUserGroup($data, $user_id)
    {
        global $wgPetroWikiGroupPermissions;
        $dbr = wfGetDB(DB_REPLICA);
        $PetroWikiUserGroups = array('student', 'industry', 'member');
        //Declare return array
        $userPermssionsarr = array();
        $userPermssionsarr['add'] = array();
        $userPermssionsarr['remove'] = array();
        $this->logDebug("Determining the groups that user_id: $user_id suppose to be in.");

        //If student flag is true, we don't need to check anything else..
        if(strtolower($data['student']) == 'true')
            $group= 'student';
        else
        {
            switch(strtolower($data['status']))
            {
                case 'paid':
                    $group= 'member';
                    break;
                case 'unpaid':
                case 'nonmember':
                    $group= 'industry';
                    break;
                case '':
                default:
                    $group= 'industry';
            }
        }
        $this->logDebug("Petrowiki base group for the user: $user_id is: $group");
        //Get the user groups for the user currently in user_groups table
        $res = $dbr->select('user_groups',
            array( 'ug_group' ),
            array('ug_user' => $user_id,)
        );
        $currentGroups = array();
        foreach ( $res as $row )
        {
            $currentGroups[] = $row->ug_group;
        }

        //assign default group
        $wikiPermissionsarr[] = $group;
        if(is_array($wgPetroWikiGroupPermissions[$group]) && count($wgPetroWikiGroupPermissions[$group])> 0)
        {
            foreach($wgPetroWikiGroupPermissions[$group] as $key=>$value)
            {
                if($value && $value === true)
                    $wikiPermissionsarr[] = $key;
            }
        }

        $this->logDebug("User suppose to have following permissions: ".print_r($wikiPermissionsarr, true));
        if(count($currentGroups) > 0)
        {
            $this->logDebug("Current user permissions from database are: ".print_r($currentGroups, true));
            $this->logDebug("We will compare all the permissions and determine which permissions user should have");
            //We need to go through the iteration process as user is not really a new user
            //First we will check if user status has changed since last login
            if(in_array($group, $currentGroups))
            {
                //Check and see if user has 'override' permission assigned. If permission exists, we will return an empty array.
                //and will not update the user permissions
                if(!in_array('override', $currentGroups))
                {
                    $this->logDebug("Iterating first through the permission that needs.. so that if it doesn't exists, we can add it");
                    foreach($wikiPermissionsarr as $key=>$value)
                    {
                        $this->logDebug("Current Value in iteration is: $value");
                        if(in_array($value, $currentGroups))
                        {
                            $this->logDebug("Current iteration $value exists in the currentGroups. Skipping it.");
                            continue;
                        }
                        else
                        {
                            $this->logDebug("Current iteration: $value doesn't exits. Let us add it");
                            $userPermssionsarr['add'][] = $value;
                        }
                    }

                    $this->logDebug("Let us iterate the other way round and check if we need to remove the permissions");
                    foreach($currentGroups as $key=>$value)
                    {
                        $this->logDebug("Current Value in iteration is: $value");
                        if(!in_array($value, $wikiPermissionsarr))
                        {
                            $this->logDebug("Iteration: $value doesn't exists in the permission. Let us remove it..");
                            $userPermssionsarr['remove'][] = $value;
                        }
                        else
                        {
                            $this->logDebug("Current iteration $value exists in the wikiPermissionsarr. Skipping it.");
                            continue;
                        }
                    }
                    $this->logDebug("Finally, we have a list of Add and remove array.. ".print_r($userPermssionsarr, true));
                }
                else
                    $this->logDebug("User has override turned on. We will not touch the user permission");
            }
            else
            {
                //flush all the rights.. including their overrides.
                foreach($currentGroups as $currentGroup)
                {
                    $userPermssionsarr['remove'][] = $currentGroup;
                }
                //Add the user rights for a new group
                foreach($wikiPermissionsarr as $newGroup)
                {
                    $userPermssionsarr['add'][] = $newGroup;
                }
            }
        }
        else
        {
            $this->logDebug("Either we are creating a new user OR permissions are not yet set. Let us set it up");
            //We are either creating a user for the first time OR user doesn't have any groups yet
            if(is_array($wikiPermissionsarr) && count($wikiPermissionsarr) > 0)
            {
                $this->logDebug("Wiki Permission for the Group: $group is: ".print_r($wikiPermissionsarr, true));
                //Add the default petrowiki group..
                foreach($wikiPermissionsarr as $key=>$value)
                {
                    $userPermssionsarr['add'][] = $value;
                }
            }

        }
        $this->logDebug("User group determined from cookie is: ". print_r($userPermssionsarr, true));
        return $userPermssionsarr;
    }
}

