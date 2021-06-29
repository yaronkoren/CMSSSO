<?php

class helperFunctions
{
    public function getCookieString()
    {
        $encrypted = '';
        global $wgSecretPhrase, $wgIv, $wgSecretKey, $wgEnableTestData, $wgFakeData, $wgWebServiceBaseUrl;
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
        )
        {
            $constitId = $_COOKIE['sm_constitid'];
            if(strlen($constitId) > 0)
            {
                //Get the company name from the REST service.
                $memberDataJsonArray = CustomFunctions::getUserPersonifyData($constitId);
                //We have a real data
                if(strlen($memberDataJsonArray->Company) > 0)
                {
                    $data['company'] = $memberDataJsonArray->Company;
                }
                else
                {
                    //do we want to handle the non-availability of the company
                    $data['company'] = 'Company:unknown';
                }
            }
            else
            {
                //do we want to handle the non-availability of the company
                $data['company'] = 'Company:unknown';
            }

            //$data['company'] = $_COOKIE['company']; //Removed from EMeta Cookie
            $data['email'] = strtolower($_COOKIE['email']);
            $data['emeta_id'] = $_COOKIE['emeta_id'];
            $data['erights'] = $_COOKIE['ERIGHTS'];
            $data['first_name'] = $_COOKIE['first_name'];
            $data['is_org'] = $_COOKIE['is_org'];
            $data['last_name'] = $_COOKIE['last_name'];
            $data['sm_constitid'] = $_COOKIE['sm_constitid'];
            $data['status'] = $_COOKIE['status'];
            $data['student'] = $_COOKIE['student'];
            $token = implode(",", $data);

            $data['secret'] = $token;
            $encrypted = base64_encode(openssl_encrypt(implode("::", $data), 'des-cfb', $wgSecretPhrase, false, $wgIv));
        }
        else if($wgEnableTestData == true)
        {
            $fakeDataArray = $wgFakeData;
            //Provide a fake data
            $constitId = $fakeDataArray['sm_constitid'];
            $memberDataJsonArray = CustomFunctions::getUserPersonifyData($constitId);
            //We have a real data
            if(strlen($memberDataJsonArray->Company) > 0)
            {
                $fakeDataArray['company'] = $memberDataJsonArray->Company;
            }
            else
            {
                //do we want to handle the non-availability of the company
                $fakeDataArray['company'] = 'Company:unknown';
            }

            $token = implode(",", $fakeDataArray);
            $fakeDataArray['secret'] = $token;
            $encrypted = base64_encode(openssl_encrypt(implode("::", $fakeDataArray), 'des-cfb', $wgSecretPhrase, false, $wgIv));
        }
        return $encrypted;
    }

}


?>