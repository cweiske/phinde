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

$site = null;
if (preg_match('#site:([^ ]*)#', $query, $matches)) {
    $site = $matches[1];
    $cleanQuery = trim(str_replace('site:' . $site, '', $query));
    $site = Helper::noSchema($site);
} else {
    $cleanQuery = $query;
}

$timeBegin = microtime(true);
$es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);
$res = $es->search($cleanQuery, $filters, $site, $page, $perPage);
$timeEnd = microtime(true);

$pager = new Html_Pager(
    $res->hits->total, $perPage, $page + 1,
    $baseLink
);

foreach ($res->hits->hits as &$hit) {
    $doc = $hit->_source;
    if ($doc->title == '') {
        $doc->htmlTitle = '(no title)';
    }
    if (isset($hit->highlight->title[0])) {
        $doc->htmlTitle = $hit->highlight->title[0];
    } else {
        $doc->htmlTitle = htmlspecialchars($doc->title);
    }
    if (isset($hit->highlight->text[0])) {
        $doc->htmlText = $hit->highlight->text[0];
    } else {
        $doc->htmlText = null;
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

render(
    'search',
    array(
        'queryTime' => round($timeEnd - $timeBegin, 2) . 'ms',
        'query' => $query,
        'cleanQuery' => $cleanQuery,
        'site' => $site,
        'hitcount' => $res->hits->total,
        'hits' => $res->hits->hits,
        'aggregations' => $res->aggregations,
        'activeFilters' => $activeFilters,
        'pager' => $pager
    )
);
?>
