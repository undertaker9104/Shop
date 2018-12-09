<?php

use Illuminate\Routing\Router;

Admin::registerAuthRoutes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {

    $router->get('/', 'HomeController@index');
    $router->get('users', 'UsersController@index');
    $router->get('products', 'ProductController@index');
    $router->get('products/create', 'ProductController@create');
    $router->post('products', 'ProductController@store');
    $router->get('products/{id}/edit', 'productController@edit');
    $router->put('products/{id}', 'productController@update');
});
