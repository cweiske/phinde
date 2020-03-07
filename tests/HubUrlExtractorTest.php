<?php
class HubUrlExtractorTest extends \PHPUnit\Framework\TestCase
{
    public function testGetUrlsHEAD()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: text/html\r\n"
            . "Link: <https://hub.example.com/>; rel=\"hub\"\r\n"
            . "Link: <http://example.com/feed>; rel=\"self\"\r\n"
            . "\r\n",
            'http://example.org/'
        );

        $extractor = new phinde\HubUrlExtractor();
        $extractor->setRequestTemplate(
            new HTTP_Request2(null, null, ['adapter' => $mock])
        );

        $this->assertEquals(
            [
                'hub'  => ['https://hub.example.com/'],
                'self' => 'http://example.com/feed',
            ],
            $extractor->getUrls('http://example.org/')
        );
    }

    public function testGetUrlsMultipleHubsHEAD()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: text/html\r\n"
            . "Link: <https://hub.example.com/>; rel=\"hub\"\r\n"
            . "Link: <https://hub2.example.com/>; rel=\"hub\"\r\n"
            . "Link: <http://example.com/feed>; rel=\"self\"\r\n"
            . "Link: <https://hub3.example.com/>; rel=\"hub\"\r\n"
            . "\r\n",
            'http://example.org/'
        );

        $extractor = new phinde\HubUrlExtractor();
        $extractor->setRequestTemplate(
            new HTTP_Request2(null, null, ['adapter' => $mock])
        );

        $this->assertEquals(
            [
                'hub'  => [
                    'https://hub.example.com/',
                    'https://hub2.example.com/',
                    'https://hub3.example.com/',
                ],
                'self' => 'http://example.com/feed',
            ],
            $extractor->getUrls('http://example.org/')
        );
    }

    public function testGetUrlsHtml()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: text/html\r\n"
            . "\r\n",
            'http://example.org/'
        );
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: text/html\r\n"
            . "\r\n"
            . <<<HTM
<html>
 <head>
  <link rel='hub' href='https://hub.example.com/'/>
  <link rel='self' href='http://example.com/feed'/>
 </head>
</html>
HTM,
            'http://example.org/'
        );

        $extractor = new phinde\HubUrlExtractor();
        $extractor->setRequestTemplate(
            new HTTP_Request2(null, null, ['adapter' => $mock])
        );

        $this->assertEquals(
            [
                'hub'  => ['https://hub.example.com/'],
                'self' => 'http://example.com/feed',
            ],
            $extractor->getUrls('http://example.org/')
        );
    }

    public function testGetUrlsHtmlMultipleHubs()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: text/html\r\n"
            . "\r\n",
            'http://example.org/'
        );
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: text/html\r\n"
            . "\r\n"
            . <<<HTM
<html>
 <head>
  <link rel='hub' href='https://hub.example.com/'/>
  <link rel='hub' href='https://hub2.example.com/'/>
  <link rel='self' href='http://example.com/feed'/>
 </head>
</html>
HTM,
            'http://example.org/'
        );

        $extractor = new phinde\HubUrlExtractor();
        $extractor->setRequestTemplate(
            new HTTP_Request2(null, null, ['adapter' => $mock])
        );

        $this->assertEquals(
            [
                'hub'  => [
                    'https://hub.example.com/',
                    'https://hub2.example.com/',
                ],
                'self' => 'http://example.com/feed',
            ],
            $extractor->getUrls('http://example.org/')
        );
    }

    public function testGetUrlsXHtml()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: application/xhtml+xml\r\n"
            . "\r\n",
            'http://example.org/'
        );
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: application/xhtml+xml\r\n"
            . "\r\n"
            . <<<HTM
<html>
 <head>
  <link rel='hub' href='https://hub.example.com/'/>
  <link rel='self' href='http://example.com/feed'/>
 </head>
</html>
HTM,
            'http://example.org/'
        );

        $extractor = new phinde\HubUrlExtractor();
        $extractor->setRequestTemplate(
            new HTTP_Request2(null, null, ['adapter' => $mock])
        );

        $this->assertEquals(
            [
                'hub'  => ['https://hub.example.com/'],
                'self' => 'http://example.com/feed',
            ],
            $extractor->getUrls('http://example.org/')
        );
    }

    public function testGetUrlsAtom()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: application/atom+xml\r\n"
            . "\r\n",
            'http://example.org/'
        );
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: application/atom+xml\r\n"
            . "\r\n"
            . <<<HTM
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
 <link href="http://example.org/"/>
 <link rel="self" href="http://example.com/feed"/>
 <link rel="hub" href="https://hub.example.com/"/>
</feed>
HTM,
            'http://example.org/'
        );

        $extractor = new phinde\HubUrlExtractor();
        $extractor->setRequestTemplate(
            new HTTP_Request2(null, null, ['adapter' => $mock])
        );

        $this->assertEquals(
            [
                'hub'  => ['https://hub.example.com/'],
                'self' => 'http://example.com/feed',
            ],
            $extractor->getUrls('http://example.org/')
        );
    }

    public function testGetUrlsRss2()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: application/rss+xml\r\n"
            . "\r\n",
            'http://example.org/'
        );
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: application/rss+xml\r\n"
            . "\r\n"
            . <<<HTM
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0">
 <channel>
  <link>http://www.example.com/main.html</link>
  <link rel="self" href="http://example.com/feed"/>
  <link rel="hub" href="https://hub.example.com/"/>
 </channel>
</rss>
HTM,
            'http://example.org/'
        );

        $extractor = new phinde\HubUrlExtractor();
        $extractor->setRequestTemplate(
            new HTTP_Request2(null, null, ['adapter' => $mock])
        );

        $this->assertEquals(
            [
                'hub'  => ['https://hub.example.com/'],
                'self' => 'http://example.com/feed',
            ],
            $extractor->getUrls('http://example.org/')
        );
    }

    public function testGetUrlsRss2WebLinking()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: application/rss+xml\r\n"
            . "\r\n",
            'http://example.org/'
        );
        //HEAD
        $this->addResponse(
            $mock,
            "HTTP/1.0 200 OK\r\n"
            . "Content-type: application/rss+xml\r\n"
            . "\r\n"
            . <<<HTM
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
 <channel>
  <link>http://www.example.com/main.html</link>
  <atom:link rel="self" href="http://example.com/feed"/>
  <atom:link rel="hub" href="https://hub.example.com/"/>
 </channel>
</rss>
HTM,
            'http://example.org/'
        );

        $extractor = new phinde\HubUrlExtractor();
        $extractor->setRequestTemplate(
            new HTTP_Request2(null, null, ['adapter' => $mock])
        );

        $this->assertEquals(
            [
                'hub'  => ['https://hub.example.com/'],
                'self' => 'http://example.com/feed',
            ],
            $extractor->getUrls('http://example.org/')
        );
    }

    protected function addResponse($mock, $responseContent, $effectiveUrl)
    {
        $mock->addResponse(
            static::createResponseFromString($responseContent, $effectiveUrl)
        );
    }

    public static function createResponseFromString($str, $effectiveUrl)
    {
        $parts       = preg_split('!(\r?\n){2}!m', $str, 2);
        $headerLines = explode("\n", $parts[0]);
        $response    = new HTTP_Request2_Response(
            array_shift($headerLines), true, $effectiveUrl
        );
        foreach ($headerLines as $headerLine) {
            $response->parseHeaderLine($headerLine);
        }
        $response->parseHeaderLine('');
        if (isset($parts[1])) {
            $response->appendBody($parts[1]);
        }
        return $response;
    }
}
?>
