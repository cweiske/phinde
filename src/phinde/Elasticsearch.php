<?php
namespace phinde;

class Elasticsearch
{
    protected $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public static function getDocId($url)
    {
        return hash('sha256', $url);
    }

    public function isKnown($url)
    {
        $r = new Elasticsearch_Request(
            $this->baseUrl . 'document/' . static::getDocId($url),
            \HTTP_Request2::METHOD_HEAD
        );
        $r->allow404 = true;
        $res = $r->send();
        return $res->getStatus() == 200;
    }

    public function get($url)
    {
        $r = new Elasticsearch_Request(
            $this->baseUrl . 'document/' . static::getDocId($url),
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
            $this->baseUrl . 'document/' . static::getDocId($url),
            \HTTP_Request2::METHOD_PUT
        );
        $doc = (object) array(
            'url' => $url,
            'status' => (object) array(
                'processed' => null,
                'findable'  => false,
            )
        );
        $r->setBody(json_encode($doc));
        $r->send();
    }

    public function getIndexStatus()
    {
        $r = new Elasticsearch_Request(
            $this->baseUrl . '_stats/docs,store',
            \HTTP_Request2::METHOD_GET
        );
        $res = $r->send();
        $data = json_decode($res->getBody());
        return array(
            'documents' => $data->_all->total->docs->count,
            'size'      => $data->_all->total->store->size_in_bytes,
        );
    }

    public function search($query, $filters, $site, $page, $perPage, $sort)
    {
        if (preg_match_all('#nick:([^ ]*)#', $query, $matches)) {
            foreach ($matches[1] as $authorName) {
                $query = str_replace(
                    'nick:' . $authorName,
                    'author.name:' . $authorName,
                    $query
                );
            }
        }

        $qMust = array();//query parts for the MUST section

        //modification date filters
        if (preg_match('#after:([^ ]+)#', $query, $matches)) {
            $dateAfter = $matches[1];
            $query      = trim(str_replace($matches[0], '', $query));
            $qMust[]    = array(
                'range' => array(
                    'status.modate' => array(
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
                    'status.modate' => array(
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
                    'status.modate' => array(
                        'gte' => $dateExact . '||/d',
                        'lte' => $dateExact . '||/d',
                    )
                )
            );
        }

        if (strpos($query, '/') !== false && strpos($query, '"') === false) {
            //add quotes when there is a slash and no quotes
            // https://stackoverflow.com/questions/31963643/escaping-forward-slashes-in-elasticsearch
            $query = '"' . $query . '"';
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
                'status.findable' => true
            )
        );

        if ($sort == '' && $GLOBALS['phinde']['defaultSort'] == 'date') {
            $sort = 'date';
        }
        if ($sort == 'date') {
            $sortCfg = array('status.modate' => array('order' => 'desc'));
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
                'status.modate',
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
