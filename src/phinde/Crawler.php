<?php
namespace phinde;

class Crawler
{
    protected $es;
    protected $queue;

    /**
     * If the links only should be shown, not queued
     */
    protected $showLinksOnly = false;

    static $supportedIndexTypes = array(
        'application/atom+xml'  => '\\phinde\\LinkExtractor\\Atom',
        'application/xhtml+xml' => '\\phinde\\LinkExtractor\\Html',
        'text/html'             => '\\phinde\\LinkExtractor\\Html',
    );

    public function __construct()
    {
        $this->es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);
        $this->queue = new Queue();
    }

    public function crawl($url)
    {
        $res = $this->fetch($url);
        if ($res === false) {
            return;
        }

        $linkInfos = $this->extractLinks($res);
        if ($this->showLinksOnly) {
            $this->showLinks($linkInfos);
        } else {
            $this->enqueue($linkInfos);
        }
    }

    protected function fetch($url)
    {
        $existingDoc = $this->es->get($url);

        $req = new HttpRequest($url);
        $req->setHeader(
            'accept',
            implode(',', array_keys(static::$supportedIndexTypes))
        );
        if ($existingDoc) {
            $nMoDate = strtotime($existingDoc->modate);
            $req->setHeader('If-Modified-Since: ' . date('r', $nMoDate));
        }

        $res = $req->send();
        if ($res->getStatus() === 304) {
            //not modified since last time, so don't crawl again
            return false;
        } else if ($res->getStatus() !== 200) {
            throw new \Exception(
                "Response code is not 200 but "
                . $res->getStatus() . ", stopping"
            );
        }
        return $res;
    }

    protected function extractLinks(\HTTP_Request2_Response $res)
    {
        $mimetype = explode(';', $res->getHeader('content-type'))[0];
        if (!isset(static::$supportedIndexTypes[$mimetype])) {
            echo "MIME type not supported for indexing: $mimetype\n";
            return array();
        }

        $class = static::$supportedIndexTypes[$mimetype];
        $extractor = new $class();
        return $extractor->extract($res);
    }

    protected function enqueue($linkInfos)
    {
        foreach ($linkInfos as $linkInfo) {
            if ($this->es->isKnown($linkInfo->url)) {
                continue;
            }
            $allowed = Helper::isUrlAllowed($linkInfo->url);
            $crawl   = $allowed;
            $index   = $GLOBALS['phinde']['indexNonAllowed'] || $allowed;

            if ($crawl || $index) {
                $this->es->markQueued($linkInfo->url);
            }
            if ($index) {
                $this->queue->addToIndex(
                    $linkInfo->url, $linkInfo->title, $linkInfo->source
                );
            }
            if ($allowed) {
                $this->queue->addToCrawl($linkInfo->url);
            }
        }
    }

    protected function showLinks($linkInfos)
    {
        foreach ($linkInfos as $linkInfo) {
            echo $linkInfo->url . "\n";
            if ($linkInfo->title) {
                echo '  title: ' . $linkInfo->title . "\n";
                echo '  source: ' . $linkInfo->source . "\n";
            }
        }
    }

    public function setShowLinksOnly($showLinksOnly)
    {
        $this->showLinksOnly = $showLinksOnly;
    }
}
?>
