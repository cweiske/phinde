<?php
namespace phinde;
// web interface to search
require 'www-header.php';

if (!isset($_GET['q'])) {
    exit('no query');
}

$query = $_GET['q'];
$page = 0;
if (isset($_GET['page'])) {
    if (!is_numeric($_GET['page'])) {
        throw new Exception_Input('List page is not numeric');
    }
    //PEAR Pager begins at 1
    $page = (int)$_GET['page'] - 1;
}
$perPage = 10;//$GLOBALS['phinde']['perPage'];

$filters = array();
if (isset($_GET['filter'])) {
    $allowedFilter = array('domain', 'language', 'tags', 'term');
    foreach ($_GET['filter'] as $type => $value) {
        if (in_array($type, $allowedFilter)) {
            $filters[$type] = filter_var($value, FILTER_SANITIZE_STRING);
        }
    }
}

$es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);
$res = $es->search($query, $filters, $page, $perPage);

$pager = new Html_Pager(
    $res->hits->total, $perPage, $page + 1,
    '?q=' . $query
);

foreach ($res->hits->hits as &$hit) {
    $doc = $hit->_source;
    if ($doc->title == '') {
        $doc->title = '(no title)';
    }
    $doc->extra = new \stdClass();
    $doc->extra->cleanUrl = preg_replace('#^.*://#', '', $doc->url);
    if (isset($doc->modate)) {
        $doc->extra->day = substr($doc->modate, 0, 10);
    }
}

$baseLink = '?q=' . urlencode($query);
foreach ($res->aggregations as $key => &$aggregation) {
    foreach ($aggregation->buckets as &$bucket) {
        $bucket->url = $baseLink
            . '&filter[' . urlencode($key) . ']=' . urlencode($bucket->key);
    }
}
//var_dump($res->aggregations);

render(
    'search',
    array(
        'query' => $query,
        'hitcount' => $res->hits->total,
        'hits' => $res->hits->hits,
        'aggregations' => $res->aggregations,
        'pager' => $pager
    )
);
?>
