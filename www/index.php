<?php
namespace phinde;
// web interface to search
set_include_path(__DIR__ . '/../src/' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../data/config.php';
require_once 'HTTP/Request2.php';
require_once 'Pager.php';
require_once 'Html/Pager.php';
require_once 'Elasticsearch.php';
require_once 'Elasticsearch/Request.php';
require_once 'functions.php';

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

$es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);
$res = $es->search($query, $page, $perPage);

$pager = new Html_Pager(
    $res->hits->total, $perPage, $page + 1,
    '?q=' . $query
);

foreach ($res->hits->hits as $hit) {
    $doc = $hit->_source;
    if ($doc->title == '') {
        $doc->title = '(no title)';
    }
    echo '<p>'
        . '<a href="' . htmlspecialchars($doc->url) . '">'
        . htmlspecialchars($doc->title)
        . '</a>';
    if (isset($doc->author->name)) {
        echo ' by <a href="' . htmlspecialchars($doc->author->url) . '">'
            . htmlspecialchars($doc->author->name)
            . '</a>';
    }
    echo  '<br/><tt>'
        . htmlspecialchars(preg_replace('#^.*://#', '', $doc->url))
        . '</tt>';
    if (isset($doc->modate)) {
        echo '<br/>Changed: ' . substr($doc->modate, 0, 10);
    }
    echo '</p>';
}

$links = $pager->getLinks();
echo $links['back']
    . ' ' . implode(' ', $links['pages'])
    . ' ' . $links['next'];
//var_dump($links);
var_dump($res->aggregations->domain);
?>
