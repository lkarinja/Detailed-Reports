<?php
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

namespace Detailed_Reports\Includes;

class Query_Constants
{

	// SQL Query to get details per product
	const ITEM_DETAILS_QUERY =
		"
		SELECT
		  product_data.product_id AS 'Product ID',
		  product_meta.product_name AS 'Product Name',
		  product_meta.product_sku AS 'Product SKU',
		  product_data.vendor_id AS 'Vendor ID',
		  product_meta.vendor_name AS 'Vendor Name',
		  SUM(product_data.product_qty) AS 'Quantity Sold',
		  SUM(product_data.product_qty_refunded) AS 'Quantity Refunded',
		  (SUM(product_data.product_qty) - ABS(SUM(product_data.product_qty_refunded))) AS 'Resulting Quantity Sold',
		  CONCAT('$', ROUND(SUM(product_data.product_total), 2)) AS 'Total Sold',
		  CONCAT('-$', ROUND(ABS(SUM(product_data.product_total_refunded)), 2)) AS 'Total Refunded',
		  CONCAT('$', ROUND(SUM(product_data.product_total) - ABS(SUM(product_data.product_total_refunded)), 2)) AS 'Resulting Total Sold',
		  CONCAT('$', ROUND((product_data.product_commission / product_data.product_qty) * (SUM(product_data.product_qty) - ABS(SUM(product_data.product_qty_refunded))), 2)) AS 'Vendor Payout',
		  CONCAT('$', ROUND(SUM(product_data.product_total) - ABS(SUM(product_data.product_total_refunded)) - (product_data.product_commission / product_data.product_qty) * (SUM(product_data.product_qty) - ABS(SUM(product_data.product_qty_refunded))), 2)) AS 'Store Payout'
		FROM
		  (
			SELECT
			  base_product_data.product_id AS product_id,
			  base_product_data.product_qty AS product_qty,
			  base_product_data.product_total AS product_total,
			  base_product_data.product_commission AS product_commission,
			  base_product_data.product_qty_refunded AS product_qty_refunded,
			  base_product_data.product_total_refunded AS product_total_refunded,
			  product_vendors.vendor_id AS vendor_id
			FROM
			  (
				SELECT
				  item_data.product_id AS product_id,
				  SUM(item_data.product_qty) AS product_qty,
				  SUM(item_data.product_total) AS product_total,
				  '0' AS product_qty_refunded,
				  '0' AS product_total_refunded,
				  SUM(item_data.product_commission) AS product_commission
				FROM
				  (
					SELECT
					  base_item_data.product_id AS product_id,
					  base_item_data.product_qty AS product_qty,
					  base_item_data.product_total AS product_total,
					  base_item_data.product_commission AS product_commission
					FROM
					  (
						SELECT DISTINCT
						  id.product_id AS product_id,
						  qty.product_qty AS product_qty,
						  total.product_total AS product_total,
						  commission.product_commission AS product_commission,
						  id.parent_post AS parent_post
						FROM
						  (
							SELECT
							  meta.order_item_id AS order_item_id,
							  meta.meta_value AS product_id,
							  posts.post_parent AS parent_post
							FROM
							  wp_posts AS posts
							  INNER JOIN
								wp_woocommerce_order_items AS items
								ON posts.ID = items.order_id
							  INNER JOIN
								wp_woocommerce_order_itemmeta AS meta
								ON items.order_item_id = meta.order_item_id
							WHERE
							  meta.meta_key = '_product_id'
							  AND posts.post_type = 'shop_order_vendor'
						  )
						  AS id
						  LEFT OUTER JOIN
							(
							  SELECT
								meta.order_item_id AS order_item_id,
								meta.meta_value AS product_qty
							  FROM
								wp_posts AS posts
								INNER JOIN
								  wp_woocommerce_order_items AS items
								  ON posts.ID = items.order_id
								INNER JOIN
								  wp_woocommerce_order_itemmeta AS meta
								  ON items.order_item_id = meta.order_item_id
							  WHERE
								meta.meta_key = '_qty'
								AND posts.post_type = 'shop_order_vendor'
							)
							AS qty
							ON qty.order_item_id = id.order_item_id
						  LEFT OUTER JOIN
							(
							  SELECT
								meta.order_item_id AS order_item_id,
								meta.meta_value AS product_total
							  FROM
								wp_posts AS posts
								INNER JOIN
								  wp_woocommerce_order_items AS items
								  ON posts.ID = items.order_id
								INNER JOIN
								  wp_woocommerce_order_itemmeta AS meta
								  ON items.order_item_id = meta.order_item_id
							  WHERE
								meta.meta_key = '_line_total'
								AND posts.post_type = 'shop_order_vendor'
							)
							AS total
							ON total.order_item_id = qty.order_item_id
						  LEFT OUTER JOIN
							(
							  SELECT
								meta.order_item_id AS order_item_id,
								meta.meta_value AS product_commission
							  FROM
								wp_posts AS posts
								INNER JOIN
								  wp_woocommerce_order_items AS items
								  ON posts.id = items.order_id
								INNER JOIN
								  wp_woocommerce_order_itemmeta AS meta
								  ON items.order_item_id = meta.order_item_id
							  WHERE
								meta.meta_key = '_vendor_commission'
								AND posts.post_type = 'shop_order_vendor'
							)
							AS commission
							ON commission.order_item_id = total.order_item_id
					  )
					  AS base_item_data
					  INNER JOIN
						(
						  SELECT
							posts.ID AS post_id
						  FROM
							wp_posts AS posts
						  WHERE
							posts.post_status = 'wc-completed'
							AND posts.post_type = 'shop_order'
							%s
						)
						AS post
						ON post.post_id = base_item_data.parent_post
				  )
				  AS item_data
				  GROUP BY
					item_data.product_id
				UNION
				SELECT
				  refund_data.product_id AS product_id,
				  '0' AS product_qty,
				  '0' AS product_total,
				  SUM(refund_data.product_qty_refunded) AS product_qty_refunded,
				  SUM(refund_data.product_total_refunded) AS product_total_refunded,
				  '0' AS product_commission
				FROM
				  (
					SELECT
					  base_refund_data.product_id AS product_id,
					  base_refund_data.product_qty_refunded AS product_qty_refunded,
					  base_refund_data.product_total_refunded AS product_total_refunded
					FROM
					  (
						SELECT DISTINCT
						  id.product_id AS product_id,
						  qty_refunded.product_qty_refunded AS product_qty_refunded,
						  total_refunded.product_total_refunded AS product_total_refunded,
						  id.parent_post AS parent_post
						FROM
						  (
							SELECT
							  meta.order_item_id AS order_item_id,
							  meta.meta_value AS product_id,
							  posts.post_parent AS parent_post
							FROM
							  wp_posts AS posts
							  INNER JOIN
								wp_woocommerce_order_items AS items
								ON posts.ID = items.order_id
							  INNER JOIN
								wp_woocommerce_order_itemmeta AS meta
								ON items.order_item_id = meta.order_item_id
							WHERE
							  meta.meta_key = '_product_id'
							  AND posts.post_type = 'shop_order_refund'
						  )
						  AS id
						  LEFT OUTER JOIN
							(
							  SELECT
								meta.order_item_id AS order_item_id,
								meta.meta_value AS product_qty_refunded
							  FROM
								wp_posts AS posts
								INNER JOIN
								  wp_woocommerce_order_items AS items
								  ON posts.ID = items.order_id
								INNER JOIN
								  wp_woocommerce_order_itemmeta AS meta
								  ON items.order_item_id = meta.order_item_id
							  WHERE
								meta.meta_key = '_qty'
								AND posts.post_type = 'shop_order_refund'
							)
							AS qty_refunded
							ON qty_refunded.order_item_id = id.order_item_id
						  LEFT OUTER JOIN
							(
							  SELECT
								meta.order_item_id AS order_item_id,
								meta.meta_value AS product_total_refunded
							  FROM
								wp_posts AS posts
								INNER JOIN
								  wp_woocommerce_order_items AS items
								  ON posts.ID = items.order_id
								INNER JOIN
								  wp_woocommerce_order_itemmeta AS meta
								  ON items.order_item_id = meta.order_item_id
							  WHERE
								meta.meta_key = '_line_total'
								AND posts.post_type = 'shop_order_refund'
							)
							AS total_refunded
							ON total_refunded.order_item_id = qty_refunded.order_item_id
					  )
					  AS base_refund_data
					  INNER JOIN
						(
						  SELECT
							posts.ID AS post_id
						  FROM
							wp_posts AS posts
						  WHERE
							posts.post_status = 'wc-completed'
							AND posts.post_type = 'shop_order'
							%s
						)
						AS post
						ON post.post_id = base_refund_data.parent_post
				  )
				  AS refund_data
				  GROUP BY
					refund_data.product_id
			  )
			  AS base_product_data
			  INNER JOIN
				(
				  SELECT
					posts.id AS product_id,
					posts.post_author AS vendor_id
				  FROM
					wp_posts AS posts
				  WHERE
					posts.post_type = 'product'
				)
				AS product_vendors
				ON product_vendors.product_id = base_product_data.product_id
		  )
		  AS product_data
		  INNER JOIN
			(
			  SELECT
				product.product_id AS product_id,
				product.product_name AS product_name,
				product.product_sku AS product_sku,
				vendor.vendor_name AS vendor_name
			  FROM
				(
					SELECT 
						posts.product_id,
						posts.product_name,
						meta.product_sku,
						posts.vendor_id
					FROM
					(		  
					SELECT
						posts.id AS product_id,
						posts.post_title AS product_name,
						posts.post_author AS vendor_id
					FROM
						wp_posts AS posts
					WHERE
						posts.post_type = 'product'
					) AS posts
					LEFT JOIN
					(
					SELECT
						meta.meta_value AS product_sku,
						meta.post_id AS post_id
					FROM
						wp_postmeta AS meta
					WHERE
						meta.meta_key = '_sku'
					) AS meta
					ON posts.product_id = meta.post_id
				)
				AS product
				INNER JOIN
				  (
					SELECT
					  users.id AS vendor_id,
					  users.display_name AS vendor_name
					FROM
					  wp_users AS users
					  INNER JOIN
						wp_usermeta AS meta
						ON users.id = meta.user_id
					WHERE
					  meta.meta_key = 'wp_capabilities'
					  AND meta.meta_value LIKE '%%vendor%%'
					  %s
				  )
				  AS vendor
				  ON vendor.vendor_id = product.vendor_id
			)
			AS product_meta
			ON product_meta.product_id = product_data.product_id
		GROUP BY
		  product_data.product_id
		ORDER BY
		  product_meta.vendor_name ASC,
		  product_meta.product_name ASC
		";

