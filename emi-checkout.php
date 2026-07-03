<?php
/**
 * Plugin Name:       EMI & Lead Checkout
 * Plugin URI:        https://example.com/
 * Description:
 * Version:           1.0.1
 * Author:            SM Devs
 * Author URI:        mailto:shahzaibahmed21.05.1998@gmail.com
 * Text Domain:       emi-checkout
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * WC requires at least: 5.0
 * WC tested up to:   9.0
 *
 * @package EMI_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'EMICC_VERSION', '1.0.4' );
define( 'EMICC_FILE', __FILE__ );
define( 'EMICC_DIR', plugin_dir_path( __FILE__ ) );
define( 'EMICC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Kisi product ke admin-defined EMI packages (normalized + sorted).
 *
 * Har package: array( 'months' => int, 'downpayment' => float, 'price' => float ).
 *
 * @param WC_Product|int $product Product object ya ID.
 * @return array[]  Packages list (khali ho sakti hai).
 */
function emicc_get_packages( $product ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return array();
	}
	if ( ! $product instanceof WC_Product ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product instanceof WC_Product ) {
		return array();
	}

	$raw = $product->get_meta( '_emi_packages' );
	if ( empty( $raw ) || ! is_array( $raw ) ) {
		return array();
	}

	$packages = array();
	foreach ( $raw as $row ) {
		$months = isset( $row['months'] ) ? (int) $row['months'] : 0;
		$price  = isset( $row['price'] ) ? (float) $row['price'] : 0.0;
		$down   = isset( $row['downpayment'] ) ? (float) $row['downpayment'] : 0.0;

		if ( $months > 0 && $price > 0 ) {
			if ( $down < 0 ) {
				$down = 0.0;
			}
			// Downpayment total se barabar/zyada na ho.
			if ( $down >= $price ) {
				$down = 0.0;
			}
			$packages[] = array(
				'months'      => $months,
				'downpayment' => $down,
				'price'       => $price,
			);
		}
	}

	usort(
		$packages,
		function ( $a, $b ) {
			return $a['months'] - $b['months'];
		}
	);

	return $packages;
}

/**
 * Kisi product ki EMI calculation (no interest, simple division).
 *
 * Har package ke liye: EMI monthly = ( package total price - package downpayment ) / months.
 *
 * @param WC_Product|int $product Product object ya ID.
 * @return array|false  Array of data, ya false agar koi EMI package na ho.
 */
function emicc_get_emi_data( $product ) {
	$packages = emicc_get_packages( $product );
	if ( empty( $packages ) ) {
		return false;
	}

	$computed = array();
	foreach ( $packages as $i => $pkg ) {
		$financed       = $pkg['price'] - $pkg['downpayment'];
		$computed[ $i ] = array(
			'months'      => $pkg['months'],
			'price'       => $pkg['price'],
			'downpayment' => $pkg['downpayment'],
			'financed'    => $financed,
			'monthly'     => $pkg['months'] > 0 ? $financed / $pkg['months'] : 0.0,
		);
	}

	return array(
		'packages' => $computed,
	);
}

/**
 * Default styling settings.
 *
 * @return array
 */
function emicc_default_settings() {
	return array(
		'font_size'     => 15,
		'text_color'    => '#333333',
		'title_color'   => '#222222',
		'bg_color'      => '#fafbfc',
		'border_color'  => '#e3e6ea',
		'border_radius' => 10,
		'option_bg'     => '#ffffff',
		'hover_border'  => '#b9c2cc',
		'accent_color'  => '#1a7f37',
	);
}

/**
 * Saved settings (defaults ke saath merged).
 *
 * @return array
 */
function emicc_get_settings() {
	$saved = get_option( 'emicc_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, emicc_default_settings() );
}

/**
 * Settings se dynamic CSS (CSS variables) generate karo.
 *
 * @return string
 */
function emicc_inline_css() {
	$s = emicc_get_settings();

	return '.emicc-product-emi{'
		. '--emicc-font-size:' . (int) $s['font_size'] . 'px;'
		. '--emicc-text-color:' . esc_attr( $s['text_color'] ) . ';'
		. '--emicc-title-color:' . esc_attr( $s['title_color'] ) . ';'
		. '--emicc-bg:' . esc_attr( $s['bg_color'] ) . ';'
		. '--emicc-border:' . esc_attr( $s['border_color'] ) . ';'
		. '--emicc-radius:' . (int) $s['border_radius'] . 'px;'
		. '--emicc-option-bg:' . esc_attr( $s['option_bg'] ) . ';'
		. '--emicc-hover-border:' . esc_attr( $s['hover_border'] ) . ';'
		. '--emicc-accent:' . esc_attr( $s['accent_color'] ) . ';'
		. '}';
}

/**
 * Plugin tabhi load karo jab WooCommerce active ho.
 */
function emicc_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'emicc_missing_wc_notice' );
		return;
	}

	require_once EMICC_DIR . 'includes/class-emicc-emi.php';
	require_once EMICC_DIR . 'includes/class-emicc-checkout.php';
	require_once EMICC_DIR . 'includes/class-emicc-settings.php';

	EMICC_EMI::instance();
	EMICC_Checkout::instance();
	EMICC_Settings::instance();
}
add_action( 'plugins_loaded', 'emicc_init' );

/**
 * Admin notice jab WooCommerce na ho.
 */
function emicc_missing_wc_notice() {
	echo '<div class="notice notice-error"><p><strong>EMI &amp; Lead Checkout</strong>: ';
	echo esc_html__( 'This plugin requires WooCommerce to be installed and active.', 'emi-checkout' );
	echo '</p></div>';
}

/**
 * Frontend assets (CSS/JS).
 */
function emicc_enqueue_assets() {
	if ( ! function_exists( 'is_product' ) ) {
		return;
	}

	if ( is_product() || is_cart() || is_checkout() ) {
		wp_enqueue_style( 'emicc-frontend', EMICC_URL . 'assets/css/emi-checkout.css', array(), EMICC_VERSION );
		wp_add_inline_style( 'emicc-frontend', emicc_inline_css() );
		wp_enqueue_script( 'emicc-frontend', EMICC_URL . 'assets/js/emi-checkout.js', array( 'jquery' ), EMICC_VERSION, true );
		wp_localize_script(
			'emicc-frontend',
			'emiccL10n',
			array(
				'months'  => __( '%d Months', 'emi-checkout' ),
				'total'   => __( 'Total: %s', 'emi-checkout' ),
				'advance' => __( 'Advance: %s', 'emi-checkout' ),
				'monthly' => __( '%s / month', 'emi-checkout' ),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'emicc_enqueue_assets' );

/**
 * Admin assets (sirf product edit screen par).
 *
 * @param string $hook Current admin page hook.
 */
function emicc_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style( 'emicc-admin', EMICC_URL . 'assets/css/emi-admin.css', array(), EMICC_VERSION );
	wp_enqueue_script( 'emicc-admin', EMICC_URL . 'assets/js/emi-admin.js', array( 'jquery' ), EMICC_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'emicc_admin_assets' );

/**
 * HPOS (High-Performance Order Storage) compatibility declare karo.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', EMICC_FILE, true );
		}
	}
);
