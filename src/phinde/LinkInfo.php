<?php
namespace phinde;

class LinkInfo
{
    public $url;
    public $title;
    public $source;

    public function __construct($url, $title = null, $source = null)
    {
        $this->url    = $url;
        $this->title  = $title;
        $this->source = $source;
    }
}
?>
