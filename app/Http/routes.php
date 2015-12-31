<?php

/** @var Router $router */
use Illuminate\Routing\Router;

$router->get('/', function() {
    return view('welcome');
});

$router->post('search', 'SearchController@search');