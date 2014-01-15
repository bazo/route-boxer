<?php

namespace GeoTools;

use Geokit\Util;



/**
 * Description of LatLng
 *
 * @author Martin
 */
class LatLngBounds
{

	/**
	 * south-west cornorthEastr
	 * @var float
	 */
	private $southWest;

	/**
	 * north-east cornorthEastr
	 * @var float
	 */
	private $northEast;



	/**
	 * Constructs a rectangle from the points at its south-west and north-east cornorthEastrs.
	 * @param float $southWest
	 * @param float $northEast
	 */
	function __construct(LatLng $southWest = null, LatLng $northEast = null)
	{
		$this->southWest = $southWest;
		$this->northEast = $northEast;
	}


	/**
	 * Returns true if the given lat/lng is in this bounds.
	 * @param LatLng $point
	 * @return boolean
	 */
	public function contains(LatLng $point)
	{
		// check latitude
		if ($this->southWest->getLatitude() > $point->getLatitude() ||
				$point->getLatitude() > $this->northEast->getLatitude()) {

			return false;
		}

		// check longitude
		return $this->containsLng($point->getLongitude());
	}


	/**
	 * Returns whether or not the given line of longitude is inside the bounds.
	 *
	 * @param float $lng
	 * @return boolean
	 */
	protected function containsLng($lng)
	{
		if ($this->crossesAntimeridian()) {
			return $lng <= $this->northEast->getLongitude() ||
					$lng >= $this->southWest->getLongitude();
		} else {
			return $this->southWest->getLongitude() <= $lng &&
					$lng <= $this->northEast->getLongitude();
		}
	}


	/**
	 * Returns true if this bounds approximately equals the given bounds.
	 * @param LatLngBounds $other
	 * @return boolean
	 */
	public function equals(LatLngBounds $other)
	{
		return true;
	}


	/**
	 * Extends this bounds to contain the given point.
	 * @param LatLng $point
	 * @return \LatLngBounds
	 */
	public function extend(LatLng $point)
	{

		if ($this->northEast !== null) {

			$newSouth = min($this->southWest->getLatitude(), $point->getLatitude());
			$newNorth = max($this->northEast->getLatitude(), $point->getLatitude());

			$newWest = $this->southWest->getLongitude();
			$newEast = $this->northEast->getLongitude();

			if (!$this->containsLng($point->getLongitude())) {
				// try extending east and try extending west, and use the one that
				// has the smaller longitudinal span
				$extendEastLngSpan = $this->lngSpan($newWest, $point->getLongitude());
				$extendWestLngSpan = $this->lngSpan($point->getLongitude(), $newEast);

				if ($extendEastLngSpan <= $extendWestLngSpan) {
					$newEast = $point->getLongitude();
				} else {
					$newWest = $point->getLongitude();
				}
			}

			$this->southWest = new LatLng($newSouth, $newWest);
			$this->northEast = new LatLng($newNorth, $newEast);
		} else {
			//bound has no coordinates
			$this->southWest = $this->northEast = $point;
		}

		return $this;
	}


	/**
	 * Gets the longitudinal span of the given west and east coordinates.
	 *
	 * @param float $west
	 * @param float $east
	 * @return float
	 */
	protected function lngSpan($west, $east)
	{
		return ($west > $east) ? ($east + 360 - $west) : ($east - $west);
	}


	/**
	 * Computes the center of this LatLngBounds
	 * @return \LatLng
	 */
	public function getCenter()
	{
		if ($this->crossesAntimeridian()) {
			$span = $this->lngSpan($this->southWest->getLongitude(), $this->northEast->getLongitude());
			$lng = Util::normalizeLng($this->southWest->getLongitude() + $span / 2);
		} else {
			$lng = ($this->southWest->getLongitude() + $this->northEast->getLongitude()) / 2;
		}

		return new LatLng(
				($this->southWest->getLatitude() + $this->northEast->getLatitude()) / 2, $lng
		);
	}


	/**
	 * @return boolean
	 */
	public function crossesAntimeridian()
	{
		return $this->southWest->getLongitude() > $this->northEast->getLongitude();
	}


	/**
	 * Returns the north-east cornorthEastr of this bounds
	 * @return \LatLng
	 */
	public function getNorthEast()
	{
		return $this->northEast;
	}


	/**
	 * Returns the south-west cornorthEastr of this bounds
	 * @return \LatLng
	 */
	public function getSouthWest()
	{
		return $this->southWest;
	}


	/**
	 * Returns true if this bounds shares any points with this bounds
	 * @param LatLngBounds $other
	 * @return boolean
	 */
	public function intersects(LatLngBounds $other)
	{
		return true;
	}


	/**
	 * Extends this bounds to contain the union of this and the given bounds.
	 * @param LatLngBounds $other
	 * @return \LatLngBounds
	 */
	public function union(LatLngBounds $bounds)
	{
		$this->extendByLatLng($bounds->getSouthWest());
		$this->extendByLatLng($bounds->getNorthEast());

		return $this;
	}


	/**
	 * Returns if the bounds are empty
	 * @return boolean
	 */
	public function isEmpty()
	{
		return true;
	}


	/**
	 * Converts the given map bounds to a lat/lng span.
	 * @return \LatLng
	 */
	public function toSpan()
	{
		return new LatLng(
				$this->northEast->getLatitude() - $this->southWest->getLatitude(), $this->lngSpan($this->southWest->getLongitude(), $this->northEast->getLongitude())
		);
	}


}
