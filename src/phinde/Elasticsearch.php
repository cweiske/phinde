<?php
namespace phinde;

class Elasticsearch
{
    protected $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @link https://www.elastic.co/guide/en/elasticsearch/guide/current/_finding_exact_values.html
     */
    public function isKnown($url)
    {
        $r = new Elasticsearch_Request(
            $this->baseUrl . 'document/_search/exists',
            \HTTP_Request2::METHOD_GET
        );
        $r->allow404 = true;
        $r->setBody(
            json_encode(
                array(
                    'query' => array(
                        'filtered' => array(
                            'filter' => array(
                                'term' => array(
                                    'url' => $url
                                )
                            )
                        )
                    )
                )
            )
        );
        $res = json_decode($r->send()->getBody());
        return $res->exists;
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

    public function search($query, $filters, $site, $page, $perPage)
    {
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
                    'must' => array(
                        array(
                            'query_string' => array(
                                'default_field' => '_all',
                                'query' => $query
                            )
                        ),
                        array(
                            'term' => array(
                                'status' => 'indexed'
                            )
                        ),
                    )
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
            'sort' => array(
                //array('modate' => array('order' => 'desc'))
            )
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