	// SQL Query to get details per vendor
	const VENDOR_DETAILS_QUERY =
		"
		SELECT
		  data.vendor_id AS 'Vendor ID',
		  data.vendor_name AS 'Vendor Name',
		  SUM(data.quantity_sold) AS 'Items Sold',
		  SUM(data.quantity_refunded) AS 'Items Refunded',
		  SUM(data.resulting_quantity_sold) AS 'Resulting Items Sold',
		  CONCAT('$', ROUND(SUM(data.total_sold), 2)) AS 'Total Sold',
		  CONCAT('-$', ROUND(SUM(data.total_refunded), 2)) AS 'Total Refunded',
		  CONCAT('$', ROUND(SUM(data.resulting_total_sold), 2)) AS 'Resulting Total Sold',
		  CONCAT('$', ROUND(SUM(data.vendor_payout), 2)) AS 'Vendor Payout',
		  CONCAT('$', ROUND(SUM(data.store_payout), 2)) AS 'Store Payout'
		FROM
		  (
			SELECT
			  product_data.product_id AS product_id,
			  product_data.vendor_id AS vendor_id,
			  product_meta.vendor_name AS vendor_name,
			  SUM(product_data.product_qty) AS quantity_sold,
			  SUM(product_data.product_qty_refunded) AS quantity_refunded,
			  SUM(product_data.product_qty) - ABS(SUM(product_data.product_qty_refunded)) AS resulting_quantity_sold,
			  SUM(product_data.product_total) AS total_sold,
			  ABS(SUM(product_data.product_total_refunded)) AS total_refunded,
			  SUM(product_data.product_total) - ABS(SUM(product_data.product_total_refunded)) AS resulting_total_sold,
			  (SUM(product_data.product_total) - ABS(SUM(product_data.product_total_refunded))) - (SUM(product_data.product_total) - ABS(SUM(product_data.product_total_refunded)) - (product_data.product_commission / product_data.product_qty) * (SUM(product_data.product_qty) - ABS(SUM(product_data.product_qty_refunded)))) AS vendor_payout,
			  SUM(product_data.product_total) - ABS(SUM(product_data.product_total_refunded)) - (product_data.product_commission / product_data.product_qty) * (SUM(product_data.product_qty) - ABS(SUM(product_data.product_qty_refunded))) AS store_payout
			FROM
			  (
				SELECT
				  base_product_data.product_id AS product_id,
				  base_product_data.product_qty AS product_qty,
				  base_product_data.product_total AS product_total,
				  base_product_data.product_commission AS product_commission,
				  base_product_data.product_qty_refunded AS product_qty_refunded,
				  base_product_data.product_total_refunded AS product_total_refunded,
				  product_vendors.vendor_id AS vendor_id
				FROM
				  (
					SELECT
					  item_data.product_id AS product_id,
					  SUM(item_data.product_qty) AS product_qty,
					  SUM(item_data.product_total) AS product_total,
					  '0' AS product_qty_refunded,
					  '0' AS product_total_refunded,
					  SUM(item_data.product_commission) AS product_commission
					FROM
					  (
						SELECT
						  base_item_data.product_id AS product_id,
						  base_item_data.product_qty AS product_qty,
						  base_item_data.product_total AS product_total,
						  base_item_data.product_commission AS product_commission
						FROM
						  (
							SELECT DISTINCT
							  id.product_id AS product_id,
							  qty.product_qty AS product_qty,
							  total.product_total AS product_total,
							  commission.product_commission AS product_commission,
							  id.parent_post AS parent_post
							FROM
							  (
								SELECT
								  meta.order_item_id AS order_item_id,
								  meta.meta_value AS product_id,
								  posts.post_parent AS parent_post
								FROM
								  wp_posts AS posts
								  INNER JOIN
									wp_woocommerce_order_items AS items
									ON posts.ID = items.order_id
								  INNER JOIN
									wp_woocommerce_order_itemmeta AS meta
									ON items.order_item_id = meta.order_item_id
								WHERE
								  meta.meta_key = '_product_id'
								  AND posts.post_type = 'shop_order_vendor'
							  )
							  AS id
							  LEFT OUTER JOIN
								(
								  SELECT
									meta.order_item_id AS order_item_id,
									meta.meta_value AS product_qty
								  FROM
									wp_posts AS posts
									INNER JOIN
									  wp_woocommerce_order_items AS items
									  ON posts.ID = items.order_id
									INNER JOIN
									  wp_woocommerce_order_itemmeta AS meta
									  ON items.order_item_id = meta.order_item_id
								  WHERE
									meta.meta_key = '_qty'
									AND posts.post_type = 'shop_order_vendor'
								)
								AS qty
								ON qty.order_item_id = id.order_item_id
							  LEFT OUTER JOIN
								(
								  SELECT
									meta.order_item_id AS order_item_id,
									meta.meta_value AS product_total
								  FROM
									wp_posts AS posts
									INNER JOIN
									  wp_woocommerce_order_items AS items
									  ON posts.ID = items.order_id
									INNER JOIN
									  wp_woocommerce_order_itemmeta AS meta
									  ON items.order_item_id = meta.order_item_id
								  WHERE
									meta.meta_key = '_line_total'
									AND posts.post_type = 'shop_order_vendor'
								)
								AS total
								ON total.order_item_id = qty.order_item_id
							  LEFT OUTER JOIN
								(
								  SELECT
									meta.order_item_id AS order_item_id,
									meta.meta_value AS product_commission
								  FROM
									wp_posts AS posts
									INNER JOIN
									  wp_woocommerce_order_items AS items
									  ON posts.id = items.order_id
									INNER JOIN
									  wp_woocommerce_order_itemmeta AS meta
									  ON items.order_item_id = meta.order_item_id
								  WHERE
									meta.meta_key = '_vendor_commission'
									AND posts.post_type = 'shop_order_vendor'
								)
								AS commission
								ON commission.order_item_id = total.order_item_id
						  )
						  AS base_item_data
						  INNER JOIN
							(
							  SELECT
								posts.ID AS post_id
							  FROM
								wp_posts AS posts
							  WHERE
								posts.post_status = 'wc-completed'
								AND posts.post_type = 'shop_order' % s
							)
							AS post
							ON post.post_id = base_item_data.parent_post
					  )
					  AS item_data
					GROUP BY
					  item_data.product_id
					UNION
					SELECT
					  refund_data.product_id AS product_id,
					  '0' AS product_qty,
					  '0' AS product_total,
					  SUM(refund_data.product_qty_refunded) AS product_qty_refunded,
					  SUM(refund_data.product_total_refunded) AS product_total_refunded,
					  '0' AS product_commission
					FROM
					  (
						SELECT
						  base_refund_data.product_id AS product_id,
						  base_refund_data.product_qty_refunded AS product_qty_refunded,
						  base_refund_data.product_total_refunded AS product_total_refunded
						FROM
						  (
							SELECT DISTINCT
							  id.product_id AS product_id,
							  qty_refunded.product_qty_refunded AS product_qty_refunded,
							  total_refunded.product_total_refunded AS product_total_refunded,
							  id.parent_post AS parent_post
							FROM
							  (
								SELECT
								  meta.order_item_id AS order_item_id,
								  meta.meta_value AS product_id,
								  posts.post_parent AS parent_post
								FROM
								  wp_posts AS posts
								  INNER JOIN
									wp_woocommerce_order_items AS items
									ON posts.ID = items.order_id
								  INNER JOIN
									wp_woocommerce_order_itemmeta AS meta
									ON items.order_item_id = meta.order_item_id
								WHERE
								  meta.meta_key = '_product_id'
								  AND posts.post_type = 'shop_order_refund'
							  )
							  AS id
							  LEFT OUTER JOIN
								(
								  SELECT
									meta.order_item_id AS order_item_id,
									meta.meta_value AS product_qty_refunded
								  FROM
									wp_posts AS posts
									INNER JOIN
									  wp_woocommerce_order_items AS items
									  ON posts.ID = items.order_id
									INNER JOIN
									  wp_woocommerce_order_itemmeta AS meta
									  ON items.order_item_id = meta.order_item_id
								  WHERE
									meta.meta_key = '_qty'
									AND posts.post_type = 'shop_order_refund'
								)
								AS qty_refunded
								ON qty_refunded.order_item_id = id.order_item_id
							  LEFT OUTER JOIN
								(
								  SELECT
									meta.order_item_id AS order_item_id,
									meta.meta_value AS product_total_refunded
								  FROM
									wp_posts AS posts
									INNER JOIN
									  wp_woocommerce_order_items AS items
									  ON posts.ID = items.order_id
									INNER JOIN
									  wp_woocommerce_order_itemmeta AS meta
									  ON items.order_item_id = meta.order_item_id
								  WHERE
									meta.meta_key = '_line_total'
									AND posts.post_type = 'shop_order_refund'
								)
								AS total_refunded
								ON total_refunded.order_item_id = qty_refunded.order_item_id
						  )
						  AS base_refund_data
						  INNER JOIN
							(
							  SELECT
								posts.ID AS post_id
							  FROM
								wp_posts AS posts
							  WHERE
								posts.post_status = 'wc-completed'
								AND posts.post_type = 'shop_order' % s
							)
							AS post
							ON post.post_id = base_refund_data.parent_post
					  )
					  AS refund_data
					GROUP BY
					  refund_data.product_id
				  )
				  AS base_product_data
				  INNER JOIN
					(
					  SELECT
						posts.id AS product_id,
						posts.post_author AS vendor_id
					  FROM
						wp_posts AS posts
					  WHERE
						posts.post_type = 'product'
					)
					AS product_vendors
					ON product_vendors.product_id = base_product_data.product_id
			  )
			  AS product_data
			  INNER JOIN
				(
				  SELECT
					product.product_id AS product_id,
					product.product_name AS product_name,
					product.product_sku AS product_sku,
					vendor.vendor_name AS vendor_name
				  FROM
					(
						SELECT 
							posts.product_id,
							posts.product_name,
							meta.product_sku,
							posts.vendor_id
						FROM
						(		  
						SELECT
							posts.id AS product_id,
							posts.post_title AS product_name,
							posts.post_author AS vendor_id
						FROM
							wp_posts AS posts
						WHERE
							posts.post_type = 'product'
						) AS posts
						LEFT JOIN
						(
						SELECT
							meta.meta_value AS product_sku,
							meta.post_id AS post_id
						FROM
							wp_postmeta AS meta
						WHERE
							meta.meta_key = '_sku'
						) AS meta
						ON posts.product_id = meta.post_id
					)
					AS product
					INNER JOIN
					  (
						SELECT
						  users.id AS vendor_id,
						  users.display_name AS vendor_name
						FROM
						  wp_users AS users
						  INNER JOIN
							wp_usermeta AS meta
							ON users.id = meta.user_id
						WHERE
						  meta.meta_key = 'wp_capabilities'
						  AND meta.meta_value LIKE '%%vendor%%' % s
					  )
					  AS vendor
					  ON vendor.vendor_id = product.vendor_id
				)
				AS product_meta
				ON product_meta.product_id = product_data.product_id
			GROUP BY
			  product_data.product_id
			ORDER BY
			  product_meta.vendor_name ASC,
			  product_meta.product_name ASC
		  )
		  AS data
		GROUP BY
		  data.vendor_id
		ORDER BY
		  data.vendor_name ASC
		";

