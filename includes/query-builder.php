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

// Include the Query Constants
include_once(plugin_dir_path(__FILE__) . 'query-constants.php');

class Query_Builder
{

	/**
	 * Gets sales details on all items or vendors
	 */
	public static function get_query_data($start_date, $end_date, $vendor_id, $method)
	{
		// Allow us to query the WordPress Database
		global $wpdb;

		$between_dates = '';
		$user_id = '';

		// If a start and end date were specified
		if($start_date != '' && $end_date != '')
		{
			// Create prepared statements for SQL Query based on dates
			$between_dates = $wpdb->prepare("AND posts.post_date BETWEEN %s AND %s", $start_date, $end_date);
		}

		// If a vendor was specified
		if($vendor_id != 0)
		{
			// Create prepared statements for SQL Query based on vendor
			$user_id = $wpdb->prepare("AND users.id = %d", $vendor_id);
		}

		// Final prepared query to use
		if($method == 'by_product')
		{
			$base_query = Query_Constants::ITEM_DETAILS_QUERY;
		}
		elseif($method == 'by_vendor')
		{
			$base_query = Query_Constants::VENDOR_DETAILS_QUERY;
		}
		elseif($method == 'basic_by_product')
		{
			$base_query = Query_Constants::BASIC_ITEM_DETAILS_QUERY;
		}
		elseif($method == 'by_order')
		{
			$base_query = Query_Constants::ORDER_DETAILS_QUERY;
		}

		// Apply database table prefix
		$base_query = str_replace('wp_', $wpdb->prefix, $base_query);

		$prepared = sprintf(
			$base_query,
			$between_dates,
			$between_dates,
			$user_id
		);

		// Get the results as an array
		$data = $wpdb->get_results($prepared);

		return empty($data) ? null : $data;
	}

}
