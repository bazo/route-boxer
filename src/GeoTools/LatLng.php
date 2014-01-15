<?php

namespace GeoTools;

/**
 * Description of LatLng
 *
 * @author Martin
 */
class LatLng extends \Geokit\LatLng
{

	public function equals(LatLng $other)
	{
		return $this->getLatitude() === $other->getLatitude() and $this->getLongitude() === $other->getLongitude();
	}


	public function rhumbDestinationPoint($bearing, $distance, $R = 6371)
	{
		$dist = $distance / $R;  //convert dist to angular distance in radians
		$brng = deg2rad($bearing);  // convert to radians
		$lat1 = deg2rad($this->getLatitude());
		$lon1 = deg2rad($this->getLongitude());

		$lat2 = asin(sin($lat1) * cos($dist) + cos($lat1) * sin($dist) * cos($brng));
		$lon2 = $lon1 + atan2(sin($brng) * sin($dist) * cos($lat1), cos($dist) - sin($lat1) * sin($lat2));
		$lon2 = fmod($lon2 + 3 * M_PI, 2 * M_PI) - M_PI;
		$lat2 = rad2deg($lat2);
		$lon2 = rad2deg($lon2);

		return new LatLng($lat2, $lon2);
	}


	public function rhumbBearingTo(LatLng $dest)
	{
		$dLon = deg2rad($dest->getLongitude() - $this->getLongitude());
		$dPhi = log(tan(deg2rad($dest->getLatitude()) / 2 + M_PI / 4) / tan(deg2rad($this->getLatitude()) / 2 + M_PI / 4));
		if (abs($dLon) > M_PI) {
			$dLon = $dLon > 0 ? -(2 * M_PI - $dLon) : (2 * M_PI + $dLon);
		}
		return $this->toBrng(atan2($dLon, $dPhi));
	}


	/**
	 * Normalize a heading in degrees to between 0 and +360
	 *
	 * @return {Number} Return
	 */
	private function toBrng($number)
	{
		return (rad2deg($number) + 360) % 360;
	}


}
