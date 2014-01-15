<?php

$autoload = require __DIR__ . '/../vendor/autoload.php';

$points = [
	[48.167, 17.104],
	[48.399, 17.586],
	[48.908, 18.049],
	[49.22253, 18.734436],
	[48.728115, 21.255798],
];

$collection = new GeoTools\LatLngCollection($points);

$boxer = new GeoTools\RouteBoxer();

$boxes = $boxer->box($collection, 10);

echo count($boxes) === 19 ? 'pass' : 'fail';
echo "\n";

var_dump($boxes);
