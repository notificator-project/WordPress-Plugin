/**
 * Built-in notification template catalogue.
 *
 * Template data is intentionally declarative. Keep hook names and argument
 * order aligned with the owning integration's public action contract.
 *
 * @package NotificatorCompanion
 * @since 1.1.0
 */

type ScenarioTemplateCondition = {
	field?: string;
	operator?: '=' | '!=' | '>' | '>=' | '<' | '<=' | 'contains' | 'not_contains';
	value?: string | number;
	value_type?: string;
	value_label?: string;
	value_placeholder?: string;
	value_options?: Array<{ value: string; label: string } | string>;
	value_options_key?: string;
	locked?: boolean;
	lock_field?: boolean;
	lock_operator?: boolean;
};

type ScenarioTemplateHookMetaProperty = {
	name: string;
	label?: string;
	type?: string;
};

type ScenarioTemplateHookMeta = {
	label?: string;
	type?: string;
	arg_names?: string[];
	payload_arity?: number;
	properties?: Record<string, ScenarioTemplateHookMetaProperty[]>;
};

type ScenarioTemplate = {
	icon?: string;
	title: string;
	hook_name: string;
	description: string;
	scenario_name: string;
	required_plugin: string;
	severity?: 'info' | 'warning' | 'critical';
	category?: 'commerce' | 'content' | 'security' | 'forms' | 'operations' | 'learning';
	featured?: boolean;
	setup_hint?: string;
	default_notes?: string;
	hook_meta?: ScenarioTemplateHookMeta;
	conditions?: ScenarioTemplateCondition[];
};

