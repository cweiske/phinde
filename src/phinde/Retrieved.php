<?php
namespace phinde;

/**
 * Information retrieved by Fetcher
 */
class Retrieved
{
    /**
     * @var \HTTP_Request2_Response
     */
    public $httpRes;

    /**
     * Existing elasticsearch document
     *
     * @var object
     */
    public $esDoc;

    /**
     * URL of document
     */
    public $url;
}
?>
