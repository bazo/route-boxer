<?php

namespace GeoTools;

/**
 * Description of RouteBoxer
 *
 * @author Martin
 */
class RouteBoxer
{

	private $R = 6371; // earth's mean radius in km

	/*
	 * Two dimensional array representing the cells in the grid overlaid on the path
	 */
	private $grid = null;

	/*
	 * Array that holds the latitude coordinate of each vertical grid line
	 */
	private $latGrid = [];

	/*
	 * Array that holds the longitude coordinate of each horizontal grid line
	 */
	private $lngGrid = [];

	/*
	 * Array of bounds that cover the whole route formed by merging cells that
	 * the route intersects first horizontally, and then vertically
	 */
	private $boxesX = [];

	/*
	 *  Array of bounds that cover the whole route formed by merging cells that
	 * the route intersects first vertically, and then horizontally
	 */
	private $boxesY = [];



	/**
	 * Generates boxes for a given route and distance
	 * @param LatLngCollection $collection The array of LatLngs representing the vertices of the path
	 * @param int $range The distance in kms around the route that the generated boxes must cover.
	 * @return array
	 */
	public function box(LatLngCollection $collection, $range)
	{
		$vertices = $collection->toArray();

		// Build the grid that is overlaid on the route
		$this->grid = $this->buildGrid($vertices, $range);

		// Identify the grid cells that the route intersects
		$this->findIntersectingCells($vertices);

		// Merge adjacent intersected grid cells (and their neighbours) into two sets
		//  of bounds, both of which cover them completely
		$this->mergeIntersectingCells();

		// Return the set of merged bounds that has the fewest elements
		return (count($this->boxesX) <= count($this->boxesY) ? $this->boxesX : $this->boxesY);
	}


	/**
	 * Generates boxes for a given route and distance
	 *
	 * @param {LatLng[]} vertices The vertices of the path over which to lay the grid
	 * @param {Number} range The spacing of the grid cells.
	 */
	private function buildGrid($vertices, $range)
	{
		// Create a LatLngBounds object that contains the whole path
		$routeBounds = new LatLngBounds;
		for ($i = 0; $i < count($vertices); $i++) {
			$routeBounds->extend($vertices[$i]);
		}

		// Find the center of the bounding box of the path
		$routeBoundsCenter = $routeBounds->getCenter();

		// Starting from the center define grid lines outwards vertically until they
		//  extend beyond the edge of the bounding box by more than one cell
		array_push($this->latGrid, $routeBoundsCenter->getLatitude());

		// Add lines from the center out to the north
		array_push($this->latGrid, $routeBoundsCenter->rhumbDestinationPoint(0, $range, $this->R)->getLatitude());
		for ($i = 2; $this->latGrid[$i - 2] < $routeBounds->getNorthEast()->getLatitude(); $i++) {
			array_push($this->latGrid, $routeBoundsCenter->rhumbDestinationPoint(0, $range * $i, $this->R)->getLatitude());
		}

		// Add lines from the center out to the south
		for ($i = 1; $this->latGrid[1] > $routeBounds->getSouthWest()->getLatitude(); $i++) {
			array_unshift($this->latGrid, $routeBoundsCenter->rhumbDestinationPoint(180, $range * $i, $this->R)->getLatitude());
		}

		// Starting from the center define grid lines outwards horizontally until they
		//  extend beyond the edge of the bounding box by more than one cell
		array_push($this->lngGrid, $routeBoundsCenter->getLongitude());

		// Add lines from the center out to the east
		array_push($this->lngGrid, $routeBoundsCenter->rhumbDestinationPoint(90, $range, $this->R)->getLongitude());
		for ($i = 2; $this->lngGrid[$i - 2] < $routeBounds->getNorthEast()->getLongitude(); $i++) {
			array_push($this->lngGrid, $routeBoundsCenter->rhumbDestinationPoint(90, $range * $i, $this->R)->getLongitude());
		}

		// Add lines from the center out to the west
		for ($i = 1; $this->lngGrid[1] > $routeBounds->getSouthWest()->getLongitude(); $i++) {
			array_unshift($this->lngGrid, $routeBoundsCenter->rhumbDestinationPoint(270, $range * $i, $this->R)->getLongitude());
		}

		// Create a two dimensional array representing this grid
		$lngGridLength = count($this->lngGrid);
		$latGridLength = count($this->latGrid);

		$grid = $this->createEmptyArray($lngGridLength);
		for ($i = 0; $i < count($grid); $i++) {
			$grid[$i] = $this->createEmptyArray($latGridLength);
		}

		return $grid;
	}


	private function createEmptyArray($length)
	{
		$array = [];
		for ($i = 0; $i <= $length - 1; $i++) {
			$array[$i] = null;
		}

		return $array;
	}


