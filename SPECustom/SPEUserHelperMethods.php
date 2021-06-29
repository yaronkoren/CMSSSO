<?php
/**
 * Created by PhpStorm.
 * User: ashah
 * Date: 6/9/2018
 * Time: 4:18 PM
 */

class SPEUserHelperMethods extends SpecialPage{
    public function __construct()
    {
        parent::__construct('SPEUserHelperMethods');
    }
    public static function Instance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new SPEUserHelperMethods();
        }
        return $inst;
    }
    public function isUserLoggedIn()
    {
        $user = User::newFromSession();
        if($user->isLoggedIn() || !$user->isAnon())
            return true;
        else
            return false;
    }
    public function getUserInfo($userId)
    {
        $dbr = wfGetDB(DB_REPLICA);
        $userInfo = array();

        //Get user permissions as string based on user id
        $user_groups = $dbr->select("user_groups", "ug_group", "ug_user = " . $userId);
        $user_groups_string = "";
        if ($user_groups->numRows() > 0) {
            foreach ($user_groups as $user_group) {
                $user_groups_arr[] = $user_group->ug_group;
            }
            $user_groups_string = implode(", ", $user_groups_arr);
        }

        $tables = array('u' => 'user',
            'cust' => 'customer');

        $variables = array("u.user_id", "u.user_name", "u.user_real_name", "u.user_email", "cust.company_name", "cust.membership_status", "cust.country_flag", "cust.student", "cust.customer_id");
        $conditions = "u.user_id = " . $userId;
        $queryJoin = array(
            "cust" => array("JOIN", "u.user_id = cust.user_id")
        );

        $sql = $dbr->selectSQLText($tables, $variables, $conditions, '', '', $queryJoin);
        $user_data = $dbr->selectRow($tables, $variables, $conditions, '', '', $queryJoin);

        if ($user_data) {
            $userInfo["ussr_id"] = $user_data->user_id;
            $userInfo["user_name"] = $user_data->user_name;
            $userInfo["user_real_name"] = $user_data->user_real_name;
            $userInfo["user_email"] = $user_data->user_email;
            $userInfo["user_company"] = $user_data->company_name;
            $userInfo["user_membership_status"] = $user_data->membership_status;
            $userInfo["user_country"] = $user_data->country_flag;
            $userInfo["user_is_student"] = $user_data->student;
            $userInfo["user_constitId"] = $user_data->customer_id;
            $userInfo["user_permissions"] = $user_groups_string;
        }
        else{
            /*
             * Looks like customer table doesn't have any rows.. Let us get only user data
             */
            $user_data = $dbr->selectRow(
                'user',                                   // $table The table to query FROM (or array of tables)
                array( 'user_id', 'user_name', 'user_real_name', 'user_email' ),            // $vars (columns of the table to SELECT)
                'user_id = '.$userId,                              // $conds (The WHERE conditions)
                __METHOD__,                                   // $fname The current __METHOD__ (for performance tracking)
                null                                        // $options = array()
            );
            if($user_data){
                $userInfo["ussr_id"] = $user_data->user_id;
                $userInfo["user_name"] = $user_data->user_name;
                $userInfo["user_real_name"] = $user_data->user_real_name;
                $userInfo["user_email"] = $user_data->user_email;
            }
        }

        return $userInfo;
    }
    public function randomPassword($length=9, $strength=0) {
        $vowels = 'aeuy';
        $consonants = 'bdghjmnpqrstvz';
        if ($strength & 1) {
            $consonants .= 'BDGHJLMNPQRSTVWXZ';
        }
        if ($strength & 2) {
            $vowels .= "AEUY";
        }
        if ($strength & 4) {
            $consonants .= '23456789';
        }
        if ($strength & 8) {
            $consonants .= '@#$%';
        }

        $password = '';
        $alt = time() % 2;
        for ($i = 0; $i < $length; $i++) {
            if ($alt == 1) {
                $password .= $consonants[(rand() % strlen($consonants))];
                $alt = 0;
            } else {
                $password .= $vowels[(rand() % strlen($vowels))];
                $alt = 1;
            }
        }
        return $password;
    }
    public function findUserIdFromUsername($username){
        $userId = null;
        try {
            $dbr = wfGetDB(DB_REPLICA);
            $userIdFromDb = $dbr->selectField('user',
                array('user_id'),
                array('user_name' => $username,)
            );
            if($userIdFromDb != ''){
                $userId = $userIdFromDb;
            }

        } catch (DBUnexpectedError $e) {
            SPETokenAuthentication::Instance()->logDebug("Database Error occurred in getting users: ".$e->getMessage());
        }
        return $userId;
    }
    public function getClientIP() {
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
    }
    public function encryptOpenssl($string_to_encrypt, $method='des-cfb')
    {
        global $wgSecretPhrase, $wgIv;
        $encrypted = base64_encode(openssl_encrypt($string_to_encrypt, $method, $wgSecretPhrase, false, $wgIv));
        return $encrypted;
    }
    function isUserPrivileged($user_id)
    {
        //Define privileged user groups
        $privilegedUserGroups = array("moderator", "staff", "sysop");
        $returnValue = false;

        $dbr = wfGetDB(DB_REPLICA);
        $res = $dbr->select('user_groups',
            array( 'ug_group' ),
            array('ug_user' => $user_id,)
        );
        $currentGroups = array();
        foreach ( $res as $row )
        {
            if(in_array($row->ug_group, $privilegedUserGroups))
                $returnValue =  true;
        }
        return $returnValue;
    }
    public function userActivityAuditLogLogin($userId)
    {
        $dbw = wfGetDB(DB_MASTER);
        $dataTables = array("u" => "user", "c" => "customer");
        $dataFields = array("c.user_id", "c.customer_id", "c.first_name", "c.last_name", "c.company_name", "c.email_address", "c.student", "c.membership_status");
        $dataCondition = "(u.user_id = " . $userId . ")";
        $dataOptions = '';
        //$options = array('ORDER BY' => 'mp.page_status ASC, u.user_name ASC');
        $dataJoin = array('u' => array(
            'JOIN',
            'u.user_id=c.user_id'
        )
        );

        $customerDataRow = $dbw->selectRow($dataTables, $dataFields, $dataCondition, '', $dataOptions, $dataJoin);
        if (is_object($customerDataRow)) {
            $insertdata['constitId'] = $customerDataRow->customer_id;
            $insertdata['first_name'] = $customerDataRow->first_name;
            $insertdata['last_name'] = $customerDataRow->last_name;
            $insertdata['company'] = $customerDataRow->company_name;
            $insertdata['email'] = $customerDataRow->email_address;
            $insertdata['membership_status'] = $customerDataRow->membership_status;
            $insertdata['student'] = $customerDataRow->student;
            $insertdata['user_id'] = $customerDataRow->user_id;
            $insertdata['logintime'] = date("Y-m-d H:i:s");

            //TODO: geoLocation Data is currently broken.. Disabling it. It was making search very slow since the service that we are using to get the location data was not reachable. We need some reliable service.
            $geoLocationData = array();
            //Get Geolocation data..
            //$ip = $_SERVER['REMOTE_ADDR'];
            //$geoLocationData = CustomFunctions::getGeoLocationData($ip);
            if (is_array($geoLocationData) && count($geoLocationData) > 0) {
                $insertdata['ip'] = $geoLocationData['ip'];
                $insertdata['city'] = $geoLocationData['CityName'];
                $insertdata['state'] = $geoLocationData['RegionName'];
                $insertdata['country'] = $geoLocationData['CountryName'];
            }
            $dbw->insert("user_log", $insertdata);
        }

    }

    /**
     * @param $userId
     * @return bool|mixed
     * @throws DBUnexpectedError
     */
    public function getUserConstiIdFromUserId($userId){
        try {
            $dbr = wfGetDB(DB_REPLICA);
            $constitId = $dbr->selectField('customer',
                array('customer_id'),
                array('user_id' => $userId)
            );
        } catch (DBUnexpectedError $e) {

        }

        return $constitId;
    }
    public function getUserLoginUrl(){
        global $wgHTTPS, $wgLoginUrl, $wgSefUrlStatus, $wgRequest, $wgScriptPath;
        $return_url = '';
        $return_url .= (strtolower($wgHTTPS) == 'on' ? 'https://' : 'http://');
        $title = preg_replace('{/$}', '', $wgRequest->getVal('title', false));
        $titleObject = Title::newFromText($title);
        if (strtolower($title) == strtolower(Title::makeName(NS_SPECIAL, 'UserLogout'))) {
            if (strtolower($wgSefUrlStatus) == 'on')
                $return_url .= $_SERVER['HTTP_HOST'] . $wgScriptPath . '/PetroWiki?rel=50';
            else
                $return_url .= $_SERVER['HTTP_HOST'] . $wgScriptPath . '/index.php?title=PetroWiki&rel=50';
        } else {
            //Remove the url parameters before we redirect to SPE login page..
            /*
            $request_uri = $_SERVER['REQUEST_URI'];
            $pos = strpos($_SERVER['REQUEST_URI'], '?');
            $request_uri = substr($request_uri, 0, $pos);
            $request_uri = $request_uri."?rel=50";
            $return_url .= $_SERVER['HTTP_HOST']. $request_uri;
            */
            if ($titleObject) {
                $return_url = $titleObject->getFullURL();
                $return_url .= "?rel=50";
            }
            else
            {
                $return_url .= $_SERVER['HTTP_HOST'];
                $return_url .= "?rel=50";
            }

        }
        return str_replace('CHANGEME', $return_url, $wgLoginUrl);
    }
}