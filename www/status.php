<?php
namespace phinde;
require 'www-header.php';

$es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);
$esStatus = $es->getIndexStatus();

$queue = new Queue();
$gearStatus = $queue->getServerStatus();

$subDb = new Subscriptions();
$subCount = $subDb->count();

/**
 * @link http://jeffreysambells.com/2012/10/25/human-readable-filesize-php
 */
function human_filesize($bytes, $decimals = 2)
{
    $size = array('B','kiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor))
        . ' ' . @$size[$factor];
}

$esStatus['size_human']      = human_filesize($esStatus['size']);
$esStatus['documents_human'] = number_format(
    $esStatus['documents'], 0, '.', ' '
);

render(
    'status',
    array(
        'esStatus'   => $esStatus,
        'gearStatus' => $gearStatus,
        'subCount'   => $subCount,
        'subSum'     => array_sum($subCount),
    )
);
?>