const templates: ScenarioTemplate[] = [
	// ============================================
	// WooCommerce - Orders
	// ============================================
	{
		icon: '🛒',
		title: 'WooCommerce: New Order',
		hook_name: 'woocommerce_new_order',
		description: 'Get an alert as soon as a customer places a new order.',
		scenario_name: 'New Order Created',
		required_plugin: 'woocommerce',
		category: 'commerce',
		featured: true,
		severity: 'info',
		default_notes: 'New WooCommerce order #{{order_id}} received.',
		hook_meta: {
			label: 'New order',
			type: 'action',
			arg_names: ['order_id', 'order'],
			payload_arity: 2,
			properties: {
				order: [
					{ name: 'total', label: 'Order Total', type: 'number' },
					{ name: 'status', label: 'Order Status', type: 'string' },
					{ name: 'billing_email', label: 'Billing Email', type: 'string' },
					{ name: 'payment_method', label: 'Payment Method', type: 'string' }
				]
			}
		},
		conditions: []
	},
	{
		icon: '💰',
		title: 'WooCommerce: High-Value Order',
		hook_name: 'woocommerce_new_order',
		description: 'Notify when an order total exceeds your threshold',
		scenario_name: 'High-Value Order',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'New order',
			type: 'action',
			arg_names: ['order_id', 'order'],
			payload_arity: 2,
			properties: {
				order: [{ name: 'total', label: 'Order Total', type: 'number' }]
			}
		},
		conditions: [
			{
				field: 'order.total',
				operator: '>=',
				value: '100',
				value_type: 'number',
				value_label: 'Minimum order total',
				value_placeholder: '100',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🎯',
		title: 'WooCommerce: VIP Order',
		hook_name: 'woocommerce_new_order',
		description: 'Notify when an order total exceeds a VIP threshold',
		scenario_name: 'VIP Order',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'New order',
			type: 'action',
			arg_names: ['order_id', 'order'],
			payload_arity: 2,
			properties: {
				order: [{ name: 'total', label: 'Order Total', type: 'number' }]
			}
		},
		conditions: [
			{
				field: 'order.total',
				operator: '>=',
				value: '500',
				value_type: 'number',
				value_label: 'Minimum order total',
				value_placeholder: '500',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '📊',
		title: 'WooCommerce: Order Status Changed',
		hook_name: 'woocommerce_order_status_changed',
		description: 'Order status changes',
		scenario_name: 'Order Status Changed',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Order status changed',
			type: 'action',
			arg_names: ['order_id', 'old_status', 'new_status', 'order'],
			payload_arity: 4
		},
		conditions: [
			{
				field: 'new_status',
				operator: '=',
				value: 'processing',
				value_type: 'select',
				value_label: 'New status',
				value_options_key: 'wc_order_statuses',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '✅',
		title: 'WooCommerce: Order Completed',
		hook_name: 'woocommerce_order_status_changed',
		description: 'Notify when an order transitions to completed',
		scenario_name: 'Order Completed',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Order status changed',
			type: 'action',
			arg_names: ['order_id', 'old_status', 'new_status', 'order'],
			payload_arity: 4
		},
		conditions: [
			{
				field: 'new_status',
				operator: '=',
				value: 'completed',
				value_type: 'select',
				value_label: 'New status',
				value_options_key: 'wc_order_statuses',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '💳',
		title: 'WooCommerce: Payment Complete',
		hook_name: 'woocommerce_order_status_changed',
		description: 'Notify when payment results in processing status',
		scenario_name: 'Payment Completed',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Order status changed',
			type: 'action',
			arg_names: ['order_id', 'old_status', 'new_status', 'order'],
			payload_arity: 4
		},
		conditions: [
			{
				field: 'new_status',
				operator: '=',
				value: 'processing',
				value_type: 'select',
				value_label: 'New status',
				value_options_key: 'wc_order_statuses',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '❌',
		title: 'WooCommerce: Payment Failed',
		hook_name: 'woocommerce_order_status_changed',
		description: 'Notify when an order transitions to failed',
		scenario_name: 'Payment Failed',
		required_plugin: 'woocommerce',
		category: 'commerce',
		featured: true,
		severity: 'critical',
		default_notes: 'Payment failed for WooCommerce order #{{order_id}}.',
		hook_meta: {
			label: 'Order status changed',
			type: 'action',
			arg_names: ['order_id', 'old_status', 'new_status', 'order'],
			payload_arity: 4
		},
		conditions: [
			{
				field: 'new_status',
				operator: '=',
				value: 'failed',
				value_type: 'select',
				value_label: 'New status',
				value_options_key: 'wc_order_statuses',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '⏸️',
		title: 'WooCommerce: Order On Hold',
		hook_name: 'woocommerce_order_status_changed',
		description: 'Notify when an order transitions to on-hold',
		scenario_name: 'Order On Hold',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Order status changed',
			type: 'action',
			arg_names: ['order_id', 'old_status', 'new_status', 'order'],
			payload_arity: 4
		},
		conditions: [
			{
				field: 'new_status',
				operator: '=',
				value: 'on-hold',
				value_type: 'select',
				value_label: 'New status',
				value_options_key: 'wc_order_statuses',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🚫',
		title: 'WooCommerce: Order Cancelled',
		hook_name: 'woocommerce_order_status_changed',
		description: 'Notify when an order transitions to cancelled',
		scenario_name: 'Order Cancelled',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Order status changed',
			type: 'action',
			arg_names: ['order_id', 'old_status', 'new_status', 'order'],
			payload_arity: 4
		},
		conditions: [
			{
				field: 'new_status',
				operator: '=',
				value: 'cancelled',
				value_type: 'select',
				value_label: 'New status',
				value_options_key: 'wc_order_statuses',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '💸',
		title: 'WooCommerce: Order Refunded (Status)',
		hook_name: 'woocommerce_order_status_changed',
		description: 'Notify when an order transitions to refunded',
		scenario_name: 'Order Refunded',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Order status changed',
			type: 'action',
			arg_names: ['order_id', 'old_status', 'new_status', 'order'],
			payload_arity: 4
		},
		conditions: [
			{
				field: 'new_status',
				operator: '=',
				value: 'refunded',
				value_type: 'select',
				value_label: 'New status',
				value_options_key: 'wc_order_statuses',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '↩️',
		title: 'WooCommerce: Refund Issued',
		hook_name: 'woocommerce_order_refunded',
		description: 'Order has been refunded',
		scenario_name: 'Refund Issued',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Order refunded',
			type: 'action',
			arg_names: ['order_id', 'refund_id'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'refund_id',
				operator: '>',
				value: '0',
				value_type: 'number',
				value_label: 'Refund ID greater than',
				value_placeholder: '0',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🎫',
		title: 'WooCommerce: Coupon Used',
		hook_name: 'woocommerce_applied_coupon',
		description: 'Coupon code applied to order',
		scenario_name: 'Coupon Used',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Coupon applied',
			type: 'action',
			arg_names: ['coupon_code'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'coupon_code',
				operator: '=',
				value: 'SAVE10',
				value_type: 'text',
				value_label: 'Coupon code',
				value_placeholder: 'SAVE10',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	// ============================================
	// WooCommerce - Checkout & Customer
	// ============================================
	{
		icon: '👤',
		title: 'WooCommerce: Customer Created',
		hook_name: 'woocommerce_created_customer',
		description: 'Know when a shopper creates a new customer account.',
		scenario_name: 'Customer Created',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Customer created',
			type: 'action',
			arg_names: ['customer_id', 'new_customer_data'],
			payload_arity: 2
		},
		conditions: []
	},
	{
		icon: '🧾',
		title: 'WooCommerce: Checkout Order Processed',
		hook_name: 'woocommerce_checkout_order_processed',
		description: 'Order processed during checkout',
		scenario_name: 'Checkout Processed',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Checkout order processed',
			type: 'action',
			arg_names: ['order_id', 'posted_data', 'order'],
			payload_arity: 3,
			properties: {
				order: [
					{ name: 'total', label: 'Order Total', type: 'number' },
					{ name: 'payment_method', label: 'Payment Method', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'order.total',
				operator: '>=',
				value: '100',
				value_type: 'number',
				value_label: 'Minimum order total',
				value_placeholder: '100',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🎉',
		title: 'WooCommerce: Thank You Page',
		hook_name: 'woocommerce_thankyou',
		description: 'Customer reached the order received page',
		scenario_name: 'Thank You Page',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Thank you page',
			type: 'action',
			arg_names: ['order_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'order_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Order ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '📝',
		title: 'WooCommerce: Customer Note Added',
		hook_name: 'woocommerce_new_customer_note',
		description: 'Customer note added to an order',
		scenario_name: 'Customer Note Added',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'New customer note',
			type: 'action',
			arg_names: ['data'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'data.customer_note',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Customer note contains (optional)',
				value_placeholder: 'left at door',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '📦',
		title: 'WooCommerce: Order Completed (Hook)',
		hook_name: 'woocommerce_order_status_completed',
		description: 'Order marked completed',
		scenario_name: 'Order Completed',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Order completed',
			type: 'action',
			arg_names: ['order_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'order_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Order ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '⏳',
		title: 'WooCommerce: Order Processing (Hook)',
		hook_name: 'woocommerce_order_status_processing',
		description: 'Order moved to processing',
		scenario_name: 'Order Processing',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Order processing',
			type: 'action',
			arg_names: ['order_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'order_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Order ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// WooCommerce - Subscriptions
	// ============================================
	{
		icon: '🔄',
		title: 'WooCommerce: Subscription Renewed',
		hook_name: 'woocommerce_subscription_renewal_payment_complete',
		description: 'Subscription renewal successful',
		scenario_name: 'Subscription Renewed',
		required_plugin: 'woocommerce-subscriptions',
		hook_meta: {
			label: 'Subscription renewed',
			type: 'action',
			arg_names: ['subscription'],
			payload_arity: 1,
			properties: {
				subscription: [
					{ name: 'status', label: 'Status', type: 'string' },
					{ name: 'billing_email', label: 'Billing Email', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'subscription.billing_email',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Billing email contains (optional)',
				value_placeholder: '@yourdomain.com',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🚫',
		title: 'WooCommerce: Subscription Cancelled',
		hook_name: 'woocommerce_subscription_status_cancelled',
		description: 'Subscription cancelled',
		scenario_name: 'Subscription Cancelled',
		required_plugin: 'woocommerce-subscriptions',
		hook_meta: {
			label: 'Subscription cancelled',
			type: 'action',
			arg_names: ['subscription'],
			payload_arity: 1,
			properties: {
				subscription: [
					{ name: 'status', label: 'Status', type: 'string' },
					{ name: 'billing_email', label: 'Billing Email', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'subscription.status',
				operator: 'contains',
				value: 'cancelled',
				value_type: 'text',
				value_label: 'Subscription status contains',
				value_placeholder: 'cancelled',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// WooCommerce - Stock
	// ============================================
	{
		icon: '📦',
		title: 'WooCommerce: Low Stock',
		hook_name: 'woocommerce_low_stock',
		description: 'Alert the team when a product reaches five units or fewer.',
		scenario_name: 'Low Stock Alert',
		required_plugin: 'woocommerce',
		category: 'commerce',
		featured: true,
		severity: 'warning',
		default_notes: '{{product.name}} is running low ({{product.stock_quantity}} remaining).',
		hook_meta: {
			label: 'Low stock',
			type: 'action',
			arg_names: ['product'],
			payload_arity: 1,
			properties: {
				product: [
					{ name: 'name', label: 'Product Name', type: 'string' },
					{ name: 'sku', label: 'SKU', type: 'string' },
					{ name: 'stock_quantity', label: 'Stock Quantity', type: 'number' }
				]
			}
		},
		conditions: [
			{
				field: 'product.stock_quantity',
				operator: '<=',
				value: '5',
				value_type: 'number',
				value_label: 'Stock quantity at/below',
				value_placeholder: '5',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '⚠️',
		title: 'WooCommerce: Out of Stock',
		hook_name: 'woocommerce_no_stock',
		description: 'Product out of stock',
		scenario_name: 'Out of Stock',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'No stock',
			type: 'action',
			arg_names: ['product'],
			payload_arity: 1,
			properties: {
				product: [
					{ name: 'name', label: 'Product Name', type: 'string' },
					{ name: 'sku', label: 'SKU', type: 'string' },
					{ name: 'stock_quantity', label: 'Stock Quantity', type: 'number' }
				]
			}
		},
		conditions: [
			{
				field: 'product.stock_quantity',
				operator: '<=',
				value: '0',
				value_type: 'number',
				value_label: 'Stock quantity at/below',
				value_placeholder: '0',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '📉',
		title: 'WooCommerce: Stock Status Changed',
		hook_name: 'woocommerce_product_set_stock_status',
		description: 'Product stock status changed',
		scenario_name: 'Stock Status Changed',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Stock status changed',
			type: 'action',
			arg_names: ['product_id', 'stock_status', 'product'],
			payload_arity: 3,
			properties: {
				product: [
					{ name: 'name', label: 'Product Name', type: 'string' },
					{ name: 'sku', label: 'SKU', type: 'string' },
					{ name: 'stock_quantity', label: 'Stock Quantity', type: 'number' }
				]
			}
		},
		conditions: [
			{
				field: 'stock_status',
				operator: '=',
				value: 'outofstock',
				value_type: 'select',
				value_label: 'Stock status',
				value_options: [
					{ value: 'instock', label: 'In stock' },
					{ value: 'outofstock', label: 'Out of stock' },
					{ value: 'onbackorder', label: 'On backorder' }
				],
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// WooCommerce - Reviews
	// ============================================
	{
		icon: '⭐',
		title: 'WooCommerce: New Product Review',
		hook_name: 'comment_post',
		description: 'New product review submitted',
		scenario_name: 'Product Review Submitted',
		required_plugin: 'woocommerce',
		hook_meta: {
			label: 'Comment posted',
			type: 'action',
			arg_names: ['comment_id', 'comment_approved'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'comment_approved',
				operator: '=',
				value: '0',
				value_type: 'select',
				value_label: 'Approval state',
				value_options: [
					{ value: '0', label: 'Pending moderation' },
					{ value: '1', label: 'Approved' }
				],
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// WordPress - Posts
	// ============================================
	{
		icon: '📝',
		title: 'WordPress: Post Published',
		hook_name: 'publish_post',
		description: 'New post published',
		scenario_name: 'Post Published',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Post published',
			type: 'action',
			arg_names: ['post_id', 'post'],
			payload_arity: 2,
			properties: {
				post: [
					{ name: 'post_type', label: 'Post Type', type: 'string' },
					{ name: 'post_author', label: 'Author ID', type: 'number' },
					{ name: 'post_title', label: 'Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'post.post_type',
				operator: '=',
				value: 'post',
				value_type: 'text',
				value_label: 'Post type',
				value_placeholder: 'post',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '✏️',
		title: 'WordPress: Post Updated',
		hook_name: 'post_updated',
		description: 'Existing post updated',
		scenario_name: 'Post Updated',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Post updated',
			type: 'action',
			arg_names: ['post_id', 'post_after', 'post_before'],
			payload_arity: 3,
			properties: {
				post_after: [
					{ name: 'post_type', label: 'Post Type', type: 'string' },
					{ name: 'post_status', label: 'Status', type: 'string' },
					{ name: 'post_title', label: 'Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'post_after.post_type',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Post type contains (optional)',
				value_placeholder: 'post / page / product',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '⏸️',
		title: 'WordPress: Post Pending Review',
		hook_name: 'pending_post',
		description: 'Post moved to pending review',
		scenario_name: 'Post Pending Review',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Post pending',
			type: 'action',
			arg_names: ['post'],
			payload_arity: 1,
			properties: {
				post: [
					{ name: 'post_type', label: 'Post Type', type: 'string' },
					{ name: 'post_title', label: 'Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'post.post_type',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Post type contains (optional)',
				value_placeholder: 'post',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '⏰',
		title: 'WordPress: Post Scheduled',
		hook_name: 'future_post',
		description: 'Post scheduled for future',
		scenario_name: 'Post Scheduled',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Post scheduled',
			type: 'action',
			arg_names: ['post'],
			payload_arity: 1,
			properties: {
				post: [
					{ name: 'post_type', label: 'Post Type', type: 'string' },
					{ name: 'post_title', label: 'Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'post.post_type',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Post type contains (optional)',
				value_placeholder: 'post',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🗑️',
		title: 'WordPress: Post Deleted',
		hook_name: 'delete_post',
		description: 'Post permanently deleted',
		scenario_name: 'Post Deleted',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Post deleted',
			type: 'action',
			arg_names: ['post_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'post_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Post ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// WordPress - Pages
	// ============================================
	{
		icon: '📄',
		title: 'WordPress: Page Published',
		hook_name: 'publish_page',
		description: 'New page published',
		scenario_name: 'Page Published',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Page published',
			type: 'action',
			arg_names: ['post_id', 'post'],
			payload_arity: 2,
			properties: {
				post: [
					{ name: 'post_type', label: 'Post Type', type: 'string' },
					{ name: 'post_title', label: 'Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'post.post_type',
				operator: '=',
				value: 'page',
				value_type: 'text',
				value_label: 'Post type',
				value_placeholder: 'page',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// WordPress - Comments
	// ============================================
	{
		icon: '💬',
		title: 'WordPress: New Comment',
		hook_name: 'comment_post',
		description: 'New comment posted',
		scenario_name: 'New Comment',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Comment posted',
			type: 'action',
			arg_names: ['comment_id', 'comment_approved'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'comment_approved',
				operator: '=',
				value: '1',
				value_type: 'select',
				value_label: 'Approval state',
				value_options: [
					{ value: '0', label: 'Pending moderation' },
					{ value: '1', label: 'Approved' }
				],
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '⏳',
		title: 'WordPress: Comment Awaiting Moderation',
		hook_name: 'wp_insert_comment',
		description: 'Comment pending approval',
		scenario_name: 'Comment Awaiting Moderation',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Comment inserted',
			type: 'action',
			arg_names: ['comment_id', 'comment'],
			payload_arity: 2,
			properties: {
				comment: [
					{ name: 'comment_approved', label: 'Approved', type: 'string' },
					{ name: 'comment_author_email', label: 'Author Email', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'comment.comment_approved',
				operator: '=',
				value: '0',
				value_type: 'text',
				value_label: 'Approved flag',
				value_placeholder: '0',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '✅',
		title: 'WordPress: Comment Approved',
		hook_name: 'comment_unapproved_to_approved',
		description: 'Comment approved',
		scenario_name: 'Comment Approved',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Comment approved',
			type: 'action',
			arg_names: ['comment'],
			payload_arity: 1,
			properties: {
				comment: [
					{ name: 'comment_approved', label: 'Approved', type: 'string' },
					{ name: 'comment_author_email', label: 'Author Email', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'comment.comment_approved',
				operator: '=',
				value: '1',
				value_type: 'text',
				value_label: 'Approved flag',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🚫',
		title: 'WordPress: Comment Marked Spam',
		hook_name: 'spam_comment',
		description: 'Comment marked as spam',
		scenario_name: 'Comment Spam',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Spam comment',
			type: 'action',
			arg_names: ['comment_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'comment_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Comment ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// WordPress - Users
	// ============================================
	{
		icon: '👤',
		title: 'WordPress: New User Registered',
		hook_name: 'user_register',
		description: 'New user account created',
		scenario_name: 'New User Registration',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'User registered',
			type: 'action',
			arg_names: ['user_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'user_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'User ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🔄',
		title: 'WordPress: User Role Changed',
		hook_name: 'set_user_role',
		description: 'User role modified',
		scenario_name: 'User Role Changed',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'User role changed',
			type: 'action',
			arg_names: ['user_id', 'role', 'old_roles'],
			payload_arity: 3
		},
		conditions: [
			{
				field: 'role',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'New role contains (optional)',
				value_placeholder: 'administrator / shop_manager',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '✏️',
		title: 'WordPress: User Profile Updated',
		hook_name: 'profile_update',
		description: 'User profile information changed',
		scenario_name: 'Profile Updated',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Profile updated',
			type: 'action',
			arg_names: ['user_id', 'old_user_data'],
			payload_arity: 2,
			properties: {
				old_user_data: [
					{ name: 'user_email', label: 'Email', type: 'string' },
					{ name: 'user_login', label: 'Login', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'old_user_data.user_email',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Old email contains (optional)',
				value_placeholder: '@domain.com',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🔑',
		title: 'WordPress: Password Reset Requested',
		hook_name: 'retrieve_password',
		description: 'User requested password reset',
		scenario_name: 'Password Reset Request',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Password reset',
			type: 'action',
			arg_names: ['user_login'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'user_login',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Username contains (optional)',
				value_placeholder: 'admin',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🔐',
		title: 'WordPress: Failed Login',
		hook_name: 'wp_login_failed',
		description: 'Get an alert whenever WordPress rejects a login attempt.',
		scenario_name: 'Failed Login Attempt',
		required_plugin: 'wordpress-core',
		category: 'security',
		featured: true,
		severity: 'warning',
		default_notes: 'Failed WordPress login for {{username}}.',
		hook_meta: {
			label: 'Login failed',
			type: 'action',
			arg_names: ['username'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'username',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Username contains (optional)',
				value_placeholder: 'admin',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🚨',
		title: 'WordPress: Failed Login (Admin Username)',
		hook_name: 'wp_login_failed',
		description: 'Failed login attempt for a specific username',
		scenario_name: 'Failed Login (Admin)',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Login failed',
			type: 'action',
			arg_names: ['username'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'username',
				operator: '=',
				value: 'admin',
				value_type: 'text',
				value_label: 'Exact username',
				value_placeholder: 'admin',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🔓',
		title: 'WordPress: User Logged In',
		hook_name: 'wp_login',
		description: 'User successfully logged in',
		scenario_name: 'User Login',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'User login',
			type: 'action',
			arg_names: ['user_login', 'user'],
			payload_arity: 2,
			properties: {
				user: [
					{ name: 'user_email', label: 'Email', type: 'string' },
					{ name: 'roles', label: 'Roles', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'user.user_email',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'User email contains (optional)',
				value_placeholder: '@company.com',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🗑️',
		title: 'WordPress: User Deleted',
		hook_name: 'delete_user',
		description: 'User account deleted',
		scenario_name: 'User Deleted',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'User deleted',
			type: 'action',
			arg_names: ['user_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'user_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'User ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '📧',
		title: 'WordPress: User Email Changed',
		hook_name: 'profile_update',
		description: 'User email address updated',
		scenario_name: 'Email Changed',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Profile updated',
			type: 'action',
			arg_names: ['user_id', 'old_user_data'],
			payload_arity: 2,
			properties: {
				old_user_data: [{ name: 'user_email', label: 'Old Email', type: 'string' }]
			}
		},
		conditions: [
			{
				field: 'old_user_data.user_email',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Old email contains (optional)',
				value_placeholder: '@domain.com',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// WordPress - Settings & System
	// ============================================
	{
		icon: '⚙️',
		title: 'WordPress: Option Updated',
		hook_name: 'update_option',
		description: 'Any WordPress option updated',
		scenario_name: 'Option Updated',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Option updated',
			type: 'action',
			arg_names: ['option', 'old_value', 'value'],
			payload_arity: 3
		},
		conditions: [
			{
				field: 'option',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Option name contains',
				value_placeholder: 'blogname',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '➕',
		title: 'WordPress: Option Added',
		hook_name: 'added_option',
		description: 'A new WordPress option was added',
		scenario_name: 'Option Added',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Option added',
			type: 'action',
			arg_names: ['option', 'value'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'option',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Option name contains',
				value_placeholder: 'siteurl',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🗑️',
		title: 'WordPress: Option Deleted',
		hook_name: 'deleted_option',
		description: 'A WordPress option was deleted',
		scenario_name: 'Option Deleted',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Option deleted',
			type: 'action',
			arg_names: ['option'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'option',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Option name contains',
				value_placeholder: 'blogdescription',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🧩',
		title: 'WordPress: Plugin/Theme Updated',
		hook_name: 'upgrader_process_complete',
		description: 'Any plugin/theme/core update completed',
		scenario_name: 'Update Completed',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Upgrader complete',
			type: 'action',
			arg_names: ['upgrader', 'options'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'options',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Options JSON contains (optional)',
				value_placeholder: 'plugin',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '📎',
		title: 'WordPress: Media Uploaded',
		hook_name: 'add_attachment',
		description: 'A new attachment was added',
		scenario_name: 'Media Uploaded',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Attachment added',
			type: 'action',
			arg_names: ['post_ID'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'post_ID',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Attachment ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🗑️',
		title: 'WordPress: Post Trashed',
		hook_name: 'wp_trash_post',
		description: 'A post was moved to trash',
		scenario_name: 'Post Trashed',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Post trashed',
			type: 'action',
			arg_names: ['post_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'post_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Post ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '♻️',
		title: 'WordPress: Post Restored',
		hook_name: 'untrash_post',
		description: 'A post was restored from trash',
		scenario_name: 'Post Restored',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Post untrashed',
			type: 'action',
			arg_names: ['post_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'post_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Post ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// WordPress - Authentication & Workflow
	// ============================================
	{
		icon: '🚪',
		title: 'WordPress: User Logged Out',
		hook_name: 'wp_logout',
		description: 'A user logged out',
		scenario_name: 'User Logout',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'User logout',
			type: 'action',
			arg_names: [],
			payload_arity: 0
		},
		conditions: []
	},
	{
		icon: '🔁',
		title: 'WordPress: Post Status Transition',
		hook_name: 'transition_post_status',
		description: 'Any post status transition',
		scenario_name: 'Post Status Transition',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Post status transition',
			type: 'action',
			arg_names: ['new_status', 'old_status', 'post'],
			payload_arity: 3,
			properties: {
				post: [
					{ name: 'post_type', label: 'Post Type', type: 'string' },
					{ name: 'post_title', label: 'Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'new_status',
				operator: '=',
				value: 'publish',
				value_type: 'text',
				value_label: 'New status',
				value_placeholder: 'publish',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🕒',
		title: 'WordPress: Scheduled Post Published',
		hook_name: 'future_to_publish',
		description: 'A scheduled post was published',
		scenario_name: 'Scheduled Post Published',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Future to publish',
			type: 'action',
			arg_names: ['post'],
			payload_arity: 1,
			properties: {
				post: [
					{ name: 'post_type', label: 'Post Type', type: 'string' },
					{ name: 'post_title', label: 'Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'post.post_type',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Post type contains (optional)',
				value_placeholder: 'post',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🔒',
		title: 'WordPress: Password Reset Completed',
		hook_name: 'password_reset',
		description: 'A user password was reset successfully',
		scenario_name: 'Password Reset Completed',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Password reset completed',
			type: 'action',
			arg_names: ['user', 'new_pass'],
			payload_arity: 2,
			properties: {
				user: [
					{ name: 'user_email', label: 'Email', type: 'string' },
					{ name: 'user_login', label: 'Login', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'user.user_email',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'User email contains (optional)',
				value_placeholder: '@domain.com',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Membership Plugins
	// ============================================
	{
		icon: '⏰',
		title: 'Membership: Expired',
		hook_name: 'pmpro_membership_post_membership_expiry',
		description: 'Membership has expired',
		scenario_name: 'Membership Expired',
		required_plugin: 'paid-memberships-pro',
		hook_meta: {
			label: 'Membership expired',
			type: 'action',
			arg_names: ['user_id', 'level_id'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'level_id',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Membership level ID contains (optional)',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🔄',
		title: 'Membership: Renewed',
		hook_name: 'pmpro_after_checkout',
		description: 'Membership renewed',
		scenario_name: 'Membership Renewed',
		required_plugin: 'paid-memberships-pro',
		hook_meta: {
			label: 'Checkout complete',
			type: 'action',
			arg_names: ['user_id', 'order'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'user_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'User ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Learning - LearnDash
	// ============================================
	{
		icon: '🎓',
		title: 'LearnDash: Course Completed',
		hook_name: 'learndash_course_completed',
		description: 'Know when a learner completes an entire course.',
		scenario_name: 'Course Completed',
		required_plugin: 'sfwd-lms',
		category: 'learning',
		featured: true,
		severity: 'info',
		default_notes: 'A learner completed a LearnDash course.',
		hook_meta: {
			label: 'Course completed',
			type: 'action',
			arg_names: ['course_data'],
			payload_arity: 1
		},
		conditions: []
	},
	{
		icon: '📘',
		title: 'LearnDash: Lesson Completed',
		hook_name: 'learndash_lesson_completed',
		description: 'Receive an alert when a learner finishes a lesson.',
		scenario_name: 'Lesson Completed',
		required_plugin: 'sfwd-lms',
		category: 'learning',
		severity: 'info',
		default_notes: 'A learner completed a LearnDash lesson.',
		hook_meta: {
			label: 'Lesson completed',
			type: 'action',
			arg_names: ['lesson_data'],
			payload_arity: 1
		},
		conditions: []
	},
	{
		icon: '🏆',
		title: 'LearnDash: Quiz Completed',
		hook_name: 'learndash_quiz_completed',
		description: 'Receive an alert whenever a learner completes a quiz, whether they pass or fail.',
		scenario_name: 'Quiz Completed',
		required_plugin: 'sfwd-lms',
		category: 'learning',
		severity: 'info',
		default_notes: 'A learner completed a LearnDash quiz.',
		hook_meta: {
			label: 'Quiz completed',
			type: 'action',
			arg_names: ['quiz_data', 'user'],
			payload_arity: 2,
			properties: {
				user: [
					{ name: 'user_login', label: 'Username', type: 'string' },
					{ name: 'user_email', label: 'Email', type: 'string' }
				]
			}
		},
		conditions: []
	},

	// ============================================
	// WordPress - Plugins & Themes
	// ============================================
	{
		icon: '🔌',
		title: 'WordPress: Plugin Activated',
		hook_name: 'activated_plugin',
		description: 'Plugin activated',
		scenario_name: 'Plugin Activated',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Plugin activated',
			type: 'action',
			arg_names: ['plugin', 'network_wide'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'plugin',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Plugin file contains (optional)',
				value_placeholder: 'woocommerce/woocommerce.php',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '❌',
		title: 'WordPress: Plugin Deactivated',
		hook_name: 'deactivated_plugin',
		description: 'Plugin deactivated',
		scenario_name: 'Plugin Deactivated',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Plugin deactivated',
			type: 'action',
			arg_names: ['plugin', 'network_wide'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'plugin',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Plugin file contains (optional)',
				value_placeholder: 'woocommerce/woocommerce.php',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🎨',
		title: 'WordPress: Theme Changed',
		hook_name: 'switch_theme',
		description: 'Active theme changed',
		scenario_name: 'Theme Changed',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Theme switched',
			type: 'action',
			arg_names: ['new_name', 'new_theme'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'new_name',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'New theme name contains (optional)',
				value_placeholder: 'twentytwentyfour',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🔄',
		title: 'WordPress: Core Updated',
		hook_name: '_core_updated_successfully',
		description: 'WordPress core updated',
		scenario_name: 'Core Updated',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'Core updated',
			type: 'action',
			arg_names: ['wp_version'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'wp_version',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'WordPress version contains (optional)',
				value_placeholder: '6.',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Security Events
	// ============================================
	{
		icon: '👮',
		title: 'WordPress: Administrator Created',
		hook_name: 'set_user_role',
		description: 'Get a security alert when a user is newly granted administrator access.',
		scenario_name: 'Admin Created',
		required_plugin: 'wordpress-core',
		category: 'security',
		featured: true,
		severity: 'critical',
		default_notes: 'User #{{user_id}} was granted administrator access.',
		hook_meta: {
			label: 'User role changed',
			type: 'action',
			arg_names: ['user_id', 'role', 'old_roles'],
			payload_arity: 3
		},
		conditions: [
			{
				field: 'role',
				operator: '=',
				value: 'administrator',
				value_type: 'text',
				value_label: 'New role',
				value_placeholder: 'administrator',
				locked: true,
				lock_field: true,
				lock_operator: true
			},
			{
				field: 'old_roles',
				operator: 'not_contains',
				value: 'administrator',
				value_type: 'text',
				value_label: 'Old roles not contain',
				value_placeholder: 'administrator',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🔑',
		title: 'WordPress: Administrator Login',
		hook_name: 'wp_login',
		description: 'Admin user logged in',
		scenario_name: 'Admin Login',
		required_plugin: 'wordpress-core',
		hook_meta: {
			label: 'User login',
			type: 'action',
			arg_names: ['user_login', 'user'],
			payload_arity: 2,
			properties: {
				user: [
					{ name: 'roles', label: 'Roles', type: 'string' },
					{ name: 'user_email', label: 'Email', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'user.roles',
				operator: 'contains',
				value: 'administrator',
				value_type: 'text',
				value_label: 'Role contains',
				value_placeholder: 'administrator',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Forms - Contact Form 7
	// ============================================
	{
		icon: '📋',
		title: 'Contact Form 7: Submitted',
		hook_name: 'wpcf7_mail_sent',
		description: 'Know when any Contact Form 7 form is submitted successfully.',
		scenario_name: 'CF7 Form Submitted',
		required_plugin: 'contact-form-7',
		category: 'forms',
		featured: true,
		default_notes: 'New submission from {{contact_form.title}}.',
		hook_meta: {
			label: 'Form sent',
			type: 'action',
			arg_names: ['contact_form'],
			payload_arity: 1,
			properties: {
				contact_form: [
					{ name: 'id', label: 'Form ID', type: 'number' },
					{ name: 'title', label: 'Form Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'contact_form.title',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Form title contains (optional)',
				value_placeholder: 'Contact',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '❌',
		title: 'Contact Form 7: Failed Submission',
		hook_name: 'wpcf7_mail_failed',
		description: 'Form submission failed',
		scenario_name: 'Form Failed',
		required_plugin: 'contact-form-7',
		hook_meta: {
			label: 'Mail failed',
			type: 'action',
			arg_names: ['contact_form'],
			payload_arity: 1,
			properties: {
				contact_form: [
					{ name: 'id', label: 'Form ID', type: 'number' },
					{ name: 'title', label: 'Form Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'contact_form.title',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Form title contains (optional)',
				value_placeholder: 'Contact',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '📨',
		title: 'Contact Form 7: Before Send Mail',
		hook_name: 'wpcf7_before_send_mail',
		description: 'Form passed validation and is about to send mail',
		scenario_name: 'CF7 Before Send Mail',
		required_plugin: 'contact-form-7',
		hook_meta: {
			label: 'Before send mail',
			type: 'action',
			arg_names: ['contact_form', 'abort'],
			payload_arity: 2,
			properties: {
				contact_form: [
					{ name: 'id', label: 'Form ID', type: 'number' },
					{ name: 'title', label: 'Form Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'contact_form.title',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Form title contains (optional)',
				value_placeholder: 'Quote',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🛡️',
		title: 'Contact Form 7: Marked as Spam',
		hook_name: 'wpcf7_spam',
		description: 'Submission was flagged as spam',
		scenario_name: 'CF7 Spam Submission',
		required_plugin: 'contact-form-7',
		hook_meta: {
			label: 'Spam detected',
			type: 'action',
			arg_names: ['contact_form'],
			payload_arity: 1,
			properties: {
				contact_form: [
					{ name: 'id', label: 'Form ID', type: 'number' },
					{ name: 'title', label: 'Form Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'contact_form.title',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Form title contains (optional)',
				value_placeholder: 'Contact',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '⚠️',
		title: 'Contact Form 7: Validation Failed',
		hook_name: 'wpcf7_invalid',
		description: 'Submission failed validation checks',
		scenario_name: 'CF7 Validation Failed',
		required_plugin: 'contact-form-7',
		hook_meta: {
			label: 'Invalid submission',
			type: 'action',
			arg_names: ['contact_form'],
			payload_arity: 1,
			properties: {
				contact_form: [
					{ name: 'id', label: 'Form ID', type: 'number' },
					{ name: 'title', label: 'Form Title', type: 'string' }
				]
			}
		},
		conditions: [
			{
				field: 'contact_form.id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Form ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Forms - Gravity Forms
	// ============================================
	{
		icon: '📬',
		title: 'Gravity Forms: Submitted',
		hook_name: 'gform_after_submission',
		description: 'Know when any Gravity Forms entry is submitted successfully.',
		scenario_name: 'Gravity Form Submitted',
		required_plugin: 'gravityforms',
		category: 'forms',
		featured: true,
		default_notes: 'A new Gravity Forms entry was submitted.',
		hook_meta: {
			label: 'Form submitted',
			type: 'action',
			arg_names: ['entry', 'form'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'form.id',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Form ID contains (optional)',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🧾',
		title: 'Gravity Forms: Submitted (Specific Form)',
		hook_name: 'gform_after_submission',
		description: 'Notify only for a specific Gravity Form ID',
		scenario_name: 'Gravity Form Submitted (Specific)',
		required_plugin: 'gravityforms',
		hook_meta: {
			label: 'Form submitted',
			type: 'action',
			arg_names: ['entry', 'form'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'form.id',
				operator: '=',
				value: '1',
				value_type: 'number',
				value_label: 'Form ID',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '📂',
		title: 'Gravity Forms: File Uploaded',
		hook_name: 'gform_after_submission',
		description: 'File uploaded via form',
		scenario_name: 'Form File Uploaded',
		required_plugin: 'gravityforms',
		hook_meta: {
			label: 'Form submitted',
			type: 'action',
			arg_names: ['entry', 'form'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'form.id',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Form ID contains (optional)',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🎯',
		title: 'Gravity Forms: Lead Assigned',
		hook_name: 'gform_post_submission',
		description: 'Form lead assigned to user',
		scenario_name: 'Lead Assigned',
		required_plugin: 'gravityforms',
		hook_meta: {
			label: 'Post submission',
			type: 'action',
			arg_names: ['entry', 'form'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'form.id',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Form ID contains (optional)',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '✅',
		title: 'Gravity Forms: Payment Completed',
		hook_name: 'gform_payment_completed',
		description: 'Payment was completed for a form entry',
		scenario_name: 'GF Payment Completed',
		required_plugin: 'gravityforms',
		hook_meta: {
			label: 'Payment completed',
			type: 'action',
			arg_names: ['entry', 'action'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'entry.form_id',
				operator: '=',
				value: '1',
				value_type: 'number',
				value_label: 'Form ID',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '👤',
		title: 'Gravity Forms: User Registered',
		hook_name: 'gform_user_registered',
		description: 'A user was registered through a Gravity Form',
		scenario_name: 'GF User Registered',
		required_plugin: 'gravityforms',
		hook_meta: {
			label: 'User registered',
			type: 'action',
			arg_names: ['user_id', 'feed', 'entry', 'user_data'],
			payload_arity: 4
		},
		conditions: [
			{
				field: 'user_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'User ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '✏️',
		title: 'Gravity Forms: Entry Updated',
		hook_name: 'gform_after_update_entry',
		description: 'An existing form entry was updated',
		scenario_name: 'GF Entry Updated',
		required_plugin: 'gravityforms',
		hook_meta: {
			label: 'Entry updated',
			type: 'action',
			arg_names: ['form', 'entry_id'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'entry_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Entry ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🗑️',
		title: 'Gravity Forms: Entry Deleted',
		hook_name: 'gform_delete_entry',
		description: 'A form entry was deleted',
		scenario_name: 'GF Entry Deleted',
		required_plugin: 'gravityforms',
		hook_meta: {
			label: 'Entry deleted',
			type: 'action',
			arg_names: ['entry_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'entry_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Entry ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Forms - WPForms, Fluent Forms, Ninja Forms & Elementor Pro
	// ============================================
	{
		icon: '📨',
		title: 'WPForms: Successful Submission',
		hook_name: 'wpforms_process_complete',
		description: 'Get an alert after a WPForms submission finishes successfully.',
		scenario_name: 'WPForms Submission Received',
		required_plugin: 'wpforms-lite',
		category: 'forms',
		featured: true,
		severity: 'info',
		default_notes: 'WPForms entry #{{entry_id}} was submitted successfully.',
		hook_meta: {
			label: 'Successful form submission',
			type: 'action',
			arg_names: ['fields', 'entry', 'form_data', 'entry_id'],
			payload_arity: 4
		},
		conditions: []
	},
	{
		icon: '📝',
		title: 'Fluent Forms: Submission Received',
		hook_name: 'fluentform/submission_inserted',
		description: 'Get an alert after Fluent Forms saves a new submission.',
		scenario_name: 'Fluent Forms Submission Received',
		required_plugin: 'fluentform',
		category: 'forms',
		featured: true,
		severity: 'info',
		default_notes: 'Fluent Forms submission #{{submission_id}} was received.',
		hook_meta: {
			label: 'Form submission received',
			type: 'action',
			arg_names: ['submission_id', 'form_data', 'form'],
			payload_arity: 3
		},
		conditions: []
	},
	{
		icon: '🥷',
		title: 'Ninja Forms: Submission Received',
		hook_name: 'ninja_forms_after_submission',
		description: 'Get an alert after Ninja Forms finishes processing a submission.',
		scenario_name: 'Ninja Forms Submission Received',
		required_plugin: 'ninja-forms',
		category: 'forms',
		severity: 'info',
		default_notes: 'A new Ninja Forms submission was received.',
		hook_meta: {
			label: 'Form submission received',
			type: 'action',
			arg_names: ['form_data'],
			payload_arity: 1
		},
		conditions: []
	},
	{
		icon: '📮',
		title: 'Elementor Pro: Form Submitted',
		hook_name: 'elementor_pro/forms/new_record',
		description: 'Get an alert after an Elementor Pro form runs its configured actions.',
		scenario_name: 'Elementor Form Submitted',
		required_plugin: 'elementor-pro',
		category: 'forms',
		severity: 'info',
		default_notes: 'A new Elementor Pro form submission was received.',
		hook_meta: {
			label: 'Form submitted',
			type: 'action',
			arg_names: ['record', 'ajax_handler'],
			payload_arity: 2
		},
		conditions: []
	},

	// ============================================
	// SEO - Yoast
	// ============================================
	{
		icon: '🔍',
		title: 'Yoast SEO: Indexables Updated',
		hook_name: 'wpseo_save_indexable',
		description: 'Yoast indexables rebuilt',
		scenario_name: 'SEO Index Updated',
		required_plugin: 'wordpress-seo',
		hook_meta: {
			label: 'Indexable saved',
			type: 'action',
			arg_names: ['indexable'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'indexable',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Indexable JSON contains (optional)',
				value_placeholder: 'post',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '📝',
		title: 'Yoast SEO: Post SEO Saved',
		hook_name: 'wpseo_saved_postdata',
		description: 'SEO metadata saved for a post',
		scenario_name: 'Yoast SEO Saved',
		required_plugin: 'wordpress-seo',
		hook_meta: {
			label: 'Post SEO saved',
			type: 'action',
			arg_names: ['post_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'post_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Post ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🚀',
		title: 'Yoast SEO: Search Engines Pinged',
		hook_name: 'wpseo_ping_search_engines',
		description: 'Yoast triggered ping to search engines',
		scenario_name: 'Yoast Search Engine Ping',
		required_plugin: 'wordpress-seo',
		hook_meta: {
			label: 'Ping search engines',
			type: 'action',
			arg_names: [],
			payload_arity: 0
		},
		conditions: []
	},

	// ============================================
	// SEO - Rank Math
	// ============================================
	{
		icon: '📈',
		title: 'Rank Math: Focus Keyword Updated',
		hook_name: 'rank_math/analytics/update_post_keyword',
		description: 'Focus keyword data updated for a post',
		scenario_name: 'Rank Math Keyword Updated',
		required_plugin: 'seo-by-rank-math',
		hook_meta: {
			label: 'Keyword updated',
			type: 'action',
			arg_names: ['object_id', 'focus_keyword'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'object_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Object ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🗺️',
		title: 'Rank Math: Sitemap Cache Flushed',
		hook_name: 'rank_math/sitemap/flush_cache',
		description: 'Sitemap cache was flushed',
		scenario_name: 'Rank Math Sitemap Cache Flushed',
		required_plugin: 'seo-by-rank-math',
		hook_meta: {
			label: 'Sitemap cache flushed',
			type: 'action',
			arg_names: [],
			payload_arity: 0
		},
		conditions: []
	},
	{
		icon: '🔗',
		title: 'Rank Math: Redirection Saved',
		hook_name: 'rank_math/redirection/saved',
		description: 'A redirection rule was created or updated',
		scenario_name: 'Rank Math Redirection Saved',
		required_plugin: 'seo-by-rank-math',
		hook_meta: {
			label: 'Redirection saved',
			type: 'action',
			arg_names: ['redirection_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'redirection_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Redirection ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🧩',
		title: 'Rank Math: Schema Updated',
		hook_name: 'rank_math/schema/validated_data',
		description: 'Schema data validated/updated',
		scenario_name: 'Rank Math Schema Updated',
		required_plugin: 'seo-by-rank-math',
		hook_meta: {
			label: 'Schema validated',
			type: 'action',
			arg_names: ['data', 'jsonld'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'data',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Schema payload contains (optional)',
				value_placeholder: 'Article',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Backups - UpdraftPlus
	// ============================================
	{
		icon: '💾',
		title: 'UpdraftPlus: Backup Completed',
		hook_name: 'updraft_backup_complete',
		description: 'Backup finished successfully',
		scenario_name: 'Backup Completed',
		required_plugin: 'updraftplus',
		hook_meta: {
			label: 'Backup complete',
			type: 'action',
			arg_names: ['backup_info'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'backup_info',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Backup info JSON contains (optional)',
				value_placeholder: 'success',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '❌',
		title: 'UpdraftPlus: Backup Failed',
		hook_name: 'updraft_backup_failed',
		description: 'Get an urgent alert when an UpdraftPlus backup fails.',
		scenario_name: 'Backup Failed',
		required_plugin: 'updraftplus',
		category: 'operations',
		featured: true,
		severity: 'critical',
		default_notes: 'UpdraftPlus backup failed: {{error}}',
		hook_meta: {
			label: 'Backup failed',
			type: 'action',
			arg_names: ['error'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'error',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Error contains (optional)',
				value_placeholder: 'timeout',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Security - Wordfence
	// ============================================
	{
		icon: '🚨',
		title: 'Wordfence: Login Blocked',
		hook_name: 'wordfence_ls_login_blocked',
		description: 'Login attempt blocked',
		scenario_name: 'Login Blocked',
		required_plugin: 'wordfence',
		hook_meta: {
			label: 'Login blocked',
			type: 'action',
			arg_names: ['username', 'ip'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'ip',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'IP contains (optional)',
				value_placeholder: '192.168.',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Page Builders - Elementor
	// ============================================
	{
		icon: '🧱',
		title: 'Elementor: Page Saved',
		hook_name: 'elementor/editor/after_save',
		description: 'Page saved in Elementor',
		scenario_name: 'Elementor Page Saved',
		required_plugin: 'elementor',
		hook_meta: {
			label: 'After save',
			type: 'action',
			arg_names: ['post_id', 'editor_data'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'post_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Post ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🚧',
		title: 'Elementor: Maintenance Mode Changed',
		hook_name: 'elementor/maintenance_mode/mode_changed',
		description: 'Know when Elementor maintenance or coming-soon mode is enabled, changed, or disabled.',
		scenario_name: 'Elementor Maintenance Mode Changed',
		required_plugin: 'elementor',
		category: 'operations',
		featured: true,
		severity: 'warning',
		default_notes: 'Elementor maintenance mode changed from “{{old_value}}” to “{{value}}”.',
		hook_meta: {
			label: 'Maintenance mode changed',
			type: 'action',
			arg_names: ['old_value', 'value'],
			payload_arity: 2
		},
		conditions: []
	},
	{
		icon: '🎨',
		title: 'Elementor: Site Kit Created',
		hook_name: 'elementor/kit/after_new_kit_created',
		description: 'Receive an alert when a new Elementor Site Kit is created and optionally made active.',
		scenario_name: 'Elementor Site Kit Created',
		required_plugin: 'elementor',
		category: 'content',
		severity: 'info',
		default_notes: 'A new Elementor Site Kit was created.',
		hook_meta: {
			label: 'Site Kit created',
			type: 'action',
			arg_names: ['kit_data'],
			payload_arity: 1
		},
		conditions: []
	},
	{
		icon: '🧹',
		title: 'Elementor: Files and Cache Cleared',
		hook_name: 'elementor/core/files/clear_cache',
		description: 'Receive an alert after Elementor clears its generated files and cached asset data.',
		scenario_name: 'Elementor Cache Cleared',
		required_plugin: 'elementor',
		category: 'operations',
		severity: 'info',
		default_notes: 'Elementor generated files and cache were cleared.',
		hook_meta: {
			label: 'Files and cache cleared',
			type: 'action',
			arg_names: [],
			payload_arity: 0
		},
		conditions: []
	},

	// ============================================
	// Email Marketing - FluentCRM
	// ============================================
	{
		icon: '📧',
		title: 'FluentCRM: Contact Added',
		hook_name: 'fluentcrm_contact_added',
		description: 'New CRM contact created',
		scenario_name: 'CRM Contact Added',
		required_plugin: 'fluent-crm',
		hook_meta: {
			label: 'Contact added',
			type: 'action',
			arg_names: ['contact'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'contact',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Contact JSON contains (optional)',
				value_placeholder: '@gmail.com',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🏷️',
		title: 'FluentCRM: Tag Applied',
		hook_name: 'fluentcrm_contact_tag_applied',
		description: 'Tag applied to contact',
		scenario_name: 'CRM Tag Applied',
		required_plugin: 'fluent-crm',
		hook_meta: {
			label: 'Tag applied',
			type: 'action',
			arg_names: ['tag_id', 'contact_id'],
			payload_arity: 2
		},
		conditions: [
			{
				field: 'tag_id',
				operator: '=',
				value: '1',
				value_type: 'number',
				value_label: 'Tag ID',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Caching - LiteSpeed Cache
	// ============================================
	{
		icon: '🚀',
		title: 'LiteSpeed: Cache Purged (All)',
		hook_name: 'litespeed_purged_all',
		description: 'All LiteSpeed cache was purged',
		scenario_name: 'LiteSpeed Cache Purged (All)',
		required_plugin: 'litespeed-cache',
		hook_meta: {
			label: 'Cache purged all',
			type: 'action',
			arg_names: [],
			payload_arity: 0
		},
		conditions: []
	},
	{
		icon: '🧹',
		title: 'LiteSpeed: URL Cache Purged',
		hook_name: 'litespeed_purged_url',
		description: 'A specific URL cache was purged',
		scenario_name: 'LiteSpeed URL Purged',
		required_plugin: 'litespeed-cache',
		hook_meta: {
			label: 'Cache purged URL',
			type: 'action',
			arg_names: ['url'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'url',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'URL contains (optional)',
				value_placeholder: '/shop',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🏷️',
		title: 'LiteSpeed: Tag Cache Purged',
		hook_name: 'litespeed_purged_tag',
		description: 'Cache purged by LiteSpeed tag',
		scenario_name: 'LiteSpeed Tag Purged',
		required_plugin: 'litespeed-cache',
		hook_meta: {
			label: 'Cache purged tag',
			type: 'action',
			arg_names: ['tag'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'tag',
				operator: 'contains',
				value: '',
				value_type: 'text',
				value_label: 'Tag contains (optional)',
				value_placeholder: 'post',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🕒',
		title: 'LiteSpeed: Scheduled Purge Triggered',
		hook_name: 'litespeed_purge_scheduled',
		description: 'Scheduled cache purge job was triggered',
		scenario_name: 'LiteSpeed Scheduled Purge',
		required_plugin: 'litespeed-cache',
		hook_meta: {
			label: 'Scheduled purge',
			type: 'action',
			arg_names: [],
			payload_arity: 0
		},
		conditions: []
	},

	// ============================================
	// SEO / Redirects - Redirection
	// ============================================
	{
		icon: '↪️',
		title: 'Redirection: Redirect Triggered',
		hook_name: 'redirection_redirect',
		description: 'A redirect rule matched and executed',
		scenario_name: 'Redirect Triggered',
		required_plugin: 'redirection',
		hook_meta: {
			label: 'Redirect executed',
			type: 'action',
			arg_names: ['request_url', 'target_url', 'status_code'],
			payload_arity: 3
		},
		conditions: [
			{
				field: 'status_code',
				operator: '=',
				value: '301',
				value_type: 'number',
				value_label: 'HTTP status code',
				value_placeholder: '301',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '➕',
		title: 'Redirection: Rule Created',
		hook_name: 'redirection_after_add',
		description: 'A new redirection rule was created',
		scenario_name: 'Redirect Rule Created',
		required_plugin: 'redirection',
		hook_meta: {
			label: 'Rule created',
			type: 'action',
			arg_names: ['item_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'item_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Rule ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '✏️',
		title: 'Redirection: Rule Updated',
		hook_name: 'redirection_after_update',
		description: 'An existing redirection rule was updated',
		scenario_name: 'Redirect Rule Updated',
		required_plugin: 'redirection',
		hook_meta: {
			label: 'Rule updated',
			type: 'action',
			arg_names: ['item_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'item_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Rule ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},
	{
		icon: '🗑️',
		title: 'Redirection: Rule Deleted',
		hook_name: 'redirection_after_delete',
		description: 'A redirection rule was deleted',
		scenario_name: 'Redirect Rule Deleted',
		required_plugin: 'redirection',
		hook_meta: {
			label: 'Rule deleted',
			type: 'action',
			arg_names: ['item_id'],
			payload_arity: 1
		},
		conditions: [
			{
				field: 'item_id',
				operator: '>=',
				value: '1',
				value_type: 'number',
				value_label: 'Rule ID at/above',
				value_placeholder: '1',
				locked: true,
				lock_field: true,
				lock_operator: true
			}
		]
	},

	// ============================================
	// Caching - WP Rocket
	// ============================================
	{
		icon: '⚡',
		title: 'WP Rocket: Cache Cleared',
		hook_name: 'after_rocket_clean_domain',
		description: 'Site cache cleared',
		scenario_name: 'Cache Cleared',
		required_plugin: 'wp-rocket',
		hook_meta: {
			label: 'Cache cleared',
			type: 'action',
			arg_names: [],
			payload_arity: 0
		},
		conditions: []
	}
];

window.notificatorScenarioTemplates = templates;
