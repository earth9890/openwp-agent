<?php
/**
 * WooCommerce MCP tools.
 *
 * @package OpenWP\Inc\MCP\Modules
 */

namespace OpenWP\Inc\MCP\Modules;

use OpenWP\Inc\Actions\ActionContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce MCP parity tools.
 */
class WooTools implements ToolModuleInterface {
	/**
	 * Tools cache.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private $tools;

	/**
	 * Return tool map.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_tools() {
		if ( null !== $this->tools ) {
			return $this->tools;
		}

		$all = [
			'wc_list_products'           => [ 'level' => 'read' ],
			'wc_get_product'             => [ 'level' => 'read',  'required' => [ 'id' ] ],
			'wc_create_product'          => [ 'level' => 'write', 'required' => [ 'name' ] ],
			'wc_update_product'          => [ 'level' => 'write', 'required' => [ 'id' ] ],
			'wc_delete_product'          => [ 'level' => 'admin', 'required' => [ 'id' ] ],
			'wc_alter_product'           => [ 'level' => 'write', 'required' => [ 'id', 'field', 'search', 'replace' ] ],
			'wc_list_orders'             => [ 'level' => 'read' ],
			'wc_get_order'               => [ 'level' => 'read',  'required' => [ 'id' ] ],
			'wc_update_order_status'     => [ 'level' => 'write', 'required' => [ 'id', 'status' ] ],
			'wc_add_order_note'          => [ 'level' => 'write', 'required' => [ 'id', 'note' ] ],
			'wc_create_refund'           => [ 'level' => 'write', 'required' => [ 'id', 'amount' ] ],
			'wc_get_orders_by_customer'  => [ 'level' => 'read',  'required' => [ 'customer_id' ] ],
			'wc_update_stock'            => [ 'level' => 'write', 'required' => [ 'id', 'stock_quantity' ] ],
			'wc_get_sales_report'        => [ 'level' => 'read' ],
			'wc_get_top_sellers'         => [ 'level' => 'read' ],
			'wc_get_revenue_stats'       => [ 'level' => 'read' ],
			'wc_get_low_stock_products'  => [ 'level' => 'read' ],
			'wc_get_stock_report'        => [ 'level' => 'read' ],
			'wc_bulk_update_stock'       => [ 'level' => 'write', 'required' => [ 'updates' ] ],
			'wc_list_customers'          => [ 'level' => 'read' ],
			'wc_get_customer'            => [ 'level' => 'read',  'required' => [ 'id' ] ],
			'wc_update_customer'         => [ 'level' => 'write', 'required' => [ 'id' ] ],
			'wc_list_reviews'            => [ 'level' => 'read' ],
			'wc_approve_review'          => [ 'level' => 'write', 'required' => [ 'review_id' ] ],
			'wc_delete_review'           => [ 'level' => 'admin', 'required' => [ 'review_id' ] ],
		];

		$tools = [];
		foreach ( $all as $name => $meta ) {
			$tools[ $name ] = [
				'name'        => $name,
				'description' => 'WooCommerce tool: ' . $name,
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [],
					'required'   => isset( $meta['required'] ) ? $meta['required'] : [],
				],
				'accessLevel' => $meta['level'],
				'category'    => 'AI Engine (WooCommerce)',
				'annotations' => [
					'readOnlyHint'    => 'read' === $meta['level'],
					'destructiveHint' => false !== strpos( $name, 'delete' ),
					'openWorldHint'   => false,
				],
			];
		}

		$this->tools = $tools;
		return $this->tools;
	}

	/**
	 * Support check.
	 *
	 * @param string $tool Tool name.
	 * @return bool
	 */
	public function supports( $tool ) {
		$tools = $this->get_tools();
		return isset( $tools[ $tool ] );
	}

