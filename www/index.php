<?php
namespace phinde;
// web interface to search
require 'www-header.php';

if (!isset($_GET['q'])) {
    $_GET['q'] = '';
}
$query = trim($_GET['q']);

$page = 0;
if (isset($_GET['page'])) {
    if (!is_numeric($_GET['page'])) {
        throw new Exception_Input('List page is not numeric');
    }
    //PEAR Pager begins at 1
    $page = (int)$_GET['page'] - 1;
}

$perPage = 10;//$GLOBALS['phinde']['perPage'];
$site = null;
$siteParam = false;
$baseLink = '?q=' . urlencode($query);

if (preg_match('#site:([^ ]*)#', $query, $matches)) {
    $site = $matches[1];
    $cleanQuery = trim(str_replace('site:' . $site, '', $query));
    $site = Helper::noSchema($site);
} else if (isset($_GET['site']) && trim(isset($_GET['site'])) != '') {
    $site = trim($_GET['site']);
    $siteParam = true;
    $cleanQuery = $query;
    $baseLink .= '&site=' . urlencode($site);
} else {
    $cleanQuery = $query;
}

if (isset($_GET['sort'])
    && ($_GET['sort'] === 'date' || $_GET['sort'] === 'score')
) {
    $sortMode = $_GET['sort'];
} else {
    $sortMode = $GLOBALS['phinde']['defaultSort'];
}
$sort = $sortMode;
if ($sortMode !== $GLOBALS['phinde']['defaultSort']) {
    $baseLink .= '&sort=' . $sortMode;
}

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

if (preg_match('#site:([^ ]*)#', $query, $matches)) {
    $site = $matches[1];
    $cleanQuery = trim(str_replace('site:' . $site, '', $query));
    $site = Helper::noSchema($site);
    $urlNoSite = buildLink('?q=' . urlencode($cleanQuery), $filters, null, null);
} else {
    $cleanQuery = $query;
    $urlNoSite = null;
}

$timeBegin = microtime(true);
$es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);
$res = $es->search($cleanQuery, $filters, $site, $page, $perPage, $sort);
$timeEnd = microtime(true);

$pager = new Html_Pager(
    $res->hits->total, $perPage, $page + 1,
    $baseLink
);

foreach ($res->hits->hits as &$hit) {
    $doc = $hit->_source;
    if (!isset($doc->title) || $doc->title == '') {
        $doc->title = '(no title)';
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
    if (isset($doc->status->modate)) {
        $doc->extra->day = substr($doc->status->modate, 0, 10);
    }
}

foreach ($res->aggregations as $key => &$aggregation) {
    foreach ($aggregation->buckets as &$bucket) {
        $bucket->url = buildLink($baseLink, $filters, $key, $bucket->key);
    }
}

if ($site !== null) {
    $urlNoSite = buildLink('?q=' . urlencode($cleanQuery), $filters, null, null);
} else {
    $urlNoSite = null;
}

$urlSortBase = buildLink(
    preg_replace('#&sort=[^&]+#', '', $baseLink), $filters, null, null
);
$urlSorts = [];
foreach (['date', 'score'] as $sortMode) {
    if ($sortMode === $GLOBALS['phinde']['defaultSort']) {
        $urlSorts[$sortMode] = $urlSortBase;
    } else {
        $urlSorts[$sortMode] = $urlSortBase . '&sort=' . $sortMode;
    }
}

if (isset($_GET['format']) && $_GET['format'] == 'opensearch') {
    $template = 'opensearch';
    $baseLink .= '&format=opensearch';
    header('Content-type: application/atom+xml');
} else {
    $template = 'search';
}

render(
    $template,
    array(
        'queryTime' => round($timeEnd - $timeBegin, 2) . 's',
        'query' => $query,
        'fullUrl' => Helper::fullUrl($baseLink),
        'cleanQuery' => $cleanQuery,
        'urlNoSite' => $urlNoSite,
        'site' => $site,
        'siteParam' => $siteParam,
        'hitcount' => $res->hits->total,
        'hits' => $res->hits->hits,
        'aggregations' => $res->aggregations,
        'activeFilters' => $activeFilters,
        'pager' => $pager,
        'sort' => $sort,
        'urlSorts' => $urlSorts,
        'hitTemplate' => 'search/' . $GLOBALS['phinde']['hitTemplate'],
    )
);
?>