	// SQL Query to get details per product
	const BASIC_ITEM_DETAILS_QUERY =
		"
		SELECT
		  product_meta.product_name AS 'Product Name',
		  product_meta.product_sku AS 'Product SKU',
		  product_meta.vendor_name AS 'Vendor Name',
		  product_data.product_qty - ABS(SUM(product_data.product_qty_refunded)) AS 'Quantity Sold'
		FROM
		  (
			SELECT
			  base_product_data.product_id AS product_id,
			  base_product_data.product_qty AS product_qty,
			  base_product_data.product_qty_refunded AS product_qty_refunded,
			  product_vendors.vendor_id AS vendor_id
			FROM
			  (
				SELECT
				  item_data.product_id AS product_id,
				  SUM(item_data.product_qty) AS product_qty,
				  '0' AS product_qty_refunded
				FROM
				  (
					SELECT
					  base_item_data.product_id AS product_id,
					  base_item_data.product_qty AS product_qty
					FROM
					  (
						SELECT DISTINCT
						  id.product_id AS product_id,
						  qty.product_qty AS product_qty,
						  id.parent_post AS parent_post
						FROM
						  (
							SELECT
							  meta.order_item_id AS order_item_id,
							  meta.meta_value AS product_id,
							  posts.post_parent AS parent_post
							FROM
							  wp_posts AS posts
							  INNER JOIN
								wp_woocommerce_order_items AS items
								ON posts.ID = items.order_id
							  INNER JOIN
								wp_woocommerce_order_itemmeta AS meta
								ON items.order_item_id = meta.order_item_id
							WHERE
							  meta.meta_key = '_product_id'
							  AND posts.post_type = 'shop_order_vendor'
						  )
						  AS id
						  LEFT OUTER JOIN
							(
							  SELECT
								meta.order_item_id AS order_item_id,
								meta.meta_value AS product_qty
							  FROM
								wp_posts AS posts
								INNER JOIN
								  wp_woocommerce_order_items AS items
								  ON posts.ID = items.order_id
								INNER JOIN
								  wp_woocommerce_order_itemmeta AS meta
								  ON items.order_item_id = meta.order_item_id
							  WHERE
								meta.meta_key = '_qty'
								AND posts.post_type = 'shop_order_vendor'
							)
							AS qty
							ON qty.order_item_id = id.order_item_id
					  )
					  AS base_item_data
					  INNER JOIN
						(
						  SELECT
							posts.ID AS post_id
						  FROM
							wp_posts AS posts
						  WHERE
							posts.post_status = 'wc-completed'
							AND posts.post_type = 'shop_order'
							%s
						)
						AS post
						ON post.post_id = base_item_data.parent_post
				  )
				  AS item_data
				  GROUP BY
					item_data.product_id
				UNION
				SELECT
				  refund_data.product_id AS product_id,
				  '0' AS product_qty,
				  SUM(refund_data.product_qty_refunded) AS product_qty_refunded
				FROM
				  (
					SELECT
					  base_refund_data.product_id AS product_id,
					  base_refund_data.product_qty_refunded AS product_qty_refunded
					FROM
					  (
						SELECT DISTINCT
						  id.product_id AS product_id,
						  qty_refunded.product_qty_refunded AS product_qty_refunded,
						  id.parent_post AS parent_post
						FROM
						  (
							SELECT
							  meta.order_item_id AS order_item_id,
							  meta.meta_value AS product_id,
							  posts.post_parent AS parent_post
							FROM
							  wp_posts AS posts
							  INNER JOIN
								wp_woocommerce_order_items AS items
								ON posts.ID = items.order_id
							  INNER JOIN
								wp_woocommerce_order_itemmeta AS meta
								ON items.order_item_id = meta.order_item_id
							WHERE
							  meta.meta_key = '_product_id'
							  AND posts.post_type = 'shop_order_refund'
						  )
						  AS id
						  LEFT OUTER JOIN
							(
							  SELECT
								meta.order_item_id AS order_item_id,
								meta.meta_value AS product_qty_refunded
							  FROM
								wp_posts AS posts
								INNER JOIN
								  wp_woocommerce_order_items AS items
								  ON posts.ID = items.order_id
								INNER JOIN
								  wp_woocommerce_order_itemmeta AS meta
								  ON items.order_item_id = meta.order_item_id
							  WHERE
								meta.meta_key = '_qty'
								AND posts.post_type = 'shop_order_refund'
							)
							AS qty_refunded
							ON qty_refunded.order_item_id = id.order_item_id
					  )
					  AS base_refund_data
					  INNER JOIN
						(
						  SELECT
							posts.ID AS post_id
						  FROM
							wp_posts AS posts
						  WHERE
							posts.post_status = 'wc-completed'
							AND posts.post_type = 'shop_order'
							%s
						)
						AS post
						ON post.post_id = base_refund_data.parent_post
				  )
				  AS refund_data
				  GROUP BY
					refund_data.product_id
			  )
			  AS base_product_data
			  INNER JOIN
				(
				  SELECT
					posts.id AS product_id,
					posts.post_author AS vendor_id
				  FROM
					wp_posts AS posts
				  WHERE
					posts.post_type = 'product'
				)
				AS product_vendors
				ON product_vendors.product_id = base_product_data.product_id
		  )
		  AS product_data
		  INNER JOIN
			(
			  SELECT
				product.product_id AS product_id,
				product.product_name AS product_name,
				product.product_sku AS product_sku,
				vendor.vendor_name AS vendor_name
			  FROM
				(
					SELECT 
						posts.product_id,
						posts.product_name,
						meta.product_sku,
						posts.vendor_id
					FROM
					(		  
					SELECT
						posts.id AS product_id,
						posts.post_title AS product_name,
						posts.post_author AS vendor_id
					FROM
						wp_posts AS posts
					WHERE
						posts.post_type = 'product'
					) AS posts
					LEFT JOIN
					(
					SELECT
						meta.meta_value AS product_sku,
						meta.post_id AS post_id
					FROM
						wp_postmeta AS meta
					WHERE
						meta.meta_key = '_sku'
					) AS meta
					ON posts.product_id = meta.post_id
				)
				AS product
				INNER JOIN
				  (
					SELECT
					  users.id AS vendor_id,
					  users.display_name AS vendor_name
					FROM
					  wp_users AS users
					  INNER JOIN
						wp_usermeta AS meta
						ON users.id = meta.user_id
					WHERE
					  meta.meta_key = 'wp_capabilities'
					  AND meta.meta_value LIKE '%%vendor%%'
					  %s
				  )
				  AS vendor
				  ON vendor.vendor_id = product.vendor_id
			)
			AS product_meta
			ON product_meta.product_id = product_data.product_id
		GROUP BY
		  product_data.product_id
		ORDER BY
		  product_meta.vendor_name ASC,
		  product_meta.product_name ASC
		";

