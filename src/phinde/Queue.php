<?php
namespace phinde;

class Queue
{
    protected $gmclient;

    public function __construct()
    {
        $this->gmclient = new \GearmanClient();
        $this->gmclient->addServer('127.0.0.1');
        $this->queueName = $GLOBALS['phinde']['queuePrefix'] . 'phinde_process';
    }

    public function addToProcessList($linkUrl, $actions)
    {
        Log::info(
            "Queuing for processing: $linkUrl"
            . ' (' . implode(',', $actions) . ')'
        );

        $this->gmclient->doBackground(
            $this->queueName,
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

    public function getServerStatus()
    {
        $cmd = 'gearadmin --status'
            . '| grep ' . escapeshellarg($this->queueName);
        $line = exec($cmd);

        $parts = preg_split('#\s+#', $line);
        if (count($parts) !== 4) {
            throw new \Exception('gearadmin status line does not have 4 parts');
        }

        return array(
            'tasks'      => $parts[1],
            'processing' => $parts[2],
            'workers'    => $parts[3],
        );
    }
}
?>
