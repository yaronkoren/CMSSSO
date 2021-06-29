<?php
class consumer extends SpecialPage
{

    public function __construct()
    {
        parent::__construct( 'tracking');
    }


    public function onResourceLoaderGetConfigVars( array &$vars ){
        global $wgCrossSiteSSOProvider, $wgCrossSiteSSOConsumer, $wgSSOConsumerTopLevelDomain;

        $vars['crossDomainSSOVars'] = [
            'wgCrossSiteSSOProvider' => $wgCrossSiteSSOProvider,
            'wgCrossSiteSSOConsumer' => $wgCrossSiteSSOConsumer,
            'wgSSOConsumerTopLevelDomain' => $wgSSOConsumerTopLevelDomain
        ];
    }

    public function execute( $subpage )
    {
        global $wgScriptPath, $wgOut, $wgCrossSiteSSOProvider, $wgCrossSiteSSO, $wgCrossSiteSSOConsumer, $wgSSOConsumerTopLevelDomain;
        $wgOut->addModules( 'ext.CrossDomainSSO' );
        //$out->addScriptFile("$wgScriptPath/extensions/CrossDomainSSO/js/helperFunctions.js");
        if($wgCrossSiteSSO === true)
        {
            if(stristr($wgCrossSiteSSOConsumer, $_SERVER['HTTP_HOST']))
            {
                //Load the script here..
            }
            else if(stristr($wgCrossSiteSSOProvider, $_SERVER['HTTP_HOST']))
            {
                //To Enable EasyXDM, restore the code here
            }
        }

        return true;
    }
}