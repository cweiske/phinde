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

$baseLink = '?q=' . urlencode($query);

$filters = array();
if (isset($_GET['filter'])) {
    $allowedFilter = array('domain', 'language', 'tags', 'term');
    foreach ($_GET['filter'] as $type => $value) {
        if (in_array($type, $allowedFilter)) {
            $filters[$type] = filter_var($value, FILTER_SANITIZE_STRING);
        }
    }
}
$activeFilters = array();
foreach ($filters as $type => $value) {
    $activeFilters[$type] = array(
        'label' => $value,
        'removeUrl' => buildLink($baseLink, $filters, $type, null),
    );
}

function buildLink($baseLink, $filters, $addFilterType, $addFilterValue)
{
    if ($addFilterValue === null) {
        if (array_key_exists($addFilterType, $filters)) {
            unset($filters[$addFilterType]);
        }
    } else {
        $filters[$addFilterType] = $addFilterValue;
    }

    $params = http_build_query(array('filter' => $filters));
    if (strlen($params)) {
        return $baseLink . '&' . $params;
    }
    return $baseLink;
}

$es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);
$res = $es->search($query, $filters, $page, $perPage);

$pager = new Html_Pager(
    $res->hits->total, $perPage, $page + 1,
    $baseLink
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

foreach ($res->aggregations as $key => &$aggregation) {
    foreach ($aggregation->buckets as &$bucket) {
        $bucket->url = buildLink($baseLink, $filters, $key, $bucket->key);
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
        'activeFilters' => $activeFilters,
        'pager' => $pager
    )
);
?>
