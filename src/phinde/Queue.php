<?php
namespace phinde;

class Queue
{
    protected $gmclient;

    public function __construct()
    {
        $this->gmclient = new \GearmanClient();
        $this->gmclient->addServer('127.0.0.1');
    }

    public function addToIndex($linkUrl, $linkTitle, $sourceUrl)
    {
        echo "Queuing for indexing: $linkUrl\n";
        $this->gmclient->doBackground(
            $GLOBALS['phinde']['queuePrefix'] . 'phinde_index',
            serialize(
                array(
                    'url'    => $linkUrl,
                    'title'  => $linkTitle,
                    'source' => $sourceUrl
                )
            )
        );
        if ($this->gmclient->returnCode() != GEARMAN_SUCCESS) {
            echo 'Error queueing URL indexing for '
                . $linkUrl . "\n"
                . 'Error code: ' . $this->gmclient->returnCode() . "\n";
            exit(2);
        }
    }

    public function addToCrawl($linkUrl)
    {
        echo "Queuing for crawling: $linkUrl\n";
        $this->gmclient->doBackground(
            $GLOBALS['phinde']['queuePrefix'] . 'phinde_crawl',
            serialize(
                array(
                    'url' => $linkUrl
                )
            )
        );
        if ($this->gmclient->returnCode() != GEARMAN_SUCCESS) {
            echo 'Error queueing URL crawling for '
                . $linkUrl . "\n"
                . 'Error code: ' . $this->gmclient->returnCode() . "\n";
            exit(2);
        }
    }
}
?>
