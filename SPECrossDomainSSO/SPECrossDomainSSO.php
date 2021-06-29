<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "$IP/extensions/MyExtension/MyExtension.php" );
EOT;
        exit( 1 );
}
 
$wgExtensionCredits['specialpage'][] = array(
        'path' => __FILE__,
        'name' => 'CrossDomainSSO',
        'author' => 'Ankur Shah',
        'url' => 'https://spe.org',
        'descriptionmsg' => 'Extension to Authenticate uses on other domains',
        'version' => '1.0.0',
);

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['consumer'] = $dir . 'crossdomain.php'; # Location of the SpecialMyExtension class (Tell MediaWiki to load this file)
//$wgAutoloadClasses['provider'] = $dir . 'crossdomain.php';
$wgAutoloadClasses['helperFunctions'] = $dir . 'helperFunctions.php';


$wgHooks['BeforePageDisplay'][] = 'consumer::execute';
$wgHooks['ResourceLoaderGetConfigVars'][] = 'consumer::onResourceLoaderGetConfigVars';


$wgExtensionMessagesFiles['CrossDomainSSO'] = $dir . 'CrossDomainSSO.i18n.php'; # Location of a messages file (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['CrossDomainSSOAlias'] = $dir . 'CrossDomainSSO.alias.php'; # Location of an aliases file (Tell MediaWiki to load this file)

//$wgSpecialPages['tracking'] = 'provider'; # Tell MediaWiki about the new special page and its class name
//$wgSpecialPageGroups['tracking'] = 'petrowiki';

$wgResourceModules['ext.CrossDomainSSO'] = array(
        // JavaScript and CSS styles. To combine multiple files, just list them as an array.
        //To Enable EasyXDM, uncomment the following line
        //'scripts' => array('js/easyXDM.js', 'js/json2.js'),
        'scripts' => array('scripts/crossDomainSSO.script.js'),
        //'styles' => array('modules/ext.SponsorsAndAds.advertise.css'),
 
        // When your module is loaded, these messages will be available through mw.msg()
       // 'messages' => array( 'myextension-hello-world', 'myextension-goodbye-world' ),
 
        // If your scripts need code from other modules, list their identifiers as dependencies
        // and ResourceLoader will make sure they're loaded before you.
        // You don't need to manually list 'mediawiki' or 'jquery', which are always loaded.
        'dependencies' => array( 'jquery.cookie'),
 
        // You need to declare the base path of the file paths in 'scripts' and 'styles'
        'localBasePath' => dirname( __FILE__ ),
        // ... and the base from the browser as well. For extensions this is made easy,
        // you can use the 'remoteExtPath' property to declare it relative to where the wiki
        // has $wgExtensionAssetsPath configured:
        'remoteExtPath' => 'CrossDomainSSO',
        //'position' => 'top'
);

