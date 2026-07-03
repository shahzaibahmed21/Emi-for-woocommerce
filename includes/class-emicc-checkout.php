<?php
/**
 * Payment-free "lead" checkout.
 *
 * - Checkout par koi payment method / payment box nahi.
 * - Customer standard billing form bharta hai aur order submit ho jata hai.
 * - Order WooCommerce me normal order (processing) ki tarah aa jata hai = lead.
 * - Delivery = shop pickup (koi shipping force nahi).
 *
 * @package EMI_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMICC_Checkout
 */
class EMICC_Checkout {

	/**
	 * Singleton instance.
	 *
	 * @var EMICC_Checkout|null
	 */
	protected static $instance = null;

	/**
	 * Instance getter.
	 *
	 * @return EMICC_Checkout
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: hooks register.
	 */
	private function __construct() {
		// Core: cart/order ko "payment ki zaroorat nahi" bana do -> payment box hat jaata hai
		// aur checkout process bina gateway ke order bana deta hai.
		add_filter( 'woocommerce_cart_needs_payment', '__return_false' );
		add_filter( 'woocommerce_order_needs_payment', '__return_false', 10, 1 );

		// Place order button text.
		add_filter( 'woocommerce_order_button_text', array( $this, 'order_button_text' ) );

		// Checkout par info notice.
		add_action( 'woocommerce_before_checkout_form', array( $this, 'checkout_notice' ), 5 );

		// Pickup-only: shipping calculation hata do (delivery shop se).
		add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
		add_filter( 'woocommerce_cart_ship_to_different_address', '__return_false' );

		// Naya order banne par status "processing" (lead) set ho aur note add ho.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processed' ), 10, 3 );

		// Admin order list me ek pehchaan badge.
		add_filter( 'woocommerce_admin_order_preview_get_order_details', array( $this, 'order_preview_details' ), 10, 2 );
	}

	/**
	 * Place order button text.
	 *
	 * @return string
	 */
	public function order_button_text() {
		return __( 'Submit Order', 'emi-checkout' );
	}

	/**
	 * Checkout page par info notice.
	 */
	public function checkout_notice() {
		if ( ! function_exists( 'wc_print_notice' ) ) {
			return;
		}
		wc_print_notice(
			__( 'No online payment required. Just fill in your details and submit the order — our team will contact you, and delivery will be arranged/collected at the shop.', 'emi-checkout' ),
			'notice'
		);
	}

	/**
	 * Order process hone par: lead ke taur par mark karo.
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $posted   Posted checkout data.
	 * @param WC_Order $order    Order object.
	 */
	public function on_order_processed( $order_id, $posted, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		// Lead flag.
		$order->update_meta_data( '_emicc_lead', 'yes' );

		$order->add_order_note( __( 'Lead order: placed via payment-free checkout. Delivery: shop pickup.', 'emi-checkout' ) );

		// Agar abhi tak status pending hai to processing kar do (no-payment flow me
		// aam taur par payment_complete already processing kar deta hai, yeh safety hai).
		if ( $order->has_status( 'pending' ) ) {
			$order->update_status( 'processing', __( 'Lead received (no payment required).', 'emi-checkout' ) );
		} else {
			$order->save();
		}
	}

	/**
	 * Order preview (admin) me lead badge.
	 *
	 * @param array    $details Preview details.
	 * @param WC_Order $order   Order.
	 * @return array
	 */
	public function order_preview_details( $details, $order ) {
		if ( $order instanceof WC_Order && 'yes' === $order->get_meta( '_emicc_lead' ) ) {
			$details['payment_via'] = __( 'Lead (no payment)', 'emi-checkout' );
		}
		return $details;
	}
}
