<?php
namespace phinde;

class Crawler
{
    protected $es;
    protected $queue;

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
        $res       = $this->fetch($url);
        $linkInfos = $this->extractLinks($res);
        $this->enqueue($linkInfos);
    }

    protected function fetch($url)
    {
        $req = new HttpRequest($url);
        $res = $req->send();
        if ($res->getStatus() !== 200) {
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
            $this->es->markQueued($linkInfo->url);
            $this->queue->addToIndex(
                $linkInfo->url, $linkInfo->title, $linkInfo->source
            );
            if (Helper::isUrlAllowed($linkInfo->url)) {
                $this->queue->addToCrawl($linkInfo->url);
            }
        }
    }
}
?>
