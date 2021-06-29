<?php
/**
 * Created by JetBrains PhpStorm.
 * User: ashah
 * Date: 9/19/18
 * Time: 5:58 PM
 * To change this template use File | Settings | File Templates.
 */

class SpeCustomHooks{

    public static function onSkinTemplateOutputPageBeforeExec(&$skin, &$template){
        global $wgStylePath, $wgTitle;
        global $onePetroSearchUrl, $googleScholarSearchUrl, $worldCatSearchUrl, $SEGWikiSearchUrl, $AAPGSearchUrl, $SPESearchUrl;

        $wgFooterIcons = array(
            "onepetro" => array(
                "onepetro" => array(
                    "src" => "$wgStylePath/Common/images/icon_OnePetro_100x100.png", // Defaults to "$wgStylePath/common/images/poweredby_mediawiki_88x31.png"
                    "url" => $onePetroSearchUrl . $wgTitle->mTextform,
                    "alt" => "Search ". $wgTitle->mTextform." on OnePetro",
                    "height"=> 60,
                    "width" => 60
                )
            ),
            "googlescholar" => array(
                "googlescholar" => array(
                    "src" => "$wgStylePath/Common/images/icon_GoogleScholar_100x100.png", // Defaults to "$wgStylePath/common/images/poweredby_mediawiki_88x31.png"
                    "url" => $googleScholarSearchUrl . $wgTitle->mTextform,
                    "alt" => "Search ". $wgTitle->mTextform." on Google Scholar",
                    "height"=> 60,
                    "width" => 60
                )
            ),
            "worldcat" => array(
                "worldcat" => array(
                    "src" => "$wgStylePath/Common/images/icon_WorldCat_100x100.png", // Defaults to "$wgStylePath/common/images/poweredby_mediawiki_88x31.png"
                    "url" => $worldCatSearchUrl . $wgTitle->mTextform,
                    "alt" => "Search ". $wgTitle->mTextform." on WorldCat",
                    "height"=> 60,
                    "width" => 60
                )
            ),
            "seg" => array(
                "seg" => array(
                    "src" => "$wgStylePath/Common/images/icon_SEG_100x100.png", // Defaults to "$wgStylePath/common/images/poweredby_mediawiki_88x31.png"
                    "url" => $SEGWikiSearchUrl . $wgTitle->mTextform,
                    "alt" => "Search ". $wgTitle->mTextform." on SEG Wiki",
                    "height"=> 60,
                    "width" => 60
                )
            ),
            "aapg" => array(
            "aapg" => array(
                "src" => "$wgStylePath/Common/images/icon_AAPG_100x100.png", // Defaults to "$wgStylePath/common/images/poweredby_mediawiki_88x31.png"
                "url" => $AAPGSearchUrl . $wgTitle->mTextform,
                "alt" => "Search ". $wgTitle->mTextform." on AAPG Wiki",
                "height"=> 60,
                "width" => 60
            )
        ),
            "spe" => array(
                "aapg" => array(
                    "src" => "$wgStylePath/Common/images/spe_logo2.png", // Defaults to "$wgStylePath/common/images/poweredby_mediawiki_88x31.png"
                    "url" => $SPESearchUrl . str_replace(" ", "+", $wgTitle->mTextform),
                    "alt" => "Search ". $wgTitle->mTextform." on SPE",
                    "height"=> 52,
                    "width" => 100,
                )
            ),
        );
        $template->set( 'footericons', $wgFooterIcons );

        $template->set('AdvertiseLabel', $skin->footerLink('AdvertiseLabel', 'AdvertisePage'));
        $template->data['footerlinks']['places'][] = 'AdvertiseLabel';

        return true;
    }

    /*
     * Visual editor is not a default editor when you search for a non-existant page and click on it (broken link)
     * It displays the mediawiki default editor. This is a hook to kick-in visual editor.
     * In Mediawiki 1.28+, this hook will not work. We need to implement "HtmlPageLinkRendererBegin" with similar arguments.
     */
    public static function onLinkBegin( $dummy, $target, &$html, &$customAttribs, &$query, &$options, &$ret )
    {
        if ( in_array( 'broken', $options, true ) && empty( $query['action'] ) && $target->getNamespace() !== NS_SPECIAL )
        {
            $query['veaction'] = 'edit';
            $query['action'] = 'view'; // Important! Otherwise MediaWiki will override veaction.
            $query['redlink'] = '1';
        }

        return true;
    }

    public static function onPersonalUrls(array &$personal_urls, Title $title, SkinTemplate $skin){
        if(is_array($personal_urls) && count($personal_urls) > 0){
            if(isset($personal_urls['mytalk']) && isset($personal_urls['mytalk']['text'])){
                $personal_urls['mytalk']['text'] = "Discussion";
            }
        }
    }
}