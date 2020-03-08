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
        $url = Helper::rewriteUrl($url);

        $esDoc  = $this->es->get($url);
        $locUrl = null;
        if (isset($esDoc->status->location)
            && $esDoc->status->location != ''
        ) {
            //Location redirect: Use modified time of known target
            $locUrl = $esDoc->status->location;
            $locUrl = Helper::rewriteUrl($locUrl);
            $esDoc = $this->es->get($locUrl);
        }

        $types = array();
        foreach ($actions as $action) {
            $types = array_merge($types, array_keys($action::$supportedTypes));
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
        $effUrl = Helper::removeAnchor($res->getEffectiveUrl());
        $effUrl = Helper::rewriteUrl($effUrl);

        if ($res->getStatus() === 304) {
            //not modified since last time, so don't crawl again
            if ($locUrl !== null && $effUrl != $locUrl) {
                //location URL changed, and we used the wrong crawl timestampx
                $this->storeRedirect($url, $effUrl);
                return $this->fetch($url, $actions, $force);
            }

            Log::info("Not modified since last fetch");
            return false;
        } else if ($res->getStatus() !== 200) {
            throw new \Exception(
                "Response code is not 200 but "
                . $res->getStatus() . ", stopping"
            );
        }

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
        Log::info("Store $url");
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
