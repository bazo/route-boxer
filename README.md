route-boxer
===========

PHP implementation of Google Route Boxer

http://gmaps-utility-library-dev.googlecode.com/svn/trunk/routeboxer/src/RouteBoxer.js

## Install

add this line to your composer.json

````
"bazo/geotools" : "v0.1.0"
````

run composer install

## How to use

````

//add all points from calculated route
$points = [
	[48.167, 17.104],
	[48.399, 17.586],
	[48.908, 18.049],
	[49.22253, 18.734436],
	[48.728115, 21.255798],
];

$collection = new GeoTools\LatLngCollection($points);

$boxer = new GeoTools\RouteBoxer();

//calculate boxes with 10km distance from the line between points
$boxes = $boxer->box($collection, $distance = 10);

//boxes now contain an array of LatLngBounds
````

Enjoy
