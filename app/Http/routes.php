<?php

/** @var Router $router */
use Illuminate\Routing\Router;

$router->post('search', 'SearchController@search');