	/**
	 * Execute tool.
	 *
	 * @param string        $tool Tool name.
	 * @param array<string,mixed> $args Args.
	 * @param ActionContext $context Context.
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute( $tool, $args, ActionContext $context ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'openwp_mcp_woo_missing', __( 'WooCommerce is not active.', 'openwp' ) );
		}

		switch ( $tool ) {
			case 'wc_list_products':
				$query = [
					'limit'  => isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 20,
					'offset' => isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0,
					'status' => isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : 'any',
					'return' => 'objects',
				];
				if ( ! empty( $args['stock_status'] ) ) {
					$query['stock_status'] = sanitize_key( (string) $args['stock_status'] );
				}
				if ( ! empty( $args['category'] ) ) {
					$query['category'] = [ sanitize_text_field( (string) $args['category'] ) ];
				}
				if ( ! empty( $args['search'] ) ) {
					$query['search'] = '*' . sanitize_text_field( (string) $args['search'] ) . '*';
				}
				$products = wc_get_products( $query );
				$items    = array_map( [ $this, 'format_product' ], is_array( $products ) ? $products : [] );
				return [ 'products' => $items, 'count' => count( $items ) ];

			case 'wc_get_product':
				$product = wc_get_product( (int) $args['id'] );
				if ( ! $product ) {
					return new WP_Error( 'openwp_mcp_product_not_found', __( 'Product not found.', 'openwp' ) );
				}
				return [ 'product' => $this->format_product( $product ) ];

			case 'wc_create_product':
				$product = $this->create_product_object( isset( $args['type'] ) ? (string) $args['type'] : 'simple' );
				if ( ! $product ) {
					return new WP_Error( 'openwp_mcp_product_type_invalid', __( 'Invalid product type.', 'openwp' ) );
				}
				$this->apply_product_fields( $product, $args );
				$product_id = $product->save();
				return [ 'product_id' => (int) $product_id, 'product' => $this->format_product( wc_get_product( $product_id ) ) ];

			case 'wc_update_product':
				$product = wc_get_product( (int) $args['id'] );
				if ( ! $product ) {
					return new WP_Error( 'openwp_mcp_product_not_found', __( 'Product not found.', 'openwp' ) );
				}
				$this->apply_product_fields( $product, $args );
				$product->save();
				return [ 'product' => $this->format_product( $product ) ];

			case 'wc_delete_product':
				$deleted = wp_delete_post( (int) $args['id'], ! empty( $args['force'] ) );
				if ( ! $deleted ) {
					return new WP_Error( 'openwp_mcp_product_delete_failed', __( 'Failed to delete product.', 'openwp' ) );
				}
				return [ 'id' => (int) $args['id'], 'deleted' => true ];

			case 'wc_alter_product':
				$product = wc_get_product( (int) $args['id'] );
				if ( ! $product ) {
					return new WP_Error( 'openwp_mcp_product_not_found', __( 'Product not found.', 'openwp' ) );
				}
				$field = sanitize_key( (string) $args['field'] );
				if ( ! in_array( $field, [ 'description', 'short_description', 'name' ], true ) ) {
					return new WP_Error( 'openwp_mcp_product_field_invalid', __( 'Invalid product field.', 'openwp' ) );
				}
				$current = 'name' === $field ? (string) $product->get_name() : ( 'description' === $field ? (string) $product->get_description() : (string) $product->get_short_description() );
				if ( ! empty( $args['regex'] ) ) {
					$updated = preg_replace( (string) $args['search'], (string) $args['replace'], $current, -1, $count );
					if ( null === $updated ) {
						return new WP_Error( 'openwp_mcp_product_regex_invalid', __( 'Invalid regex pattern.', 'openwp' ) );
					}
				} else {
					$updated = str_replace( (string) $args['search'], (string) $args['replace'], $current, $count );
				}
				if ( 'name' === $field ) {
					$product->set_name( $updated );
				} elseif ( 'description' === $field ) {
					$product->set_description( $updated );
				} else {
					$product->set_short_description( $updated );
				}
				$product->save();
				return [ 'id' => (int) $product->get_id(), 'field' => $field, 'replacements' => (int) $count ];

			case 'wc_list_orders':
				$query = [
					'limit'  => isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 20,
					'offset' => isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0,
					'orderby'=> 'date',
					'order'  => 'DESC',
					'return' => 'objects',
				];
				if ( ! empty( $args['status'] ) && 'any' !== $args['status'] ) {
					$query['status'] = sanitize_key( (string) $args['status'] );
				}
				if ( ! empty( $args['customer'] ) ) {
					$query['customer_id'] = (int) $args['customer'];
				}
				$date_created = [];
				if ( ! empty( $args['date_after'] ) ) {
					$date_created[] = '>=' . sanitize_text_field( (string) $args['date_after'] );
				}
				if ( ! empty( $args['date_before'] ) ) {
					$date_created[] = '<=' . sanitize_text_field( (string) $args['date_before'] );
				}
				if ( ! empty( $date_created ) ) {
					$query['date_created'] = implode( '...', $date_created );
				}
				$orders = wc_get_orders( $query );
				$items  = array_map( [ $this, 'format_order' ], is_array( $orders ) ? $orders : [] );
				return [ 'orders' => $items, 'count' => count( $items ) ];

			case 'wc_get_order':
				$order = wc_get_order( (int) $args['id'] );
				if ( ! $order ) {
					return new WP_Error( 'openwp_mcp_order_not_found', __( 'Order not found.', 'openwp' ) );
				}
				return [ 'order' => $this->format_order( $order ) ];

			case 'wc_update_order_status':
				$order = wc_get_order( (int) $args['id'] );
				if ( ! $order ) {
					return new WP_Error( 'openwp_mcp_order_not_found', __( 'Order not found.', 'openwp' ) );
				}
				$order->update_status( sanitize_key( (string) $args['status'] ) );
				return [ 'order' => $this->format_order( $order ) ];

			case 'wc_add_order_note':
				$order = wc_get_order( (int) $args['id'] );
				if ( ! $order ) {
					return new WP_Error( 'openwp_mcp_order_not_found', __( 'Order not found.', 'openwp' ) );
				}
				$note_id = $order->add_order_note( sanitize_textarea_field( (string) $args['note'] ), ! empty( $args['is_customer_note'] ) );
				return [ 'order_id' => (int) $order->get_id(), 'note_id' => (int) $note_id ];

			case 'wc_create_refund':
				$refund = wc_create_refund(
					[
						'order_id' => (int) $args['id'],
						'amount'   => wc_format_decimal( (string) $args['amount'], 2 ),
						'reason'   => isset( $args['reason'] ) ? sanitize_textarea_field( (string) $args['reason'] ) : '',
					]
				);
				if ( is_wp_error( $refund ) ) {
					return $refund;
				}
				return [ 'refund_id' => (int) $refund->get_id(), 'order_id' => (int) $args['id'] ];

			case 'wc_get_orders_by_customer':
				$orders = wc_get_orders(
					[
						'customer_id' => (int) $args['customer_id'],
						'limit'       => isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 20,
						'offset'      => isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0,
						'return'      => 'objects',
					]
				);
				$items = array_map( [ $this, 'format_order' ], is_array( $orders ) ? $orders : [] );
				return [ 'orders' => $items, 'count' => count( $items ) ];

			case 'wc_update_stock':
				$product = wc_get_product( (int) $args['id'] );
				if ( ! $product ) {
					return new WP_Error( 'openwp_mcp_product_not_found', __( 'Product not found.', 'openwp' ) );
				}
				$product->set_manage_stock( true );
				$product->set_stock_quantity( (int) $args['stock_quantity'] );
				$product->save();
				return [ 'product' => $this->format_product( $product ) ];

			case 'wc_get_sales_report':
				return $this->sales_report( $args );

			case 'wc_get_top_sellers':
				$products = wc_get_products(
					[
						'limit'   => isset( $args['limit'] ) ? max( 1, min( 50, (int) $args['limit'] ) ) : 10,
						'orderby' => 'meta_value_num',
						'meta_key'=> 'total_sales',
						'order'   => 'DESC',
						'return'  => 'objects',
					]
				);
				$items = [];
				foreach ( is_array( $products ) ? $products : [] as $product ) {
					$items[] = [
						'id'          => (int) $product->get_id(),
						'name'        => (string) $product->get_name(),
						'total_sales' => (int) get_post_meta( $product->get_id(), 'total_sales', true ),
					];
				}
				return [ 'products' => $items, 'count' => count( $items ) ];

			case 'wc_get_revenue_stats':
				$report = $this->sales_report( $args );
				if ( is_wp_error( $report ) ) {
					return $report;
				}
				return [
					'revenue'       => $report['total_revenue'],
					'orders_count'  => $report['orders_count'],
					'average_order' => $report['orders_count'] > 0 ? round( $report['total_revenue'] / $report['orders_count'], 2 ) : 0,
				];

			case 'wc_get_low_stock_products':
				$threshold = isset( $args['threshold'] ) ? max( 0, (int) $args['threshold'] ) : (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
				$products  = wc_get_products( [ 'limit' => -1, 'return' => 'objects' ] );
				$items     = [];
				foreach ( is_array( $products ) ? $products : [] as $product ) {
					if ( ! $product->managing_stock() ) {
						continue;
					}
					$qty = $product->get_stock_quantity();
					if ( null !== $qty && $qty <= $threshold ) {
						$items[] = $this->format_product( $product );
					}
				}
				return [ 'products' => $items, 'count' => count( $items ), 'threshold' => $threshold ];

			case 'wc_get_stock_report':
				$products   = wc_get_products( [ 'limit' => -1, 'return' => 'objects' ] );
				$instock    = 0;
				$outofstock = 0;
				$onbackorder = 0;
				foreach ( is_array( $products ) ? $products : [] as $product ) {
					$status = $product->get_stock_status();
					if ( 'instock' === $status ) {
						++$instock;
					} elseif ( 'outofstock' === $status ) {
						++$outofstock;
					} elseif ( 'onbackorder' === $status ) {
						++$onbackorder;
					}
				}
				return [
					'instock'     => $instock,
					'outofstock'  => $outofstock,
					'onbackorder' => $onbackorder,
					'total'       => $instock + $outofstock + $onbackorder,
				];

			case 'wc_bulk_update_stock':
				$updates = isset( $args['updates'] ) && is_array( $args['updates'] ) ? $args['updates'] : [];
				$done    = [];
				foreach ( $updates as $row ) {
					if ( ! is_array( $row ) || ! isset( $row['id'], $row['stock_quantity'] ) ) {
						continue;
					}
					$product = wc_get_product( (int) $row['id'] );
					if ( ! $product ) {
						continue;
					}
					$product->set_manage_stock( true );
					$product->set_stock_quantity( (int) $row['stock_quantity'] );
					$product->save();
					$done[] = [ 'id' => (int) $product->get_id(), 'stock_quantity' => (int) $product->get_stock_quantity() ];
				}
				return [ 'updated' => $done, 'count' => count( $done ) ];

			case 'wc_list_customers':
				$query = [
					'role'   => 'customer',
					'number' => isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 20,
					'offset' => isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0,
					'fields' => [ 'ID' ],
				];
				$users = get_users( $query );
				$items = [];
				foreach ( $users as $user ) {
					$customer = new \WC_Customer( (int) $user->ID );
					$items[]  = $this->format_customer( $customer );
				}
				return [ 'customers' => $items, 'count' => count( $items ) ];

			case 'wc_get_customer':
				$customer = new \WC_Customer( (int) $args['id'] );
				if ( ! $customer || 0 === $customer->get_id() ) {
					return new WP_Error( 'openwp_mcp_customer_not_found', __( 'Customer not found.', 'openwp' ) );
				}
				return [ 'customer' => $this->format_customer( $customer ) ];

			case 'wc_update_customer':
				$customer = new \WC_Customer( (int) $args['id'] );
				if ( ! $customer || 0 === $customer->get_id() ) {
					return new WP_Error( 'openwp_mcp_customer_not_found', __( 'Customer not found.', 'openwp' ) );
				}
				if ( isset( $args['email'] ) ) {
					$customer->set_email( sanitize_email( (string) $args['email'] ) );
				}
				if ( isset( $args['first_name'] ) ) {
					$customer->set_first_name( sanitize_text_field( (string) $args['first_name'] ) );
				}
				if ( isset( $args['last_name'] ) ) {
					$customer->set_last_name( sanitize_text_field( (string) $args['last_name'] ) );
				}
				if ( isset( $args['billing_phone'] ) ) {
					$customer->set_billing_phone( sanitize_text_field( (string) $args['billing_phone'] ) );
				}
				$customer->save();
				return [ 'customer' => $this->format_customer( $customer ) ];

			case 'wc_list_reviews':
				$reviews = get_comments(
					[
						'post_type' => 'product',
						'status'    => isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : 'all',
						'number'    => isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 20,
						'offset'    => isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0,
					]
				);
				$items = [];
				foreach ( $reviews as $review ) {
					$items[] = [
						'review_id'   => (int) $review->comment_ID,
						'product_id'  => (int) $review->comment_post_ID,
						'author'      => (string) $review->comment_author,
						'content'     => (string) $review->comment_content,
						'approved'    => (string) $review->comment_approved,
						'rating'      => (int) get_comment_meta( $review->comment_ID, 'rating', true ),
						'date'        => (string) $review->comment_date,
					];
				}
				return [ 'reviews' => $items, 'count' => count( $items ) ];

			case 'wc_approve_review':
				$result = wp_set_comment_status( (int) $args['review_id'], 'approve', true );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return [ 'review_id' => (int) $args['review_id'], 'approved' => true ];

			case 'wc_delete_review':
				$deleted = wp_delete_comment( (int) $args['review_id'], true );
				if ( ! $deleted ) {
					return new WP_Error( 'openwp_mcp_review_delete_failed', __( 'Failed to delete review.', 'openwp' ) );
				}
				return [ 'review_id' => (int) $args['review_id'], 'deleted' => true ];
		}

		return new WP_Error( 'openwp_mcp_woo_unknown_tool', __( 'Unknown WooCommerce tool.', 'openwp' ) );
	}

	/**
	 * Create WC product object by type.
	 *
	 * @param string $type Product type.
	 * @return \WC_Product|null
	 */
	private function create_product_object( $type ) {
		$type = sanitize_key( $type );
		if ( 'variable' === $type && class_exists( '\\WC_Product_Variable' ) ) {
			return new \WC_Product_Variable();
		}
		if ( 'grouped' === $type && class_exists( '\\WC_Product_Grouped' ) ) {
			return new \WC_Product_Grouped();
		}
		if ( 'external' === $type && class_exists( '\\WC_Product_External' ) ) {
			return new \WC_Product_External();
		}
		if ( class_exists( '\\WC_Product_Simple' ) ) {
			return new \WC_Product_Simple();
		}
		return null;
	}