	// SQL Query to get details per product
	const ORDER_DETAILS_QUERY =
		"
		SELECT
		  order_data.post_id AS 'Post ID',
		  order_data.customer_name AS 'Customer Name',
		  order_data.payment_method AS 'Payment Method',
		  CONCAT('$', ROUND(order_data.order_total, 2)) AS 'Order Total',
		  CONCAT('$', ROUND(total_data.product_total, 2)) AS 'Product Total',
		  CONCAT('$', ROUND((order_data.order_total - total_data.product_total), 2)) AS 'Fee',
		  CONCAT('-$', ROUND((ABS(IFNULL(refund_data.refund_total, 0)) * ROUND(order_data.order_total / total_data.product_total, 2)), 2)) AS 'Refund Total',
		  CONCAT('$', ROUND((order_data.order_total - (ABS(IFNULL(refund_data.refund_total, 0)) * ROUND(order_data.order_total / total_data.product_total, 2))), 2)) AS 'Net Sale'
		FROM
		  (
			(
			SELECT
			  post_data.post_id AS post_id, CONCAT(post_meta.first_name, ' ', post_meta.last_name) AS customer_name, post_meta.payment_method AS payment_method, post_meta.order_total AS order_total
			FROM
			  (
				(
				SELECT
				  posts.ID AS post_id
				FROM
				  wp_posts AS posts
				WHERE
				  posts.post_status = 'wc-completed'
				  AND posts.post_type = 'shop_order' % s ) AS post_data
				  INNER JOIN
					(
					  SELECT
						payment_method.post_id AS post_id,
						payment_method.meta_value AS payment_method,
						first_name.meta_value AS first_name,
						last_name.meta_value AS last_name,
						order_total.meta_value AS order_total
					  FROM
						(
						  (
						  SELECT
							postmeta.post_id AS post_id, postmeta.meta_key AS meta_key, postmeta.meta_value AS meta_value
						  FROM
							wp_postmeta AS postmeta
						  WHERE
							postmeta.meta_key = '_payment_method_title' ) AS payment_method
							LEFT OUTER JOIN
							  (
								SELECT
								  postmeta.post_id AS post_id,
								  postmeta.meta_key AS meta_key,
								  postmeta.meta_value AS meta_value
								FROM
								  wp_postmeta AS postmeta
								WHERE
								  postmeta.meta_key = '_billing_first_name'
							  )
							  AS first_name
							  ON first_name.post_id = payment_method.post_id
							LEFT OUTER JOIN
							  (
								SELECT
								  postmeta.post_id AS post_id,
								  postmeta.meta_key AS meta_key,
								  postmeta.meta_value AS meta_value
								FROM
								  wp_postmeta AS postmeta
								WHERE
								  postmeta.meta_key = '_billing_last_name'
							  )
							  AS last_name
							  ON last_name.post_id = first_name.post_id
							LEFT OUTER JOIN
							  (
								SELECT
								  postmeta.post_id AS post_id,
								  postmeta.meta_key AS meta_key,
								  postmeta.meta_value AS meta_value
								FROM
								  wp_postmeta AS postmeta
								WHERE
								  postmeta.meta_key = '_order_total'
							  )
							  AS order_total
							  ON order_total.post_id = last_name.post_id
						)
					)
					AS post_meta
					ON post_data.post_id = post_meta.post_id
			  )
		)
		AS order_data
			  LEFT OUTER JOIN
				(
				  SELECT
					SUM(total_data.product_total) AS product_total,
					total_data.post_id AS post_id
				  FROM
					(
					  SELECT
						total.product_total AS product_total,
						id.post_id AS post_id
					  FROM
						(
						  SELECT
							meta.order_item_id AS order_item_id,
							meta.meta_value AS product_id,
							posts.ID AS post_id
						  FROM
							wp_posts AS posts
							INNER JOIN
							  wp_woocommerce_order_items AS items
							  ON posts.ID = items.order_id
							INNER JOIN
							  wp_woocommerce_order_itemmeta AS meta
							  ON items.order_item_id = meta.order_item_id
						  WHERE
							meta.meta_key = '_product_id'
							AND posts.post_type = 'shop_order'
						)
						AS id
						LEFT OUTER JOIN
						  (
							SELECT
							  meta.order_item_id AS order_item_id,
							  meta.meta_value AS product_total
							FROM
							  wp_posts AS posts
							  INNER JOIN
								wp_woocommerce_order_items AS items
								ON posts.ID = items.order_id
							  INNER JOIN
								wp_woocommerce_order_itemmeta AS meta
								ON items.order_item_id = meta.order_item_id
							WHERE
							  meta.meta_key = '_line_total'
							  AND posts.post_type = 'shop_order'
						  )
						  AS total
						  ON total.order_item_id = id.order_item_id
					)
					AS total_data
				  GROUP BY
					total_data.post_id
				)
				AS total_data
				ON total_data.post_id = order_data.post_id
			  LEFT OUTER JOIN
				(
				  SELECT
					SUM(refund_data.refund_total) AS refund_total,
					refund_data.parent_post AS parent_post
				  FROM
					(
					  SELECT
						refund.refund_total AS refund_total,
						id.parent_post AS parent_post
					  FROM
						(
						  SELECT
							meta.order_item_id AS order_item_id,
							posts.post_parent AS parent_post
						  FROM
							wp_posts AS posts
							INNER JOIN
							  wp_woocommerce_order_items AS items
							  ON posts.ID = items.order_id
							INNER JOIN
							  wp_woocommerce_order_itemmeta AS meta
							  ON items.order_item_id = meta.order_item_id
						  WHERE
							meta.meta_key = '_product_id'
							AND posts.post_type = 'shop_order_refund'
						)
						AS id
						LEFT OUTER JOIN
						  (
							SELECT
							  meta.order_item_id AS order_item_id,
							  meta.meta_value AS refund_total
							FROM
							  wp_posts AS posts
							  INNER JOIN
								wp_woocommerce_order_items AS items
								ON posts.ID = items.order_id
							  INNER JOIN
								wp_woocommerce_order_itemmeta AS meta
								ON items.order_item_id = meta.order_item_id
							WHERE
							  meta.meta_key = '_line_total'
							  AND posts.post_type = 'shop_order_refund'
						  )
						  AS refund
						  ON refund.order_item_id = id.order_item_id
					)
					AS refund_data
				  GROUP BY
					refund_data.parent_post
				)
				AS refund_data
				ON refund_data.parent_post = total_data.post_id
		  )
		";
}