	/**
	 * Find all of the cells in the overlaid grid that the path intersects
	 *
	 * @param {LatLng[]} vertices The vertices of the path
	 */
	private function findIntersectingCells($vertices)
	{
		// Find the cell where the path begins
		$hintXY = $this->getCellCoords($vertices[0]);

		// Mark that cell and it's neighbours for inclusion in the boxes
		$this->markCell($hintXY);

		// Work through each vertex on the path identifying which grid cell it is in
		for ($i = 1; $i < count($vertices); $i++) {
			// Use the known cell of the previous vertex to help find the cell of this vertex
			$gridXY = $this->getGridCoordsFromHint($vertices[$i], $vertices[$i - 1], $hintXY);

			if ($gridXY[0] === $hintXY[0] && $gridXY[1] === $hintXY[1]) {
				// This vertex is in the same cell as the previous vertex
				// The cell will already have been marked for inclusion in the boxes
				continue;
			} else if ((abs($hintXY[0] - $gridXY[0]) === 1 && $hintXY[1] === $gridXY[1]) ||
					($hintXY[0] === $gridXY[0] && abs($hintXY[1] - $gridXY[1]) === 1)) {
				// This vertex is in a cell that shares an edge with the previous cell
				// Mark this cell and it's neighbours for inclusion in the boxes
				$this->markCell($gridXY);
			} else {
				// This vertex is in a cell that does not share an edge with the previous
				//  cell. This means that the path passes through other cells between
				//  this vertex and the previous vertex, and we must determine which cells
				//  it passes through
				$this->getGridIntersects($vertices[$i - 1], $vertices[$i], $hintXY, $gridXY);
			}

			// Use this cell to find and compare with the next one
			$hintXY = $gridXY;
		}
	}


	/**
	 * Find the cell a path vertex is in by brute force iteration over the grid
	 *
	 * @param {LatLng[]} latlng The latlng of the vertex
	 * @return {Number[][]} The cell coordinates of this vertex in the grid
	 */
	private function getCellCoords(LatLng $latlng)
	{
		for ($x = 0; $this->lngGrid[$x] < $latlng->getLongitude(); $x++) {

		}
		for ($y = 0; $this->latGrid[$y] < $latlng->getLatitude(); $y++) {

		}
		return ([$x - 1, $y - 1]);
	}


	/**
	 * Find the cell a path vertex is in based on the known location of a nearby
	 *  vertex. This saves searching the whole grid when working through vertices
	 *  on the polyline that are likely to be in close proximity to each other.
	 *
	 * @param {LatLng[]} latlng The latlng of the vertex to locate in the grid
	 * @param {LatLng[]} hintlatlng The latlng of the vertex with a known location
	 * @param {Number[]} hint The cell containing the vertex with a known location
	 * @return {Number[]} The cell coordinates of the vertex to locate in the grid
	 */
	private function getGridCoordsFromHint(LatLng $latlng, LatLng $hintlatlng, $hint)
	{
		$x = null;
		$y = null;

		if ($latlng->getLongitude() > $hintlatlng->getLongitude()) {
			for ($x = $hint[0]; $this->lngGrid[$x + 1] < $latlng->getLongitude(); $x++) {

			}
		} else {
			for ($x = $hint[0]; $this->lngGrid[$x] > $latlng->getLongitude(); $x--) {

			}
		}

		if ($latlng->getLatitude() > $hintlatlng->getLatitude()) {
			for ($y = $hint[1]; $this->latGrid[$y + 1] < $latlng->getLatitude(); $y++) {

			}
		} else {
			for ($y = $hint[1]; $this->latGrid[$y] > $latlng->getLatitude(); $y--) {

			}
		}

		return [$x, $y];
	}


