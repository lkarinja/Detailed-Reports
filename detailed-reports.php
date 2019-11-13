<?php
/*
	Plugin Name: WooCommerce/WC-Vendors Detailed Reports
	Description: Generates detailed sales reports
	Version: 1.6.0
	Author: <a href="https://github.com/lkarinja">Leejae Karinja</a>
	License: GPL3
	License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

/*
	Detailed Reports
	Copyright (C) 2017-2019 Leejae Karinja

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

namespace Detailed_Reports;

use Detailed_Reports\Includes\Exporter;
use Detailed_Reports\Includes\Query_Builder;
use Detailed_Reports\Includes\Helper;

// Prevents execution outside of core WordPress
if(!defined('ABSPATH'))
{
	exit;
}

// Defines path to this plugin
define('DETAILED_REPORTS_PATH', plugin_dir_path(__FILE__));

// Defines path to where files should be exported ([WordPress Install Directory]/Exports/)
define('EXPORT_PATH', realpath(__DIR__ . '/../../../Exports') . '/');

// Include the Query Builder
include_once(DETAILED_REPORTS_PATH . 'includes/query-builder.php');

// Include the Exporter
include_once(DETAILED_REPORTS_PATH . 'includes/exporter.php');

// Include the Helper
include_once(DETAILED_REPORTS_PATH . 'includes/helper.php');

// If the class for the plugin is not defined
if(!class_exists('Detailed_Reports'))
{
	// Define the class for the plugin
	class Detailed_Reports
	{

		/**
		 * Plugin constructor
		 */
		public function __construct()
		{
			// Used for debugging, allows us to 'echo' for JS 'alert()' and such
			ob_start();

			// Set plugin textdomain for the Admin Pages
			$this->textdomain = 'detailed-reports';

			// On every page load (Add Admin Pages)
			add_action('init', array($this, 'init'));

			// On plugin activation (Add custom Capabilities)
			register_activation_hook(__FILE__, array($this, 'plugin_activate'));
		}

		/**
		 * Creates a page in the Admin Menu
		 */
		public function init()
		{
			// Add page in Admin Menu
			add_action('admin_menu', array($this, 'add_admin_page'));
		}

		/**
		 * Creates a page in the Admin Menu and applies custom CSS
		 *
		 * Parts of this function are referenced from Terry Tsang (http://shop.terrytsang.com) Extra Fee Option Plugin (http://terrytsang.com/shop/shop/woocommerce-extra-fee-option/)
		 * Licensed under GPL2 (Or later)
		 */
		public function add_admin_page()
		{
			if(current_user_can('manage_woocommerce'))
			{
				// Create Admin Submenu Page under WooCommerce
				$admin_page = add_submenu_page(
					'woocommerce',
					__('Detailed Reports', $this->textdomain),
					__('Detailed Reports', $this->textdomain),
					'view_detailed_reports',
					$this->textdomain,
					array(
						$this,
						'reports_page'
					)
				);
			} else {
				// Create Admin Menu Page
				$admin_page = add_menu_page(
					__('Detailed Reports', $this->textdomain),
					__('Detailed Reports', $this->textdomain),
					'view_detailed_reports',
					$this->textdomain,
					array(
						$this,
						'reports_page'
					),
					// Do not display a Menu Icon (Use CSS instead)
					'none',
					// This places it after WooCommerce but before Tools/Settings
					58
				);
			}
			// Apply CSS
			add_action('load-' . $admin_page, array($this, 'add_admin_css'));
		}

		/**
		 * Add CSS request to WordPress
		 */
		public function add_admin_css()
		{
			add_action('admin_enqueue_scripts', array($this, 'admin_page_css'));
		}

		/**
		 * Applies CSS to WordPress
		 */
		public function admin_page_css()
		{
			wp_enqueue_style('detailed-reports-stylesheet', plugins_url('css/admin-page.css', __FILE__));
		}

		/**
		 * Creates custom Capability 'view_detailed_reports' to view/use this plugin
		 */
		public function plugin_activate()
		{
			// Admin Role
			$admin_role = get_role('administrator');
			// Allow this role to view/use the plugin page
			$admin_role->add_cap('view_detailed_reports', true);
		}

		/**
		 * Creates a page in the Admin Menu
		 *
		 * Parts of this function are referenced from Matt Gates (http://mgates.me) WC Vendors Admin Reports (https://github.com/wcvendors/wcvendors/blob/master/classes/admin/class-admin-reports.php)
		 * Licensed under GPL2 (Or later)
		 */
		public function reports_page()
		{
			// Get the entered start date (Default is first of the month)
			$start_date = !empty($_POST['start_date']) ? date('Y-m-d', strtotime($_POST['start_date'])) : date('Y-m-d', strtotime(date('Ym', current_time('timestamp')) . '01'));
			// Get the entered end date (Default is today)
			$end_date = !empty($_POST['end_date']) ? date('Y-m-d', strtotime($_POST['end_date'])) : date('Y-m-d', current_time('timestamp'));

			// Get all vendors for vendor filter
			$vendors = get_users(array('role' => 'vendor'));
			// Selected vendor to filter by, if selected
			$selected_vendor = !empty($_POST['show_vendor']) ? (int) $_POST['show_vendor'] : false;

			// Selected method to filter by
			$selected_method = !empty($_POST['query_method']) ? $_POST['query_method'] : 'basic_by_product';

			// Data retrieved from SQL Query
			$query_data = Query_Builder::get_query_data($start_date, date('Y-m-d', strtotime('+1 day', strtotime($end_date))), $selected_vendor, $selected_method);

			if(isset($query_data))
			{
				// Data
				$data = Helper::as_arrays($query_data);
				// Column names
				$column_names = array_keys($data[0]);

				if(isset($_POST['export']))
				{
					$file = EXPORT_PATH . 'Export ' . $selected_method . ' ' . $start_date . ' to ' . $end_date . '.csv';
					Exporter::export_csv($data, $column_names, $file);
				}
			}

			// HTML/PHP for the page display
			?>

			<div class="detailed-reports-options">
				<h3><span><?php _e('Options', $this->textdomain); ?></span></h3>
				<form method="post" action="">
					<p>
						<label><?php _e('Method:', $this->textdomain); ?></label>
						<select name="query_method">
							<option value="by_product" <?php _e(selected($selected_method, 'by_product', false), $this->textdomain) ?>><?php _e('Product Sales Information', $this->textdomain); ?></option>
							<option value="by_vendor" <?php _e(selected($selected_method, 'by_vendor', false), $this->textdomain) ?>><?php _e('Vendor Sales Information', $this->textdomain); ?></option>
							<option value="basic_by_product" <?php _e(selected($selected_method, 'basic_by_product', false), $this->textdomain) ?>><?php _e('Product Quantities', $this->textdomain); ?></option>
							<option value="by_order" <?php _e(selected($selected_method, 'by_order', false), $this->textdomain) ?>><?php _e('Order Sales Information', $this->textdomain); ?></option>
						</select>

						<label><?php _e('From:', $this->textdomain); ?></label>
						<input type="text" placeholder="YYYY-MM-DD" value="<?php echo esc_attr($start_date); ?>" name="start_date"/>

						<label><?php _e('To:', $this->textdomain); ?></label>
						<input type="text" placeholder="YYYY-MM-DD" value="<?php echo esc_attr($end_date); ?>" name="end_date"/>

						<label><?php _e('Vendor:', $this->textdomain); ?></label>
						<select name="show_vendor">
							<option></option>
							<?php foreach($vendors as $vendor) printf('<option value="%s" %s>%s</option>', $vendor->ID, selected($selected_vendor, $vendor->ID, false), $vendor->display_name); ?>
						</select>

						<br>

						<input class="button-secondary" type="submit" value="<?php _e('Get Results', $this->textdomain); ?>" />
						<input class="button-secondary" type="submit" name="export" value="<?php _e('Export CSV', $this->textdomain); ?>" />
					</p>
				</form>
			</div>

			<div class="detailed-reports-results">
				<?php if(isset($data)): ?>
					<h3><span><?php _e('Results', $this->textdomain); ?></span></h3>

					<table>
						<thead>
							<tr>
							<?php foreach($column_names as $column_name): ?>
								<th><?php echo $column_name; ?></th>
							<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
						<?php foreach($data as $item): ?>
							<tr>
								<?php foreach($item as $item_data): ?>
									<td><?php echo $item_data; ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<?php
		}

	}
	// Create new instance of 'Detailed_Reports' class
	$detailed_reports = new Detailed_Reports();
}
