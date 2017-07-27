<?php
/*
	Detailed Reports
	Copyright (C) 2017 Leejae Karinja

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

class Exporter
{

	/**
	 * Exports an Array to a CSV file
	 */
	public static function export_csv($array, $file)
	{
		// File stream
		$export_file = fopen($file, 'w');

		// Add data to a new row in the CSV
		foreach($array as $row)
		{
			if(is_object($row))
			{
				fputcsv($export_file, get_object_vars($row));
			}
			else
			{
				fputcsv($export_file, $row);
			}
		}

		// Close the file stream
		fclose($export_file);

		// Send file to client
		if(file_exists($file))
		{
			header('Content-Description: File Transfer');
			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename=' . basename($file));
			header('Pragma: no-cache');
			header('Expires: 0');
			ob_clean();
			flush();
			readfile(realpath($file));
			exit;
		}
	}

}
