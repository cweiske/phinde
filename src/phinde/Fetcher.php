<?php
namespace phinde;

class Fetcher
{
    protected $es;

    public function __construct()
    {
        $this->es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);
    }

    /**
     * @return Retrieved HTTP response and elasticsearch document
     */
    public function fetch($url, $actions, $force = false)
    {
        $esDoc = $this->es->get($url);
        if (isset($esDoc->status->location)
            && $esDoc->status->location != ''
        ) {
            //TODO: what if location redirects change?
            $url = $esDoc->status->location;
            $esDoc = $this->es->get($url);
        }

        $types = array();
        foreach ($actions as $action) {
            $types = array_merge($action::$supportedTypes);
        }
        $types = array_unique($types);

        $req = new HttpRequest($url);
        $req->setHeader('accept', implode(',', $types));
        if (!$force && $esDoc
            && isset($esDoc->status->processed)
            && $esDoc->status->processed != ''
        ) {
            $nCrawlTime = strtotime($esDoc->status->processed);
            $req->setHeader('If-Modified-Since: ' . gmdate('r', $nCrawlTime));
        }

        $res = $req->send();
        if ($res->getStatus() === 304) {
            //not modified since last time, so don't crawl again
            echo "Not modified since last fetch\n";
            return false;
        } else if ($res->getStatus() !== 200) {
            throw new \Exception(
                "Response code is not 200 but "
                . $res->getStatus() . ", stopping"
            );
        }

        $effUrl = Helper::removeAnchor($res->getEffectiveUrl());
        if ($effUrl != $url) {
            $this->storeRedirect($url, $effUrl);
            $url = $effUrl;
            $esDoc = $this->es->get($url);
        }
        //FIXME: etag, hash on content

        $retrieved = new Retrieved();
        $retrieved->httpRes = $res;
        $retrieved->esDoc   = $esDoc;
        $retrieved->url     = $url;
        return $retrieved;
    }

    protected function storeRedirect($url, $target)
    {
        $esDoc = Helper::baseDoc($url);
        $esDoc->status = (object) array(
            'location' => $target,
            'findable' => false,
        );
        $this->storeDoc($url, $esDoc);
    }

    public function storeDoc($url, $esDoc)
    {
        echo "Store $url\n";
        $esDoc->status->processed = gmdate('c');
        $r = new Elasticsearch_Request(
            $GLOBALS['phinde']['elasticsearch'] . 'document/'
            . ElasticSearch::getDocId($url),
            \HTTP_Request2::METHOD_PUT
        );
        $r->setBody(json_encode($esDoc));
        $r->send();
    }
}
?>