	/**
	 * Apply product fields from MCP args.
	 *
	 * @param \WC_Product         $product Product object.
	 * @param array<string,mixed> $args Args.
	 * @return void
	 */
	private function apply_product_fields( \WC_Product $product, $args ) {
		if ( isset( $args['name'] ) ) {
			$product->set_name( sanitize_text_field( (string) $args['name'] ) );
		}
		if ( isset( $args['status'] ) ) {
			$product->set_status( sanitize_key( (string) $args['status'] ) );
		}
		if ( isset( $args['description'] ) ) {
			$product->set_description( wp_kses_post( (string) $args['description'] ) );
		}
		if ( isset( $args['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( (string) $args['short_description'] ) );
		}
		if ( isset( $args['sku'] ) ) {
			$product->set_sku( sanitize_text_field( (string) $args['sku'] ) );
		}
		if ( isset( $args['regular_price'] ) ) {
			$product->set_regular_price( wc_format_decimal( (string) $args['regular_price'], 2 ) );
		}
		if ( isset( $args['sale_price'] ) ) {
			$product->set_sale_price( wc_format_decimal( (string) $args['sale_price'], 2 ) );
		}
		if ( isset( $args['manage_stock'] ) ) {
			$product->set_manage_stock( (bool) $args['manage_stock'] );
		}
		if ( isset( $args['stock_quantity'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $args['stock_quantity'] );
		}
		if ( isset( $args['stock_status'] ) ) {
			$product->set_stock_status( sanitize_key( (string) $args['stock_status'] ) );
		}
	}

	/**
	 * Format product for MCP output.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string,mixed>
	 */
	private function format_product( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return [];
		}

		$categories = get_the_terms( $product->get_id(), 'product_cat' );
		$category_items = [];
		foreach ( is_array( $categories ) ? $categories : [] as $term ) {
			$category_items[] = [
				'id'   => (int) $term->term_id,
				'name' => (string) $term->name,
				'slug' => (string) $term->slug,
			];
		}

		return [
			'id'                => (int) $product->get_id(),
			'name'              => (string) $product->get_name(),
			'slug'              => (string) $product->get_slug(),
			'type'              => (string) $product->get_type(),
			'status'            => (string) $product->get_status(),
			'sku'               => (string) $product->get_sku(),
			'price'             => (string) $product->get_price(),
			'regular_price'     => (string) $product->get_regular_price(),
			'sale_price'        => (string) $product->get_sale_price(),
			'stock_status'      => (string) $product->get_stock_status(),
			'stock_quantity'    => $product->get_stock_quantity(),
			'manage_stock'      => (bool) $product->get_manage_stock(),
			'description'       => (string) $product->get_description(),
			'short_description' => (string) $product->get_short_description(),
			'categories'        => $category_items,
			'permalink'         => (string) $product->get_permalink(),
		];
	}

	/**
	 * Format order.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string,mixed>
	 */
	private function format_order( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return [];
		}

		$line_items = [];
		foreach ( $order->get_items() as $item ) {
			$line_items[] = [
				'id'         => (int) $item->get_id(),
				'name'       => (string) $item->get_name(),
				'product_id' => (int) $item->get_product_id(),
				'quantity'   => (int) $item->get_quantity(),
				'total'      => (float) $item->get_total(),
			];
		}

		return [
			'id'           => (int) $order->get_id(),
			'order_number' => (string) $order->get_order_number(),
			'status'       => (string) $order->get_status(),
			'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			'total'        => (float) $order->get_total(),
			'currency'     => (string) $order->get_currency(),
			'customer_id'  => (int) $order->get_customer_id(),
			'billing'      => [
				'first_name' => (string) $order->get_billing_first_name(),
				'last_name'  => (string) $order->get_billing_last_name(),
				'email'      => (string) $order->get_billing_email(),
				'phone'      => (string) $order->get_billing_phone(),
			],
			'line_items'   => $line_items,
			'payment_method' => (string) $order->get_payment_method_title(),
		];
	}

	/**
	 * Format customer.
	 *
	 * @param \WC_Customer $customer Customer.
	 * @return array<string,mixed>
	 */
	private function format_customer( $customer ) {
		if ( ! $customer || ! is_a( $customer, 'WC_Customer' ) ) {
			return [];
		}

		return [
			'id'         => (int) $customer->get_id(),
			'email'      => (string) $customer->get_email(),
			'first_name' => (string) $customer->get_first_name(),
			'last_name'  => (string) $customer->get_last_name(),
			'username'   => (string) $customer->get_username(),
			'billing'    => [
				'first_name' => (string) $customer->get_billing_first_name(),
				'last_name'  => (string) $customer->get_billing_last_name(),
				'company'    => (string) $customer->get_billing_company(),
				'address_1'  => (string) $customer->get_billing_address_1(),
				'city'       => (string) $customer->get_billing_city(),
				'state'      => (string) $customer->get_billing_state(),
				'postcode'   => (string) $customer->get_billing_postcode(),
				'country'    => (string) $customer->get_billing_country(),
				'email'      => (string) $customer->get_billing_email(),
				'phone'      => (string) $customer->get_billing_phone(),
			],
			'total_spent'=> (float) $customer->get_total_spent(),
			'order_count'=> (int) $customer->get_order_count(),
		];
	}

	/**
	 * Build lightweight sales report.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return array<string,mixed>|WP_Error
	 */
	private function sales_report( $args ) {
		$query = [
			'limit'  => -1,
			'status' => [ 'wc-completed', 'wc-processing', 'wc-on-hold' ],
			'return' => 'objects',
		];

		if ( ! empty( $args['date_after'] ) && ! empty( $args['date_before'] ) ) {
			$query['date_created'] = sanitize_text_field( (string) $args['date_after'] ) . '...' . sanitize_text_field( (string) $args['date_before'] );
		}

		$orders = wc_get_orders( $query );
		$total  = 0.0;
		$count  = 0;
		foreach ( is_array( $orders ) ? $orders : [] as $order ) {
			if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}
			$total += (float) $order->get_total();
			++$count;
		}

		return [
			'orders_count'   => $count,
			'total_revenue'  => round( $total, 2 ),
			'currency'       => get_woocommerce_currency(),
			'date_after'     => isset( $args['date_after'] ) ? (string) $args['date_after'] : '',
			'date_before'    => isset( $args['date_before'] ) ? (string) $args['date_before'] : '',
		];
	}
}