	/**
	 * Identify the grid squares that a path segment between two vertices
	 * intersects with by:
	 * 1. Finding the bearing between the start and end of the segment
	 * 2. Using the delta between the lat of the start and the lat of each
	 *    latGrid boundary to find the distance to each latGrid boundary
	 * 3. Finding the lng of the intersection of the line with each latGrid
	 *     boundary using the distance to the intersection and bearing of the line
	 * 4. Determining the x-coord on the grid of the point of intersection
	 * 5. Filling in all squares between the x-coord of the previous intersection
	 *     (or start) and the current one (or end) at the current y coordinate,
	 *     which is known for the grid line being intersected
	 *
	 * @param {LatLng} start The latlng of the vertex at the start of the segment
	 * @param {LatLng} end The latlng of the vertex at the end of the segment
	 * @param {Number[]} startXY The cell containing the start vertex
	 * @param {Number[]} endXY The cell containing the vend vertex
	 */
	private function getGridIntersects(LatLng $start, LatLng $end, $startXY, $endXY)
	{
		$brng = $start->rhumbBearingTo($end);   // Step 1.

		$hint = $start;
		$hintXY = $startXY;

		// Handle a line segment that travels south first
		if ($end->getLatitude() > $start->getLatitude()) {
			// Iterate over the east to west grid lines between the start and end cells
			for ($i = $startXY[1] + 1; $i <= $endXY[1]; $i++) {
				// Find the latlng of the point where the path segment intersects with
				//  this grid line (Step 2 & 3)
				$edgePoint = $this->getGridIntersect($start, $brng, $this->latGrid[$i]);

				// Find the cell containing this intersect point (Step 4)
				$edgeXY = $this->getGridCoordsFromHint($edgePoint, $hint, $hintXY);

				// Mark every cell the path has crossed between this grid and the start,
				//   or the previous east to west grid line it crossed (Step 5)
				$this->fillInGridSquares($hintXY[0], $edgeXY[0], $i - 1);

				// Use the point where it crossed this grid line as the reference for the
				//  next iteration
				$hint = $edgePoint;
				$hintXY = $edgeXY;
			}

			// Mark every cell the path has crossed between the last east to west grid
			//  line it crossed and the end (Step 5)
			$this->fillInGridSquares($hintXY[0], $endXY[0], $i - 1);
		} else {
			// Iterate over the east to west grid lines between the start and end cells
			for ($i = $startXY[1]; $i > $endXY[1]; $i--) {
				// Find the latlng of the point where the path segment intersects with
				//  this grid line (Step 2 & 3)
				$edgePoint = $this->getGridIntersect($start, $brng, $this->latGrid[$i]);

				// Find the cell containing this intersect point (Step 4)
				$edgeXY = $this->getGridCoordsFromHint($edgePoint, $hint, $hintXY);

				// Mark every cell the path has crossed between this grid and the start,
				//   or the previous east to west grid line it crossed (Step 5)
				$this->fillInGridSquares($hintXY[0], $edgeXY[0], $i);

				// Use the point where it crossed this grid line as the reference for the
				//  next iteration
				$hint = $edgePoint;
				$hintXY = $edgeXY;
			}

			// Mark every cell the path has crossed between the last east to west grid
			//  line it crossed and the end (Step 5)
			$this->fillInGridSquares($hintXY[0], $endXY[0], $i);
		}
	}


	/**
	 * Find the latlng at which a path segment intersects with a given
	 *   line of latitude
	 *
	 * @param {LatLng} start The vertex at the start of the path segment
	 * @param {Number} brng The bearing of the line from start to end
	 * @param {Number} gridLineLat The latitude of the grid line being intersected
	 * @return {LatLng} The latlng of the point where the path segment intersects
	 *                    the grid line
	 */
	private function getGridIntersect(LatLng $start, $brng, $gridLineLat)
	{
		$d = $this->R * ((deg2rad($gridLineLat) - deg2rad($start->getLatitude()))) / cos(deg2rad($brng));
		return $start->rhumbDestinationPoint($brng, $d);
	}


	/**
	 * Mark all cells in a given row of the grid that lie between two columns
	 *   for inclusion in the boxes
	 *
	 * @param {Number} startx The first column to include
	 * @param {Number} endx The last column to include
	 * @param {Number} y The row of the cells to include
	 */
	private function fillInGridSquares($startx, $endx, $y)
	{
		if ($startx < $endx) {
			for ($x = $startx; $x <= $endx; $x++) {
				$this->markCell([$x, $y]);
			}
		} else {
			for ($x = $startx; $x >= $endx; $x--) {
				$this->markCell([$x, $y]);
			}
		}
	}


	/**
	 * Mark a cell and the 8 immediate neighbours for inclusion in the boxes
	 *
	 * @param {Number[]} square The cell to mark
	 */
	private function markCell($cell)
	{
		$x = $cell[0];
		$y = $cell[1];
		$this->grid[$x - 1][$y - 1] = 1;
		$this->grid[$x][$y - 1] = 1;
		$this->grid[$x + 1][$y - 1] = 1;
		$this->grid[$x - 1][$y] = 1;
		$this->grid[$x][$y] = 1;
		$this->grid[$x + 1][$y] = 1;
		$this->grid[$x - 1][$y + 1] = 1;
		$this->grid[$x][$y + 1] = 1;
		$this->grid[$x + 1][$y + 1] = 1;
	}


