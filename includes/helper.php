<?php
/*
	Detailed Reports
	Copyright (C) 2017-2018 Leejae Karinja

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Detailed_Reports\Includes;

class Helper
{

	/**
	 * Makes a Multidimensional Array of various Objects into a Multidimensional Array of Arrays
	 */
	public static function as_arrays($array)
	{
		$result = $array;
		array_walk($result, 'self::to_array_of_arrays');
		return $result;
	}

	/**
	 * Recursively cast all Objects in an Array to Arrays
	 */
	private static function to_array_of_arrays(&$item)
	{
		// If the item is an Object
		if(is_object($item))
		{
			// Get an Array of values from the Object
			$item = (array) $item;
		}
		// If the item is an Array (Cast previously or originally an Array)
		if(is_array($item))
		{
			// Recursively set all Objects of the Array to Arrays
			array_walk($item, 'self::to_array_of_arrays');
		}
	}

}
