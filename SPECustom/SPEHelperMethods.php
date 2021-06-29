<?php

/**
 * Created by JetBrains PhpStorm.
 * User: ashah
 * Date: 1/23/13
 * Time: 3:58 PM
 * To change this template use File | Settings | File Templates.
 */
class SPEHelperMethods extends SpecialPage
{
    public function __construct()
    {
        parent::__construct('SPEHelperMethods');
    }

    public static function Instance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new SPEHelperMethods();
        }
        return $inst;
    }

    public function GetUserPersonifyData($constitId)
    {
        global $wgWebServiceBaseUrl, $wgWebServiceUseHttps;
        //Get the company
        $memberServiceTokenUrl =  $this->buildUrl($wgWebServiceBaseUrl, array("nwsext", "rspers", "speencode", $constitId), $wgWebServiceUseHttps, true, true);
        //call the restFul Webservice
        $tokenJson = $this->CurlRestRequest($memberServiceTokenUrl, "GET", "json", array(), 5000);
        $tokenObject = json_decode($tokenJson);

        //check for errors..
        if(strtolower($tokenObject->Status) == 'ok')
        {
            //Get the token
            $token = $tokenObject->EncodedValue;

            if(strlen($token) > 0)
            {
                //Make the call to get the member data
                $memberServiceMemberDataUrl =  $this->buildUrl($wgWebServiceBaseUrl, array("nwsext", "rspers", "spemember", $token), $wgWebServiceUseHttps, true, true);
                $memberDataObject = json_decode($this->CurlRestRequest($memberServiceMemberDataUrl, "GET", "json", array(), 10000));

                //Check if we have a data..
                if(!$memberDataObject->result == 'Error')
                {
                    return $memberDataObject;
                }
            }
        }
    }

    public function buildUrl($baseUrl, $parametersArray, $secured = false, $parameterLessUrl = false, $urlEncodedQueryString = false)
    {
        $completeUrl = "";
        switch($secured)
        {
            case true:
                if(!stristr($baseUrl, 'https://')){
                    $completeUrl .= "https://".$baseUrl;
                }
                else{
                    $completeUrl .= $baseUrl;
                }
                break;
            case false:
            default:
            if(!stristr($baseUrl, 'http://')){
                $completeUrl .= "http://".$baseUrl;
            }
            else{
                $completeUrl .= $baseUrl;
            }
                break;
        }
        if(is_array($parametersArray) && count($parametersArray) > 0)
        {

            //We have parameters..
            if($parameterLessUrl == false)
            {
                //typical php style url building
                if($urlEncodedQueryString == true)
                {
                    $completeUrl .= "/".http_build_query($parametersArray, '', '&amp;');
                }
                else
                {
                    $completeUrl .= "/".http_build_query($parametersArray);
                }
            }
            else
            {
                //We have MVC style url.. We will have to build it on our own
                foreach($parametersArray as $key => $val)
                {
                    //Just get the value..and discard the key
                    $completeUrl .= "/".$val;
                }
            }
        }
        return $completeUrl;
    }

    public function CurlRestRequest($url, $method = "GET", $returnFormat = "json", $data = array(), $requestTimeoutMS = 4000)
    {
        # headers and data (this is API dependent, some uses XML)
        $headers = array(
            'Accept: application/' . strtolower($returnFormat),
            'Content-Type: application/json',
        );

        if(is_array($data) && count($data) > 0)
            $data = json_encode($data);

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_TIMEOUT_MS, $requestTimeoutMS);
        curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($handle, CURLOPT_FAILONERROR, true);
        //curl_setopt($handle, CURLOPT_PROXY, '127.0.0.1:8888');    //Use of fiddler to debug
        //curl_setopt($handle, CURLOPT_VERBOSE, true);      // print debudding info from curl

        switch (strtoupper($method)) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($handle, CURLOPT_POST, true);
                curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
                break;
            case 'PUT':
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
                break;
            case 'DELETE':
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($handle);
        $httpResponseCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        //print_r(curl_getinfo($handle));
        if ($httpResponseCode == 200) {
            return $response;
        } else {
            $errorArray = array("result" => "Error", "curlErrorCode" => curl_errno($handle), "httpResponseCode" => $httpResponseCode, "errorDetails" => curl_error($handle));
            if (strtolower($returnFormat) == 'json') {
                return json_encode($errorArray);
            } else if (strtolower($returnFormat) == 'xml') {
                $xmlData = $this->ArrayToXML($errorArray, "Error");
                return $xmlData;
            }
        }
        curl_close($handle); // close curl session
    }

    public function ArrayToXML($dataArray, $rootNodeName)
    {
        $simpleXMLObject = new SimpleXMLElement("<?xml version=\"1.0\"?><$rootNodeName></$rootNodeName>");

        foreach ($dataArray as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subNode = $simpleXMLObject->addChild("$key");
                    $this->ArrayToXML($value, $subNode);
                } else {
                    $subNode = $simpleXMLObject->addChild("item$key");
                    $this->ArrayToXML($value, $subNode);
                }
            } else {
                $simpleXMLObject->addChild("$key", htmlspecialchars("$value"));
            }
        }
        return $simpleXMLObject->asXML();
    }

    public function EmptyArray(&$array)
    {
        foreach ($array as $i => $value)
        {
            unset($array[$i]);
        }
    }

    public function logoutCurrentUser(){
        try{
            global $wgServer;
            $parametersArray = array();
            $parametersArray["action"] = "logout";
            $parametersArray["format"] = "json";
            $logoutApiUrl = SPEHelperMethods::Instance()->buildUrl($wgServer."/api.php", $parametersArray, false, false, false);
            $result = SPEHelperMethods::Instance()->CurlRestRequest($logoutApiUrl);
            return $result;
        }
        catch(Exception $e){
            return $e->getMessage();
        }

    }

    public function customFunctionsLog($message){

    }
}
