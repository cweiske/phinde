<?php
namespace phinde;
require_once __DIR__ . '/../src/init.php';

\Twig_Autoloader::register();
$GLOBALS['twig'] = new \Twig_Environment(
    new \Twig_Loader_Filesystem(__DIR__ . '/../data/templates'),
    array(
        //'cache' => '/path/to/compilation_cache',
        'debug' => true
    )
);
$GLOBALS['twig']->addExtension(new \Twig_Extension_Debug());


function render($tplname, $vars = array(), $return = false)
{
    if (!isset($vars['htmlhelper'])) {
        //$vars['htmlhelper'] = new HtmlHelper();
    }
    $vars['apptitle'] = 'cweiske.de search';

    $template = $GLOBALS['twig']->loadTemplate($tplname . '.htm');
    if ($return) {
        return $template->render($vars);
    } else {
        echo $template->render($vars);
    }
}
?>
