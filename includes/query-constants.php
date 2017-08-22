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
		  CONCAT('$', ROUND(SUM(product_data.product_commission), 2)) AS 'Vendor Payout',
		  CONCAT('$', ROUND((SUM(product_data.product_total) - ABS(SUM(product_data.product_total_refunded))) - SUM(product_data.product_commission), 2)) AS 'Store Payout'
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
				  item_data.product_qty AS product_qty,
				  item_data.product_total AS product_total,
				  '0' AS product_qty_refunded,
				  '0' AS product_total_refunded,
				  item_data.product_commission AS product_commission
				FROM
				  (
					SELECT
					  base_item_data.product_id AS product_id,
					  base_item_data.product_qty AS product_qty,
					  base_item_data.product_total AS product_total,
					  base_item_data.product_commission AS product_commission
					FROM
					  (
						SELECT
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
				UNION
				SELECT
				  refund_data.product_id AS product_id,
				  '0' AS product_qty,
				  '0' AS product_total,
				  refund_data.product_qty_refunded AS product_qty_refunded,
				  refund_data.product_total_refunded AS product_total_refunded,
				  '0' AS product_commission
				FROM
				  (
					SELECT
					  base_refund_data.product_id AS product_id,
					  base_refund_data.product_qty_refunded AS product_qty_refunded,
					  base_refund_data.product_total_refunded AS product_total_refunded
					FROM
					  (
						SELECT
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
					posts.id AS product_id,
					posts.post_title AS product_name,
					meta.meta_value AS product_sku,
					posts.post_author AS vendor_id
				  FROM
					wp_posts AS posts
					INNER JOIN
					  wp_postmeta AS meta
					  ON posts.id = meta.post_id
				  WHERE
					meta.meta_key = '_sku'
					AND posts.post_type = 'product'
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
		  product_data.vendor_id AS 'Vendor ID',
		  product_meta.vendor_name AS 'Vendor Name',
		  SUM(product_data.product_qty) AS 'Items Sold',
		  SUM(product_data.product_qty_refunded) AS 'Items Refunded',
		  (SUM(product_data.product_qty) - ABS(SUM(product_data.product_qty_refunded))) AS 'Resulting Items Sold',
		  CONCAT('$', ROUND(SUM(product_data.product_total), 2)) AS 'Total Sold',
		  CONCAT('-$', ROUND(ABS(SUM(product_data.product_total_refunded)), 2)) AS 'Total Refunded',
		  CONCAT('$', ROUND(SUM(product_data.product_total) - ABS(SUM(product_data.product_total_refunded)), 2)) AS 'Resulting Total Sold',
		  CONCAT('$', ROUND(SUM(product_data.product_commission), 2)) AS 'Vendor Payout',
		  CONCAT('$', ROUND((SUM(product_data.product_total) - ABS(SUM(product_data.product_total_refunded))) - SUM(product_data.product_commission), 2)) AS 'Store Payout'
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
				  item_data.product_qty AS product_qty,
				  item_data.product_total AS product_total,
				  '0' AS product_qty_refunded,
				  '0' AS product_total_refunded,
				  item_data.product_commission AS product_commission
				FROM
				  (
					SELECT
					  base_item_data.product_id AS product_id,
					  base_item_data.product_qty AS product_qty,
					  base_item_data.product_total AS product_total,
					  base_item_data.product_commission AS product_commission
					FROM
					  (
						SELECT
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
				UNION
				SELECT
				  refund_data.product_id AS product_id,
				  '0' AS product_qty,
				  '0' AS product_total,
				  refund_data.product_qty_refunded AS product_qty_refunded,
				  refund_data.product_total_refunded AS product_total_refunded,
				  '0' AS product_commission
				FROM
				  (
					SELECT
					  base_refund_data.product_id AS product_id,
					  base_refund_data.product_qty_refunded AS product_qty_refunded,
					  base_refund_data.product_total_refunded AS product_total_refunded
					FROM
					  (
						SELECT
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
				vendor.vendor_name AS vendor_name
			  FROM
				(
				  SELECT
					posts.id AS product_id,
					posts.post_author AS vendor_id
				  FROM
					wp_posts AS posts
				  WHERE
					posts.post_type = 'product'
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
		  product_data.vendor_id
		ORDER BY
		  product_meta.vendor_name ASC
		";

	// SQL Query to get details per product
	const BASIC_ITEM_DETAILS_QUERY =
		"
		SELECT
		  product_meta.product_name AS 'Product Name',
		  product_meta.product_sku AS 'Product SKU',
		  product_meta.vendor_name AS 'Vendor Name',
		  (SUM(product_data.product_qty) - ABS(SUM(product_data.product_qty_refunded))) AS 'Quantity Sold'
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
				  item_data.product_qty AS product_qty,
				  '0' AS product_qty_refunded
				FROM
				  (
					SELECT
					  base_item_data.product_id AS product_id,
					  base_item_data.product_qty AS product_qty
					FROM
					  (
						SELECT
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
				UNION
				SELECT
				  refund_data.product_id AS product_id,
				  '0' AS product_qty,
				  refund_data.product_qty_refunded AS product_qty_refunded
				FROM
				  (
					SELECT
					  base_refund_data.product_id AS product_id,
					  base_refund_data.product_qty_refunded AS product_qty_refunded
					FROM
					  (
						SELECT
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
					posts.id AS product_id,
					posts.post_title AS product_name,
					meta.meta_value AS product_sku,
					posts.post_author AS vendor_id
				  FROM
					wp_posts AS posts
					INNER JOIN
					  wp_postmeta AS meta
					  ON posts.id = meta.post_id
				  WHERE
					meta.meta_key = '_sku'
					AND posts.post_type = 'product'
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

}
