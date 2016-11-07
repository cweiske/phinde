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

    public function addToProcessList($linkUrl, $actions)
    {
        echo "Queuing for processing: $linkUrl"
            . ' (' . implode(',', $actions) . ')'
            . "\n";
        $this->gmclient->doBackground(
            $GLOBALS['phinde']['queuePrefix'] . 'phinde_process',
            serialize(
                array(
                    'url'     => $linkUrl,
                    'actions' => $actions,
                )
            )
        );
        if ($this->gmclient->returnCode() != GEARMAN_SUCCESS) {
            echo 'Error queueing URL processing for '
                . $linkUrl . "\n"
                . 'Error code: ' . $this->gmclient->returnCode() . "\n";
            exit(2);
        }
    }
}
?>
