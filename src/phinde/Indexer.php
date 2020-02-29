<?php
namespace phinde;

class Indexer
{
    static $supportedTypes = array(
        'application/xhtml+xml',
        'text/html',
    );

    public function run(Retrieved $retrieved)
    {
        $res   = $retrieved->httpRes;
        $esDoc = $retrieved->esDoc;
        $url   = $retrieved->url;

        $mimetype = explode(';', $res->getHeader('content-type'))[0];
        if (!in_array($mimetype, static::$supportedTypes)) {
            Log::info("MIME type not supported for indexing: $mimetype");
            return false;
        }

        if ($esDoc === null) {
            $esDoc = Helper::baseDoc($url);
            $retrieved->esDoc = $esDoc;
        }

        //FIXME: update index only if changed since last index time
        //FIXME: extract base url from html
        //FIXME: check if effective url needs updating

        $base = new \Net_URL2($url);

        //FIXME: MIME type switch
        $doc = new \DOMDocument();
        //@ to hide parse warning messages in invalid html
        @$doc->loadHTML($res->getBody());
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
                if (trim($value) == 'noindex') {
                    $esDoc->status->findable = false;
                    return true;
                }
            }
        }

        //remove script tags
        $this->removeTags($doc, 'script');
        $this->removeTags($doc, 'style');
        $this->removeTags($doc, 'nav');

        //default content: <body>
        $xpContext = $doc->getElementsByTagName('body')->item(0);
        //FIXME: follow meta refresh, no body
        // example: https://www.gnu.org/software/coreutils/

        //use microformats content if it exists
        $xpElems = $dx->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' e-content ')]"
        );
        if ($xpElems->length) {
            $xpContext = $xpElems->item(0);
        } else if ($doc->getElementById('content')) {
            //if there is an element with ID "content", we'll use this
            $xpContext = $doc->getElementById('content');
        }

        $esDoc->type = 'html';
        $esDoc->subtype = '';
        $esDoc->mimetype = $mimetype;

        //$esDoc->source = 'FIXME';
        //$esDoc->sourcetitle = 'FIXME';

        $esDoc->author = new \stdClass();

        $arXpElems = $dx->query('/html/head/meta[@name="author" and @content]');
        if ($arXpElems->length) {
            $esDoc->author->name = trim(
                $arXpElems->item(0)->attributes->getNamedItem('content')->textContent
            );
        }
        $arXpElems = $dx->query('/html/head/link[@rel="author" and @href]');
        if ($arXpElems->length) {
            $esDoc->author->url = trim(
                $base->resolve(
                    $arXpElems->item(0)->attributes->getNamedItem('href')->textContent
                )
            );
        }


        $arXpElems = $dx->query('/html/head/title');
        if ($arXpElems->length) {
            $esDoc->title = trim(
                $arXpElems->item(0)->textContent
            );
        }

        foreach (array('h1', 'h2', 'h3', 'h4', 'h5', 'h6') as $headlinetype) {
            $esDoc->$headlinetype = array();
            foreach ($xpContext->getElementsByTagName($headlinetype) as $xheadline) {
                array_push(
                    $esDoc->$headlinetype,
                    trim($xheadline->textContent)
                );
            }
        }

        //FIXME: split paragraphs
        //FIXME: insert space after br
        $esDoc->text = array();
        $esDoc->text[] = trim(
            str_replace(
                array("\r\n", "\n", "\r", '  '),
                ' ',
                $xpContext->textContent
            )
        );

        //tags
        $tags = array();
        foreach ($dx->query('/html/head/meta[@name="keywords" and @content]') as $xkeywords) {
            $keywords = $xkeywords->attributes->getNamedItem('content')->textContent;
            foreach (explode(',', $keywords) as $keyword) {
                $tags[trim($keyword)] = true;
            }
        }
        $esDoc->tags = array_keys($tags);

        //dates
        $arXpdates = $dx->query('/html/head/meta[@name="DC.date.created" and @content]');
        if ($arXpdates->length) {
            $esDoc->status->crdate = gmdate(
                'c',
                strtotime(
                    $arXpdates->item(0)->attributes->getNamedItem('content')->textContent
                )
            );
        }
        //FIXME: keep creation date from database, or use modified date if we
        // do not have it there

        $arXpdates = $dx->query('/html/head/meta[@name="DC.date.modified" and @content]');
        if ($arXpdates->length) {
            $esDoc->status->modate = gmdate(
                'c',
                strtotime(
                    $arXpdates->item(0)->attributes->getNamedItem('content')->textContent
                )
            );
        } else {
            $lm = $res->getHeader('last-modified');
            if ($lm !== null) {
                $esDoc->status->modate = gmdate('c', strtotime($lm));
            } else {
                //use current time since we don't have any other data
                $esDoc->status->modate = gmdate('c');
            }
        }
        $esDoc->status->findable = true;

        //language
        //there may be "en-US" and "de-DE"
        $xlang = $doc->documentElement->attributes->getNamedItem('lang');
        if ($xlang) {
            $esDoc->language = strtolower(substr($xlang->textContent, 0, 2));
        }
        //FIXME: fallback, autodetection
        //FIXME: check noindex

        //var_dump($esDoc);die();

        return true;
    }

    function removeTags($doc, $tag) {
        $elems = array();
        foreach ($doc->getElementsbyTagName($tag) as $elem) {
            $elems[] = $elem;
        }
        foreach ($elems as $elem) {
            $elem->parentNode->removeChild($elem);
        }
    }
}
?>
