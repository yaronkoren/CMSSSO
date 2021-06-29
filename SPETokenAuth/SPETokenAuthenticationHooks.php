<?php
/**
 * Created by PhpStorm.
 * User: ashah
 * Date: 6/9/2018
 * Time: 3:29 PM
 */

class SPETokenAuthenticationHooks extends SpecialPage {

    public static function onUserLoadAfterLoadFromSession($user){
        global $wgUser, $wgRequest, $data, $wgOut, $wgAuthRedirect, $wgAuth;
        $context = RequestContext::getMain();
        $title = $context->getTitle();

        if(strtolower($wgAuthRedirect) != 'on'){
            SPETokenAuthentication::Instance()->logDebug("SSO is turned off. Exiting the SSO code to allow the local authentication.");
            return true;
        }
        else if(!$title){
            SPETokenAuthentication::Instance()->logDebug("Bad wgRequest object. Missing Title. Exiting the SSO code. The wgRequest object is: ");
            return true;
        }
        if (  strtolower($title) == strtolower(Title::makeName( NS_SPECIAL, 'UserLogout' ))  || strtolower($title) == strtolower(Title::makeName( NS_SPECIAL, 'UserLogin' ))){
            SPETokenAuthentication::Instance()->logDebug("The user is on page: $title. Exiting the SSO Code");
            return true;
        }
        else{
            SPETokenAuthentication::Instance()->logDebug("The user is on page: $title, SSO is also turned on and as we have a valid wgRequest, Entering the SSO Code");
            $data = SPETokenAuthentication::Instance()->getDataAraay();
            if(is_array($data) && count($data) == 10){
                if(!SPEUserHelperMethods::Instance()->isUserLoggedIn()){
                    SPETokenAuthentication::Instance()->logDebug("Member is not logged in. Let us log him in");
                    $userId = SPETokenAuthentication::Instance()->findUser($data);
                    if(!$userId || $userId == '')
                    {
                        SPETokenAuthentication::Instance()->logDebug("I couldn't locate the user in the database. Need to create one from the cookie");
                        //we will create a user for now.
                        $username = SPETokenAuthentication::Instance()->getUserNameForNewUser($data);
                        $realName = ucwords(strtolower($data['first_name']))." ".ucwords(strtolower($data['last_name']));
                        $email = $data['email'];
                        $userId = null;
                        SPETokenAuthentication::Instance()->authenticate($userId, $username, $realName, $email);
                        return true;
                    }
                    else{
                        //$username = SPETokenAuthentication::Instance()->getUserNameForNewUser($data);
                        $username = "Ankurshah";
                        $realName = "Ankur Shah";
                        $email = "ashah@spe.org";
                        //$realName = ucwords(strtolower($data['first_name']))." ".ucwords(strtolower($data['last_name']));
                        //$email = $data['email'];
                        if(!$wgUser->isLoggedIn())
                        {
                            SPETokenAuthentication::Instance()->authenticate($userId, $username, $realName, $email);
                        }


                    }

                }
            }
        }
        return true;
    }

    public static function onAuthRemoteuserFilterUserName(&$usernameRef){
        global $wgUser, $wgRequest, $data, $wgOut, $wgAuthRedirect, $wgAuth;
        $dbr = wfGetDB(DB_REPLICA);
        $data = SPETokenAuthentication::Instance()->getDataAraay();
        if(is_array($data) && count($data) == 10){
            if(!SPEUserHelperMethods::Instance()->isUserLoggedIn()){
                SPETokenAuthentication::Instance()->logDebug("Member is not logged in. Let us log him in");
                $userId = SPETokenAuthentication::Instance()->findUser($data);
                if(!$userId || $userId == '')
                {
                    //we couldn't locate the user in database and will create a new user now
                    SPETokenAuthentication::Instance()->logDebug("I couldn't locate the user in the database. Need to create one from the cookie");
                    $usernameRef = SPETokenAuthentication::Instance()->getUserNameForNewUser($data);
                    SPETokenAuthentication::Instance()->logDebug("Creating the new user for the customer id:".$data['sm_constitid'].". The username is: $usernameRef");
                    $userId = null;
                    /*
                     *  At this point we have referenced user to be cretaed and handing over to Auth_RemoteUser to create and login user.
                     *  Inserting the customer data and audit data log is being taken care of by hook method onUserLoggedIn so no need to do it here.
                     */
                }
                else{
                    //we know that user exists and we have a valid cookie
                    //TODO: Instead of returning $userData as an array, it will be much easier if we return an object so that we can use strong typings
                    $userData = SPEUserHelperMethods::Instance()->getUserInfo($userId);
                    SPETokenAuthentication::Instance()->logDebug(sprintf("I found the user in the database and user id is:%d and user name is: %s",$userData["ussr_id"], $userData["user_name"]));
                    if(is_array($userData) && count($userData) > 0 && !empty($userData["user_name"])){
                        $usernameRef = $userData["user_name"];
                        /*
                         *  At this point we have referenced existing user to be logged in and handing over to Auth_RemoteUser to login a user.
                         *  Updating the customer data and audit data log is being taken care of by hook method onUserLoggedIn so no need to do it here.
                         */
                    }
                }

            }
            else{
                //Someone is logged in. Let us logout that user
                $currentUserId = $wgUser->mId;

            }
        }

        if($usernameRef == "TotalJungle1"){
                return false;
        }

        return true;
    }

    public static function onUserLoggedIn(User $user){
        if($user->mId != null){
            global $wgLoginAuditEnabled;
            $data = SPETokenAuthentication::Instance()->getDataAraay();
            $result = SPETokenAuthentication::Instance()->addOrUpdateCustomerData($user->mId, $data);
            if($wgLoginAuditEnabled === true){
                SPEUserHelperMethods::Instance()->userActivityAuditLogLogin($user->mId);
            }
        }
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

    public static function onLocalUserCreated(User $user, $autocreated){
        try {
            $data = SPETokenAuthentication::Instance()->getDataAraay();
            $password = SPEUserHelperMethods::Instance()->randomPassword(20, 8);
            $realName = ucwords(strtolower($data['first_name']))." ".ucwords(strtolower($data['last_name']));
            $email = $data['email'];
            if($autocreated === true){
                $status = $user->changeAuthenticationData( [
                    'username' => $user->getName(),
                    'password' => $password,
                    'retype' => $password,
                ] );
                if ( !$status->isGood() ) {
                    throw new PasswordError( $status->getWikiText( null, null, 'en' ) );
                }
                $user->setRealName($realName);
                $user->setEmail($email);
                $user->setEmailAuthenticationTimestamp(wfTimestamp( TS_MW ));
                try {
                    $user->saveSettings();
                } catch (MWException $e) {
                    //TODO: Handle mwException
                }
                SPETokenAuthentication::Instance()->addOrUpdateCustomerData($user->mId, $data);
            }
        } catch ( PasswordError $pwe ) {
            //TODO: Handle password change error
        }

    }

    public static function onPersonalUrls( array &$personal_urls, Title $title, SkinTemplate $skin )
    {
        $loginUrl = SPEUserHelperMethods::Instance()->getUserLoginUrl();
        if(is_array($personal_urls["login"]) && count($personal_urls["login"]) > 0)
        {
            $personal_urls["login"]["href"] = $loginUrl;
        }

    }


}