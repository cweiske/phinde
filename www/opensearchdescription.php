<?php
namespace phinde;
/**
 * OpenSearch XML description element
 *
 * @link http://www.opensearch.org/
 */
require 'www-header.php';

header('Content-type: application/opensearchdescription+xml');
render(
    'opensearchdescription',
    array(
        'absBaseUrl' => Helper::fullUrl('/'),
    )
);
?>