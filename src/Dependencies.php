<?php

$injector = new \Auryn\Injector;

$injector->alias('Http\Request', 'Http\HttpRequest');
$injector->share('Http\HttpRequest');
$injector->define('Http\HttpRequest', [
    ':get' => $_GET,
    ':post' => $_POST,
    ':cookies' => $_COOKIE,
    ':files' => $_FILES,
    ':server' => $_SERVER,
]);

$injector->alias('Http\Response', 'Http\HttpResponse');
$injector->share('Http\HttpResponse');

$injector->delegate('Twig_Environment', function() use ($injector) {
    $loader = new Twig_Loader_Filesystem(dirname(__DIR__) . '/templates');
    $twig = new Twig_Environment($loader);
    $twig->addExtension(new Twig_Extension_Debug());
    return $twig;
});

$injector->alias('VClass\Templates\Renderer', 'VClass\Templates\TwigRenderer');


$injector->define('VClass\Page\FilePageReader', [
    ':pageFolder' => __DIR__ . '/../pages',
]);

/* Define the database connection */
$injector->define("PDO", [
    ":dsn" => "mysql:host=localhost;charset=utf8;dbname=vclass",
    ":username" => "root",
    ":passwd" => "root",
    ":options" => [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
    ],
]);

$injector->share("PDO");

$injector->alias('VClass\Page\PageReader', 'VClass\Page\FilePageReader');
$injector->share('VClass\Page\FilePageReader');
$injector->alias('VClass\Templates\FrontendRenderer', 'VClass\Templates\FrontendTwigRenderer');
$injector->alias('VClass\Menu\MenuReader', 'VClass\Menu\ArrayMenuReader');
$injector->share('VClass\Menu\ArrayMenuReader');

return $injector;