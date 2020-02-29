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

    static $supportedTypes = array(
        'application/atom+xml'  => '\\phinde\\LinkExtractor\\Atom',
        'application/xhtml+xml' => '\\phinde\\LinkExtractor\\Html',
        'text/html'             => '\\phinde\\LinkExtractor\\Html',
    );

    public function __construct()
    {
        $this->es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);
        $this->queue = new Queue();
    }

    public function run(Retrieved $retrieved)
    {
        $linkInfos = $this->extractLinks($retrieved->httpRes);
        $linkInfos = $this->filterLinks($linkInfos);
        if ($this->showLinksOnly) {
            $this->showLinks($linkInfos);
            return false;
        } else {
            $this->enqueue($linkInfos);
            return true;
        }
    }

    protected function extractLinks(\HTTP_Request2_Response $res)
    {
        $mimetype = explode(';', $res->getHeader('content-type'))[0];
        if (!isset(static::$supportedTypes[$mimetype])) {
            Log::info("MIME type not supported for crawling: $mimetype");
            return array();
        }

        $class = static::$supportedTypes[$mimetype];
        $extractor = new $class();
        return $extractor->extract($res);
    }

    protected function filterLinks($linkInfos)
    {
        $filteredLinkInfos = array();
        foreach ($linkInfos as $linkInfo) {
            $linkInfo->url = Helper::rewriteUrl($linkInfo->url);
            $allowed = Helper::isUrlAllowed($linkInfo->url);
            $crawl   = $allowed;
            $index   = $GLOBALS['phinde']['indexNonAllowed'] || $allowed;

            if ($crawl && count($GLOBALS['phinde']['crawlBlacklist'])) {
                foreach ($GLOBALS['phinde']['crawlBlacklist'] as $bl) {
                    if (preg_match('#' . $bl . '#', $linkInfo->url)) {
                        $crawl = false;
                    }
                }
            }

            $linkInfo->known = $this->es->isKnown($linkInfo->url);
            $linkInfo->crawl = $crawl;
            $linkInfo->index = $index;
            $filteredLinkInfos[] = $linkInfo;
        }
        return $filteredLinkInfos;
    }

    protected function enqueue($linkInfos)
    {
        foreach ($linkInfos as $linkInfo) {
            if ($linkInfo->known) {
                continue;
            }
            if ($linkInfo->crawl || $linkInfo->index) {
                $this->es->markQueued($linkInfo->url);
                $actions = array();
                if ($linkInfo->index) {
                    $actions[] = 'index';
                }
                if ($linkInfo->crawl) {
                    $actions[] = 'crawl';
                }
                $this->queue->addToProcessList(
                    $linkInfo->url, $actions
                );
            }
        }
    }

    protected function showLinks($linkInfos)
    {
        foreach ($linkInfos as $linkInfo) {
            Log::msg($linkInfo->url);
            if ($linkInfo->title) {
                Log::msg('   title: ' . $linkInfo->title);
                Log::msg('  source: ' . $linkInfo->source);
                Log::msg(
                    '   known: ' . intval($linkInfo->known)
                    . ', crawl: ' . intval($linkInfo->crawl)
                    . ', index: ' . intval($linkInfo->index)
                );
            }
        }
    }

    public function setShowLinksOnly($showLinksOnly)
    {
        $this->showLinksOnly = $showLinksOnly;
    }
}
?>
