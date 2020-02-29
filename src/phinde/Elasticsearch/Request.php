<?php
namespace phinde;

class Elasticsearch_Request extends \HTTP_Request2
{
    public $allow404 = false;

    public function send()
    {
        $res = parent::send();
        $mainCode = intval($res->getStatus() / 100);
        if ($mainCode === 2) {
            return $res;
        }

        if ($this->allow404 && $res->getStatus() == 404) {
            return $res;
        }
        $js = json_decode($res->getBody());
        if (isset($js->error)) {
            $error = json_encode($js->error);
        } else {
            $error = $res->getBody();
        }

        throw new \Exception(
            'Error in elasticsearch communication at '
            . $this->getMethod() . ' ' . (string) $this->getUrl()
            . ' (status code ' . $res->getStatus() . '): '
            . $error
        );
    }

    /**
     * Sets the request body - inject content type
     *
     * @param mixed $body       Either a string with the body or filename
     *                          containing body or pointer to an open file or
     *                          object with multipart body data
     * @param bool  $isFilename Whether first parameter is a filename
     *
     * @return HTTP_Request2
     * @throws HTTP_Request2_LogicException
     *
     * @link https://www.elastic.co/blog/strict-content-type-checking-for-elasticsearch-rest-requests
     */
    public function setBody($body, $isFilename = false)
    {
        $this->setHeader('content-type', 'application/json');
        return parent::setBody($body, $isFilename);
    }
}
?>
