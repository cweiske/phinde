<?php
namespace phinde;

class HttpRequest extends \HTTP_Request2
{
    public function __construct($url = null, $method = 'GET')
    {
        parent::__construct($url, $method);
        $this->setConfig('follow_redirects', true);
        $this->setConfig('connect_timeout', 5);
        $this->setConfig('timeout', 10);
        $this->setConfig('ssl_verify_peer', false);
        $this->setHeader('user-agent', 'phinde/bot');
    }
}
?>
