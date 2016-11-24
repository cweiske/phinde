<?php
namespace phinde;

class HubUrlExtractor
{
    /**
     * HTTP request object that's used to do the requests
     *
     * @var \HTTP_Request2
     */
    protected $request;

    /**
     * Get the hub and self/canonical URL of a given topic URL.
     * Uses link headers and parses HTML link rels.
     *
     * @param string $url Topic URL
     *
     * @return array Array of URLs with keys: hub, self
     */
    public function getUrls($url)
    {
        //at first, try a HEAD request that does not transfer so much data
        $req = $this->getRequest();
        $req->setUrl($url);
        $req->setMethod(\HTTP_Request2::METHOD_HEAD);
        $res = $req->send();

        if (intval($res->getStatus() / 100) >= 4
            && $res->getStatus() != 405 //method not supported/allowed
        ) {
            return null;
        }

        $url  = $res->getEffectiveUrl();
        $base = new \Net_URL2($url);

        $urls = $this->extractHeader($res);
        if (count($urls) === 2) {
            return $this->absolutifyUrls($urls, $base);
        }

        list($type) = explode(';', $res->getHeader('Content-type'));
        if ($type != 'text/html' && $type != 'text/xml'
            && $type != 'application/xhtml+xml'
            //FIXME: atom, rss
            && $res->getStatus() != 405//HEAD method not allowed
        ) {
            //we will not be able to extract links from the content
            return $urls;
        }

        //HEAD failed, do a normal GET
        $req->setMethod(\HTTP_Request2::METHOD_GET);
        $res = $req->send();
        if (intval($res->getStatus() / 100) >= 4) {
            return $urls;
        }

        //yes, maybe the server does return this header now
        // e.g. PHP's Phar::webPhar() does not work with HEAD
        // https://bugs.php.net/bug.php?id=51918
        $urls = array_merge($this->extractHeader($res), $urls);
        if (count($urls) === 2) {
            return $this->absolutifyUrls($urls, $base);
        }

        //FIXME: atom/rss
        $body = $res->getBody();
        $doc = $this->loadHtml($body, $res);

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');

        $nodeList = $xpath->query(
            '/*[self::html or self::h:html]'
            . '/*[self::head or self::h:head]'
            . '/*[(self::link or self::h:link)'
            . ' and'
            . ' ('
            . '  contains(concat(" ", normalize-space(@rel), " "), " hub ")'
            . '  or'
            . '  contains(concat(" ", normalize-space(@rel), " "), " canonical ")'
            . '  or'
            . '  contains(concat(" ", normalize-space(@rel), " "), " self ")'
            . ' )'
            . ']'
        );

        if ($nodeList->length == 0) {
            //topic has no links
            return $urls;
        }

        foreach ($nodeList as $link) {
            $uri  = $link->attributes->getNamedItem('href')->nodeValue;
            $types = explode(
                ' ', $link->attributes->getNamedItem('rel')->nodeValue
            );
            foreach ($types as $type) {
                if ($type == 'canonical') {
                    $type = 'self';
                }
                if ($type == 'hub' || $type == 'self'
                    && !isset($urls[$type])
                ) {
                    $urls[$type] = $uri;
                }
            }
        }

        //FIXME: base href
        return $this->absolutifyUrls($urls, $base);
    }

    /**
     * Extract hub url from the HTTP response headers.
     *
     * @param object $res HTTP response
     *
     * @return array Array with maximal two keys: hub and self
     */
    protected function extractHeader(\HTTP_Request2_Response $res)
    {
        $http = new \HTTP2();

        $urls = array();
        $links = $http->parseLinks($res->getHeader('Link'));
        foreach ($links as $link) {
            if (isset($link['_uri']) && isset($link['rel'])) {
                if (!isset($urls['hub'])
                    && array_search('hub', $link['rel']) !== false
                ) {
                    $urls['hub'] = $link['_uri'];
                }
                if (!isset($urls['self'])
                    && array_search('self', $link['rel']) !== false
                ) {
                    $urls['self'] = $link['_uri'];
                }
            }
        }
        return $urls;
    }

    /**
     * Load a DOMDocument from the given HTML or XML
     *
     * @param string $sourceBody Content of $source URI
     * @param object $res        HTTP response from fetching $source
     *
     * @return \DOMDocument DOM document object with HTML/XML loaded
     */
    protected static function loadHtml($sourceBody, \HTTP_Request2_Response $res)
    {
        $doc = new \DOMDocument();

        libxml_clear_errors();
        $old = libxml_use_internal_errors(true);

        $typeParts = explode(';', $res->getHeader('content-type'));
        $type = $typeParts[0];
        if ($type == 'application/xhtml+xml'
            || $type == 'application/xml'
            || $type == 'text/xml'
        ) {
            $doc->loadXML($sourceBody);
        } else {
            $doc->loadHTML($sourceBody);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($old);

        return $doc;
    }

    /**
     * Returns the HTTP request object clone that can be used
     * for one HTTP request.
     *
     * @return HTTP_Request2 Clone of the setRequest() object
     */
    public function getRequest()
    {
        if ($this->request === null) {
            $request = new \HTTP_Request2();
            $request->setConfig('follow_redirects', true);
            $this->setRequestTemplate($request);
        }

        //we need to clone because previous requests could have
        //set internal variables like POST data that we don't want now
        return clone $this->request;
    }

    /**
     * Sets a custom HTTP request object that will be used to do HTTP requests
     *
     * @param object $request Request object
     *
     * @return self
     */
    public function setRequestTemplate(\HTTP_Request2 $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Make the list of urls absolute
     *
     * @param array  $urls Array of maybe relative URLs
     * @param object $base Base URL to resolve the relatives against
     *
     * @return array List of absolute URLs
     */
    protected function absolutifyUrls($urls, \Net_URL2 $base)
    {
        foreach ($urls as $key => $url) {
            $urls[$key] = (string) $base->resolve($url);
        }
        return $urls;
    }
}
?>
