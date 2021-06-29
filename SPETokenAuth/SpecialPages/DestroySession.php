<?php
/**
 * Created by PhpStorm.
 * User: ashah
 * Date: 7/22/2018
 * Time: 11:57 AM
 */

class DestroySession extends SpecialPage{

    /*
     * Override the parent constructor not to list this page in to special page list
     */
    function __construct() {
        parent::__construct( $name = 'DestroySession', $restriction = '', $listed = false  );
    }

    function execute( $par ) {
		global $wgEnvironmentUrl;
        /*$request = $this->getRequest();
        $output = $this->getOutput();
        $this->setHeaders();

        # Get request data from, e.g.
        $param = $request->getText( 'param' );

        # Do stuff
        # ...
        $wikitext = 'Hello world!';
        $output->addWikiText( $wikitext );*/

        if (isset($_SERVER['HTTP_COOKIE'])) {
            $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
            foreach($cookies as $cookie) {
                $parts = explode('=', $cookie);
                $name = trim($parts[0]);
                //setcookie($name, '', time()-1000);
                //setcookie($name, '', time()-1000, '/');
                setcookie($name, '', 1);
                setcookie($name, '', 1, '/');
            }
        }
        $queryString = $_SERVER['QUERY_STRING'];
        if(!empty($queryString)){
            parse_str($queryString, $queryStringArray);
        }

        if(is_array($queryStringArray) && count($queryStringArray) > 0)
        {
            if($queryStringArray['id'] == 1){
                $newQueryStringArray = array('id' => 2);
                //Redirect one more time to makse sure that we delete the cookies set back by session
                $urlToRedirectTo = $_SERVER['PHP_SELF']. '?' .  http_build_query($newQueryStringArray);
                header("Location: $urlToRedirectTo");
                die();
            }
            else{
                header("Location: $wgEnvironmentUrl");
                die();
            }
        }
        else{
            header("Location: $wgEnvironmentUrl");
            die();
        }

    }
    /*
     * If we ever decide to display page on Special page (Special:SpecialPages), set $listed = true on parent constructor arguments and then
     * appear link to this special page in "Users and rights" on special page
     */
    function getGroupName() {
        return 'users';
    }
}