<?php

namespace GeoTools;

/**
 * Description of LatLng
 *
 * @author Martin
 */
class LatLng extends \Geokit\LatLng
{
	/**
     * @var float
     */
    private $latitude;

    /**
     * @var float
     */
    private $longitude;
	
	/**
     * @param float $latitude
     * @param float $longitude
     */
    public function __construct($latitude, $longitude)
    {
        $this->latitude  = (float) $latitude;
        $this->longitude = (float) $longitude;
    }
	
	/**
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
    }
	
	public function equals(LatLng $other)
	{
		return $this->latitude === $other->getLatitude() and $this->longitude === $other->getLongitude();
	}
	
	public function rhumbDestinationPoint($bearing, $distance, $R = 6371)
	{
		$dist = $distance / $R;  //convert dist to angular distance in radians
		$brng = deg2rad($bearing);  // convert to radians 
		$lat1 = deg2rad($this->latitude); 
		$lon1 = deg2rad($this->longitude);

		$lat2 = asin(sin($lat1) * cos($dist) + cos($lat1) * sin($dist) * cos($brng) );
		$lon2 = $lon1 + atan2(sin($brng) * sin($dist) * cos($lat1), cos($dist) - sin($lat1) * sin($lat2));
		$lon2 = fmod($lon2 + 3*M_PI, 2*M_PI) - M_PI;
		$lat2 = rad2deg($lat2);
		$lon2 = rad2deg($lon2);
		
		return new LatLng($lat2, $lon2);
	}
	
	public function rhumbBearingTo(LatLng $dest)
	{
		$dLon = deg2rad($dest->getLongitude() - $this->longitude);
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

