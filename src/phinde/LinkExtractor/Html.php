<?php
namespace phinde\LinkExtractor;

use phinde\LinkInfo;
use phinde\Helper;

class Html
{
    public function extract(\HTTP_Request2_Response $res)
    {
        $url = Helper::removeAnchor($res->getEffectiveUrl());

        $linkInfos = array();

        //FIXME: mime type switch for cdata
        $doc = new \DOMDocument();
        //@ to hide parse warning messages in invalid html
        @$doc->loadHTML($res->getBody());

        //FIXME: extract base url from html
        $base = new \Net_URL2($url);

        $dx = new \DOMXPath($doc);

        $xbase = $dx->evaluate('/html/head/base[@href]')->item(0);
        if ($xbase) {
            $base = $base->resolve(
                $xbase->attributes->getNamedItem('href')->textContent
            );
        }

        $meta = $dx->evaluate('/html/head/meta[@name="robots" and @content]')
            ->item(0);
        if ($meta) {
            $robots = $meta->attributes->getNamedItem('content')->textContent;
            foreach (explode(',', $robots) as $value) {
                if (trim($value) == 'nofollow') {
                    //we shall not follow the links
                    return array();
                }
            }
        }

        $links = $dx->evaluate('//a');
        //FIXME: link rel, img, video

        $alreadySeen = array($url => true);

        foreach ($links as $link) {
            $linkTitle = Helper::sanitizeTitle($link->textContent);
            $href = '';
            foreach ($link->attributes as $attribute) {
                if ($attribute->name == 'href') {
                    $href = $attribute->textContent;
                } else if ($attribute->name == 'rel') {
                    foreach (explode(',', $attribute->textContent) as $value) {
                        if (trim($value) == 'nofollow') {
                            //we shall not follow this link
                            continue 3;
                        }
                    }
                }
            }
            if ($href == '' || $href[0] == '#') {
                //link on this page
                continue;
            }

            $linkUrlObj = $base->resolve($href);
            $linkUrlObj->setFragment(false);
            $linkUrl    = (string) $linkUrlObj;
            if (isset($alreadySeen[$linkUrl])) {
                continue;
            }

            switch ($linkUrlObj->getScheme()) {
            case 'http':
            case 'https':
                break;
            default:
                continue 2;
            }

            //FIXME: check target type
            $linkInfos[] = new LinkInfo(
               $linkUrl, $linkTitle, $url
            );
            $alreadySeen[$linkUrl] = true;
        }

        return $linkInfos;
    }
}
?>
