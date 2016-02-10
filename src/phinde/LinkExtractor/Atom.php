<?php
namespace phinde\LinkExtractor;

use phinde\LinkInfo;

class Atom
{
    public function extract(\HTTP_Request2_Response $res)
    {
        $url  = $res->getEffectiveUrl();
        $base = new \Net_URL2($url);

        $sx = simplexml_load_string($res->getBody());
        $linkInfos   = array();
        $alreadySeen = array();

        foreach ($sx->entry as $entry) {
            $linkTitle = (string) $entry->title;
            foreach ($entry->link as $xlink) {
                $linkUrl = (string) $base->resolve((string) $xlink['href']);
                if (isset($alreadySeen[$linkUrl])) {
                    continue;
                }

                if ($xlink['rel'] == 'alternate') {
                    $linkInfos[] = new LinkInfo($linkUrl, $linkTitle, $url);
                }
                $alreadySeen[$linkUrl] = true;
            }
        }

        return $linkInfos;
    }
}
?>
