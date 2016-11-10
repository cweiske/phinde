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
        Log::info(
            "Queuing for processing: $linkUrl"
            . ' (' . implode(',', $actions) . ')'
        );

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
            Log::error(
                'Error queueing URL processing for '
                . $linkUrl . "\n"
                . 'Error code: ' . $this->gmclient->returnCode()
            );
            exit(2);
        }
    }
}
?>
