<?php
namespace phinde;
require 'www-header.php';

$es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);
$esDocs = $es->countDocuments();

$queue = new Queue();
$gearStatus = $queue->getServerStatus();

render(
    'status',
    array(
        'esDocs' => $esDocs,
        'gearStatus' => $gearStatus,
    )
);
?>
