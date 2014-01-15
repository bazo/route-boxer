<?php

namespace GeoTools;

/**
 * Description of LatLngCollection
 *
 * @author Martin
 */
class LatLngCollection
{

	private $points = [];



	function __construct($points)
	{
		$this->points = $points;
	}


	public function toArray()
	{
		$collection = [];

		foreach ($this->points as $point) {

			if (!$point instanceof LatLng) {
				$lat = $point[0];
				$lon = $point[1];
				$point = new LatLng($lat, $lon);
			}

			$collection[] = $point;
		}

		return $collection;
	}


}
