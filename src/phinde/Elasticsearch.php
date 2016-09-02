<?php
namespace phinde;

class Elasticsearch
{
    protected $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function isKnown($url)
    {
        $r = new Elasticsearch_Request(
            $this->baseUrl . 'document/' . rawurlencode($url),
            \HTTP_Request2::METHOD_HEAD
        );
        $r->allow404 = true;
        $res = $r->send();
        return $res->getStatus() == 200;
    }

    public function get($url)
    {
        $r = new Elasticsearch_Request(
            $this->baseUrl . 'document/' . rawurlencode($url),
            \HTTP_Request2::METHOD_GET
        );
        $r->allow404 = true;
        $res = $r->send();
        if ($res->getStatus() != 200) {
            return null;
        }
        $d = json_decode($res->getBody());
        return $d->_source;
    }

    public function markQueued($url)
    {
        $r = new Elasticsearch_Request(
            $this->baseUrl . 'document/' . rawurlencode($url),
            \HTTP_Request2::METHOD_PUT
        );
        $doc = array(
            'status' => 'queued',
            'url' => $url
        );
        $r->setBody(json_encode($doc));
        $r->send();
    }

    public function search($query, $filters, $site, $page, $perPage, $sort)
    {
        if (preg_match('#nick:([^ ]*)#', $query, $matches)) {
            $authorName = $matches[1];
            $query = str_replace(
                'nick:' . $authorName,
                'author.name:' . $authorName,
                $query
            );
        }

        $qMust = array();//query parts for the MUST section

        //modification date filters
        if (preg_match('#after:([^ ]+)#', $query, $matches)) {
            $dateAfter = $matches[1];
            $query      = trim(str_replace($matches[0], '', $query));
            $qMust[]    = array(
                'range' => array(
                    'modate' => array(
                        'gt' => $dateAfter . '||/d',
                    )
                )
            );
        }
        if (preg_match('#before:([^ ]+)#', $query, $matches)) {
            $dateBefore = $matches[1];
            $query      = trim(str_replace($matches[0], '', $query));
            $qMust[]    = array(
                'range' => array(
                    'modate' => array(
                        'lt' => $dateBefore . '||/d',
                    )
                )
            );
        }
        if (preg_match('#date:([^ ]+)#', $query, $matches)) {
            $dateExact = $matches[1];
            $query      = trim(str_replace($matches[0], '', $query));
            $qMust[]    = array(
                'range' => array(
                    'modate' => array(
                        'gte' => $dateExact . '||/d',
                        'lte' => $dateExact . '||/d',
                    )
                )
            );
        }

        $qMust[] = array(
            'query_string' => array(
                'default_field' => '_all',
                'default_operator' => 'AND',
                'query' => $query
            )
        );
        $qMust[] = array(
            'term' => array(
                'status' => 'indexed'
            )
        );

        if ($sort == 'date') {
            $sortCfg = array('modate' => array('order' => 'desc'));
        } else {
            $sortCfg = array();
        }

        $contentMatchSize = 100;
        if ($GLOBALS['phinde']['showFullContent']) {
            $contentMatchSize = 999999;
        }

        $r = new Elasticsearch_Request(
            $this->baseUrl . 'document/_search',
            \HTTP_Request2::METHOD_GET
        );
        $doc = array(
            '_source' => array(
                'url',
                'title',
                'author',
                'modate',
            ),
            'query' => array(
                'bool' => array(
                    'must' => $qMust
                )
            ),
            'highlight' => array(
                'pre_tags' => array('<em class="hl">'),
                'order' => 'score',
                'encoder' => 'html',
                'fields' => array(
                    'title' => array(
                        'require_field_match' => false,
                        'number_of_fragments' => 0,
                    ),
                    'url' => array(
                        'require_field_match' => false,
                        'number_of_fragments' => 0,
                    ),
                    'text' => array(
                        'require_field_match' => false,
                        'number_of_fragments' => 1,
                        'fragment_size' => $contentMatchSize,
                        'no_match_size' => $contentMatchSize,
                    ),
                )
            ),
            'aggregations' => array(
                'tags' => array(
                    'terms' => array(
                        'field' => 'tags'
                    )
                ),
                'language' => array(
                    'terms' => array(
                        'field' => 'language'
                    )
                ),
                'domain' => array(
                    'terms' => array(
                        'field' => 'domain'
                    )
                ),
                'type' => array(
                    'terms' => array(
                        'field' => 'type'
                    )
                )
            ),
            'from' => $page * $perPage,
            'size' => $perPage,
            'sort' => $sortCfg,
        );
        foreach ($filters as $type => $value) {
            $doc['query']['bool']['must'][] = array(
                'term' => array(
                    $type => $value
                )
            );
        }
        if ($site != '') {
            $doc['query']['bool']['must'][] = array(
                'prefix' => array(
                    'schemalessUrl' => array(
                        'value' => $site
                    )
                )
            );
        }

        //unset($doc['_source']);

        //ini_set('xdebug.var_display_max_depth', 10);
        //echo json_encode($doc);die();
        $r->setBody(json_encode($doc));
        $res = $r->send();
        return json_decode($res->getBody());
    }
}
?>
