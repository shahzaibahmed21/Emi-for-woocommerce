<?php
/**
 * Settings page: EMI box ke colors / styling control.
 *
 * @package EMI_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMICC_Settings
 */
class EMICC_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var EMICC_Settings|null
	 */
	protected static $instance = null;

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION = 'emicc_settings';

	/**
	 * Instance getter.
	 *
	 * @return EMICC_Settings
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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Menu page.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'EMI & Checkout', 'emi-checkout' ),
			__( 'EMI & Checkout', 'emi-checkout' ),
			'manage_woocommerce',
			'emicc-settings',
			array( $this, 'render_page' ),
			'dashicons-money-alt',
			58
		);
	}

	/**
	 * Settings register.
	 */
	public function register() {
		register_setting(
			'emicc_settings_group',
			self::OPTION,
			array( $this, 'sanitize' )
		);
	}

	/**
	 * Sanitize inputs.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = emicc_default_settings();
		$out      = array();

		$out['font_size']     = isset( $input['font_size'] ) ? max( 8, min( 40, (int) $input['font_size'] ) ) : $defaults['font_size'];
		$out['border_radius'] = isset( $input['border_radius'] ) ? max( 0, min( 60, (int) $input['border_radius'] ) ) : $defaults['border_radius'];

		$color_keys = array( 'text_color', 'title_color', 'bg_color', 'border_color', 'option_bg', 'hover_border', 'accent_color' );
		foreach ( $color_keys as $key ) {
			$color       = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$out[ $key ] = $color ? $color : $defaults[ $key ];
		}

		return $out;
	}

	/**
	 * Color picker assets sirf is page par.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_emicc-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			'emicc-settings-admin',
			EMICC_URL . 'assets/js/emi-settings.js',
			array( 'jquery', 'wp-color-picker' ),
			EMICC_VERSION,
			true
		);
	}

	/**
	 * Ek color field row.
	 *
	 * @param array  $settings Current settings.
	 * @param string $key      Setting key.
	 * @param string $label    Label.
	 */
	protected function color_row( $settings, $key, $label ) {
		?>
		<tr>
			<th scope="row"><label for="emicc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input
					type="text"
					id="emicc-<?php echo esc_attr( $key ); ?>"
					class="emicc-color-field"
					name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>"
					value="<?php echo esc_attr( $settings[ $key ] ); ?>"
					data-default-color="<?php echo esc_attr( $settings[ $key ] ); ?>"
				/>
			</td>
		</tr>
		<?php
	}

	/**
	 * Settings page render.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = emicc_get_settings();
		?>
		<div class="wrap emicc-settings-wrap">
			<h1><?php esc_html_e( 'EMI & Checkout — Styling', 'emi-checkout' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Control the color and styling of the EMI box shown on the product page.', 'emi-checkout' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'emicc_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="emicc-font_size"><?php esc_html_e( 'Font Size (px)', 'emi-checkout' ); ?></label></th>
						<td>
							<input type="number" id="emicc-font_size" min="8" max="40" step="1"
								name="<?php echo esc_attr( self::OPTION ); ?>[font_size]"
								value="<?php echo esc_attr( $settings['font_size'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="emicc-border_radius"><?php esc_html_e( 'Border Radius (px)', 'emi-checkout' ); ?></label></th>
						<td>
							<input type="number" id="emicc-border_radius" min="0" max="60" step="1"
								name="<?php echo esc_attr( self::OPTION ); ?>[border_radius]"
								value="<?php echo esc_attr( $settings['border_radius'] ); ?>" />
						</td>
					</tr>
					<?php
					$this->color_row( $settings, 'title_color', __( 'Title Color', 'emi-checkout' ) );
					$this->color_row( $settings, 'text_color', __( 'Text / Font Color', 'emi-checkout' ) );
					$this->color_row( $settings, 'accent_color', __( 'Accent Color (selected + amount)', 'emi-checkout' ) );
					$this->color_row( $settings, 'bg_color', __( 'Box Background Color', 'emi-checkout' ) );
					$this->color_row( $settings, 'option_bg', __( 'Option Background Color', 'emi-checkout' ) );
					$this->color_row( $settings, 'border_color', __( 'Border Color', 'emi-checkout' ) );
					$this->color_row( $settings, 'hover_border', __( 'Hover Border Color', 'emi-checkout' ) );
					?>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