	/**
	 * Create two sets of bounding boxes, both of which cover all of the cells that
	 *   have been marked for inclusion.
	 *
	 * The first set is created by combining adjacent cells in the same column into
	 *   a set of vertical rectangular boxes, and then combining boxes of the same
	 *   height that are adjacent horizontally.
	 *
	 * The second set is created by combining adjacent cells in the same row into
	 *   a set of horizontal rectangular boxes, and then combining boxes of the same
	 *   width that are adjacent vertically.
	 *
	 */
	private function mergeIntersectingCells()
	{
		// The box we are currently expanding with new cells
		$currentBox = null;

		// Traverse the grid a row at a time
		for ($y = 0; $y < count($this->grid[0]); $y++) {
			for ($x = 0; $x < count($this->grid); $x++) {

				if ($this->grid[$x][$y]) {
					// This cell is marked for inclusion. If the previous cell in this
					//   row was also marked for inclusion, merge this cell into it's box.
					// Otherwise start a new box.
					$box = $this->getCellBounds([$x, $y]);
					if ($currentBox) {
						$currentBox->extend($box->getNorthEast());
					} else {
						$currentBox = $box;
					}
				} else {
					// This cell is not marked for inclusion. If the previous cell was
					//  marked for inclusion, merge it's box with a box that spans the same
					//  columns from the row below if possible.
					$this->mergeBoxesY($currentBox);
					$currentBox = null;
				}
			}
			// If the last cell was marked for inclusion, merge it's box with a matching
			//  box from the row below if possible.
			$this->mergeBoxesY($currentBox);
			$currentBox = null;
		}

		// Traverse the grid a column at a time
		for ($x = 0; $x < count($this->grid); $x++) {
			for ($y = 0; $y < count($this->grid[0]); $y++) {
				if ($this->grid[$x][$y]) {

					// This cell is marked for inclusion. If the previous cell in this
					//   column was also marked for inclusion, merge this cell into it's box.
					// Otherwise start a new box.
					if ($currentBox) {
						$box = $this->getCellBounds([$x, $y]);
						$currentBox->extend($box->getNorthEast());
					} else {
						$currentBox = $this->getCellBounds([$x, $y]);
					}
				} else {
					// This cell is not marked for inclusion. If the previous cell was
					//  marked for inclusion, merge it's box with a box that spans the same
					//  rows from the column to the left if possible.
					$this->mergeBoxesX($currentBox);
					$currentBox = null;
				}
			}
			// If the last cell was marked for inclusion, merge it's box with a matching
			//  box from the column to the left if possible.
			$this->mergeBoxesX($currentBox);
			$currentBox = null;
		}
	}


	/**
	 * Search for an existing box in an adjacent row to the given box that spans the
	 * same set of columns and if one is found merge the given box into it. If one
	 * is not found, append this box to the list of existing boxes.
	 *
	 * @param {LatLngBounds}  The box to merge
	 */
	private function mergeBoxesX($box)
	{
		if ($box !== null) {
			for ($i = 0; $i < count($this->boxesX); $i++) {
				if ($this->boxesX[$i]->getNorthEast()->getLongitude() === $box->getSouthWest()->getLongitude() &&
						$this->boxesX[$i]->getSouthWest()->getLatitude() === $box->getSouthWest()->getLatitude() &&
						$this->boxesX[$i]->getNorthEast()->getLatitude() === $box->getNorthEast()->getLatitude()) {
					$this->boxesX[$i]->extend($box->getNorthEast());
					return;
				}
			}
			array_push($this->boxesX, $box);
		}
	}


	/**
	 * Search for an existing box in an adjacent column to the given box that spans
	 * the same set of rows and if one is found merge the given box into it. If one
	 * is not found, append this box to the list of existing boxes.
	 *
	 * @param {LatLngBounds}  The box to merge
	 */
	private function mergeBoxesY($box)
	{
		if ($box !== null) {
			for ($i = 0; $i < count($this->boxesY); $i++) {
				if ($this->boxesY[$i]->getNorthEast()->getLatitude() === $box->getSouthWest()->getLatitude() &&
						$this->boxesY[$i]->getSouthWest()->getLongitude() === $box->getSouthWest()->getLongitude() &&
						$this->boxesY[$i]->getNorthEast()->getLongitude() === $box->getNorthEast()->getLongitude()) {
					$this->boxesY[$i]->extend($box->getNorthEast());
					return;
				}
			}
			array_push($this->boxesY, $box);
		}
	}


	/**
	 * Obtain the LatLng of the origin of a cell on the grid
	 *
	 * @param {Number[]} cell The cell to lookup.
	 * @return {LatLng} The latlng of the origin of the cell.
	 */
	private function getCellBounds($cell)
	{
		$southWest = new LatLng($this->latGrid[$cell[1]], $this->lngGrid[$cell[0]]);
		$northEast = new LatLng($this->latGrid[$cell[1] + 1], $this->lngGrid[$cell[0] + 1]);
		return new LatLngBounds($southWest, $northEast);
	}


}
