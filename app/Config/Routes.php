<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Resume::index');
$routes->get('resume/create/(:segment)', 'Resume::builder/$1');
$routes->get('resume/retrieve', 'Resume::retrieve');
$routes->post('resume/retrieve', 'Resume::find');
$routes->post('resume/save', 'Resume::save');
$routes->post('resume/photo', 'Resume::photo');
$routes->post('resume/render', 'Resume::render');
$routes->get('resume/preview/(:segment)', 'Resume::preview/$1');
$routes->get('resume/download/(:segment)', 'Resume::download/$1');
$routes->get('resume/pdf/(:segment)', 'Resume::pdf/$1');
