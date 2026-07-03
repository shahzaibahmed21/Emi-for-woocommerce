<?php
/**
 * EMI feature handler.
 *
 * - Admin: har product me "Flat Downpayment" + custom EMI Packages (months + price), jitne chahe.
 * - Frontend: product page par packages list + selection (add-to-cart form ke andar).
 * - Cart/Order: chosen package ki price line item par set hoti hai aur breakdown
 *   (downpayment + monthly x months) checkout/admin par show hota hai.
 *
 * @package EMI_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMICC_EMI
 */
class EMICC_EMI {

	/**
	 * Singleton instance.
	 *
	 * @var EMICC_EMI|null
	 */
	protected static $instance = null;

	/**
	 * Instance getter.
	 *
	 * @return EMICC_EMI
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
		// Admin product fields (simple products only).
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_field' ) );

		// Admin variation fields (variable products — har variation ka apna plan).
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'add_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );

		// Frontend: product page display + selection (add-to-cart FORM ke andar).
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_product_emi' ), 15 );
		add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_emi_data' ), 10, 3 );

		// Cart: chosen package capture + price set + display.
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'adjust_cart_price' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );

		// Order: line item meta save.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_line_item' ), 10, 4 );

		// Regular price ke bagair bhi product purchasable + price display.
		add_filter( 'woocommerce_is_purchasable', array( $this, 'make_purchasable' ), 10, 2 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'price_html' ), 10, 2 );
		add_filter( 'woocommerce_variation_is_active', array( $this, 'variation_is_active' ), 10, 2 );
		add_filter( 'woocommerce_variation_is_visible', array( $this, 'variation_is_visible' ), 10, 4 );
		add_filter( 'woocommerce_product_variation_get_stock_status', array( $this, 'variation_stock_status' ), 10, 2 );
		add_filter( 'woocommerce_product_is_in_stock', array( $this, 'parent_is_in_stock' ), 10, 2 );

		// Variable product: button swatches + hide default dropdown.
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'variation_swatches_html' ), 20, 2 );
	}

	/* ------------------------------------------------------------------ *
	 *  ADMIN
	 * ------------------------------------------------------------------ */

	/**
	 * Product data (General tab) me downpayment + EMI packages fields.
	 */
	public function add_product_field() {
		global $post;

		$product = ( $post && isset( $post->ID ) ) ? wc_get_product( $post->ID ) : null;
		if ( $product && $product->is_type( 'variable' ) ) {
			return;
		}

		echo '<div class="options_group emicc-options">';
		$this->render_packages_admin_table( emicc_get_packages( $product ), null );
		echo '</div>';
	}

	/**
	 * Har variation ke pricing section me EMI packages table.
	 *
	 * @param int     $loop           Variation loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post.
	 */
	public function add_variation_fields( $loop, $variation_data, $variation ) {
		$variation_product = wc_get_product( $variation->ID );
		$packages          = $variation_product ? emicc_get_packages( $variation_product ) : array();
		?>
		<div class="emicc-variation-packages emicc-packages form-row form-row-full">
			<?php $this->render_packages_admin_table( $packages, null, (int) $variation->ID ); ?>
		</div>
		<?php
	}

	/**
	 * Admin EMI packages table (simple product ya variation).
	 *
	 * @param array    $packages     Saved packages.
	 * @param int|null $loop         Deprecated loop index (simple product only legacy).
	 * @param int|null $variation_id Variation ID when saving per-variation packages.
	 */
	protected function render_packages_admin_table( $packages, $loop = null, $variation_id = null ) {
		$symbol = get_woocommerce_currency_symbol();
		$tpl_id = $variation_id ? 'emicc-package-row-tpl-' . $variation_id : 'emicc-package-row-tpl';
		?>
		<div class="emicc-packages form-field">
			<p class="emicc-packages-label">
				<strong><?php esc_html_e( 'EMI Packages', 'emi-checkout' ); ?></strong><br />
				<span class="description">
					<?php esc_html_e( 'Add as many packages as you like. For each package enter Months, Downpayment and Total Payment. Monthly EMI = (Total Payment − Downpayment) ÷ Months. No regular price needed — only these packages are used.', 'emi-checkout' ); ?>
				</span>
			</p>

			<table class="widefat emicc-packages-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Months', 'emi-checkout' ); ?></th>
						<th>
							<?php
							/* translators: %s currency symbol */
							printf( esc_html__( 'Downpayment (%s)', 'emi-checkout' ), esc_html( $symbol ) );
							?>
						</th>
						<th>
							<?php
							/* translators: %s currency symbol */
							printf( esc_html__( 'Total Payment (%s)', 'emi-checkout' ), esc_html( $symbol ) );
							?>
						</th>
						<th class="emicc-col-remove"></th>
					</tr>
				</thead>
				<tbody class="emicc-packages-body">
					<?php
					if ( ! empty( $packages ) ) {
						foreach ( $packages as $pkg ) {
							$this->render_package_row(
								(int) $pkg['months'],
								(float) $pkg['downpayment'],
								(float) $pkg['price'],
								null,
								$variation_id
							);
						}
					}
					?>
				</tbody>
			</table>

			<p>
				<button type="button" class="button emicc-add-package">+ <?php esc_html_e( 'Add Package', 'emi-checkout' ); ?></button>
			</p>

			<script type="text/template" id="<?php echo esc_attr( $tpl_id ); ?>" class="emicc-package-row-tpl">
				<?php $this->render_package_row( '', '', '', null, $variation_id ); ?>
			</script>
		</div>
		<?php
	}

	/**
	 * Ek package row markup.
	 *
	 * @param int|string   $months      Months value.
	 * @param float|string $downpayment Downpayment value.
	 * @param float|string $price       Total payment value.
	 * @param int|null     $loop         Simple product legacy (unused).
	 * @param int|null     $variation_id Variation ID for variable products.
	 */
	protected function render_package_row( $months, $downpayment, $price, $loop = null, $variation_id = null ) {
		if ( $variation_id ) {
			$vid         = absint( $variation_id );
			$months_name = 'emicc_emi_packages[' . $vid . '][months][]';
			$down_name   = 'emicc_emi_packages[' . $vid . '][downpayment][]';
			$price_name  = 'emicc_emi_packages[' . $vid . '][price][]';
		} else {
			$months_name = 'emi_pkg_months[]';
			$down_name   = 'emi_pkg_downpayment[]';
			$price_name  = 'emi_pkg_price[]';
		}
		?>
		<tr class="emicc-package-row">
			<td>
				<input type="number" min="1" step="1" name="<?php echo esc_attr( $months_name ); ?>" value="<?php echo esc_attr( $months ); ?>" placeholder="e.g. 10" />
			</td>
			<td>
				<input type="number" min="0" step="0.01" name="<?php echo esc_attr( $down_name ); ?>" value="<?php echo esc_attr( $downpayment ); ?>" placeholder="e.g. 5000" />
			</td>
			<td>
				<input type="number" min="0" step="0.01" name="<?php echo esc_attr( $price_name ); ?>" value="<?php echo esc_attr( $price ); ?>" placeholder="e.g. 30000" />
			</td>
			<td class="emicc-col-remove">
				<button type="button" class="button emicc-remove-package" aria-label="<?php esc_attr_e( 'Remove', 'emi-checkout' ); ?>">&times;</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * POST se packages array parse karo.
	 *
	 * @param array $months_in Months values.
	 * @param array $down_in   Downpayment values.
	 * @param array $price_in  Total payment values.
	 * @return array
	 */
	protected function parse_packages_from_post( $months_in, $down_in, $price_in ) {
		$packages = array();
		foreach ( $months_in as $i => $m ) {
			$m = (int) $m;
			$p = isset( $price_in[ $i ] ) ? (float) wc_format_decimal( wc_clean( $price_in[ $i ] ) ) : 0.0;
			$d = isset( $down_in[ $i ] ) ? (float) wc_format_decimal( wc_clean( $down_in[ $i ] ) ) : 0.0;
			if ( $m > 0 && $p > 0 ) {
				$packages[] = array(
					'months'      => $m,
					'downpayment' => max( 0.0, $d ),
					'price'       => $p,
				);
			}
		}
		return $packages;
	}

	/**
	 * Packages meta save karo kisi product/variation par.
	 *
	 * @param WC_Product $product  Product object.
	 * @param array      $packages Packages array.
	 */
	protected function save_packages_to_product( $product, $packages ) {
		if ( empty( $packages ) ) {
			$product->delete_meta_data( '_emi_packages' );
		} else {
			$product->update_meta_data( '_emi_packages', $packages );
		}
		$product->save();
	}

	/**
	 * Fields save.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_field( $post_id ) {
		$product = wc_get_product( $post_id );
		if ( ! $product || $product->is_type( 'variable' ) ) {
			return;
		}

		$months_in = isset( $_POST['emi_pkg_months'] ) ? (array) wp_unslash( $_POST['emi_pkg_months'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$down_in   = isset( $_POST['emi_pkg_downpayment'] ) ? (array) wp_unslash( $_POST['emi_pkg_downpayment'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$price_in  = isset( $_POST['emi_pkg_price'] ) ? (array) wp_unslash( $_POST['emi_pkg_price'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$this->save_packages_to_product( $product, $this->parse_packages_from_post( $months_in, $down_in, $price_in ) );
	}

	/**
	 * Variation EMI packages save.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation loop index.
	 */
	public function save_variation_fields( $variation_id, $loop ) {
		unset( $loop );

		$variation = wc_get_product( $variation_id );
		if ( ! $variation ) {
			return;
		}

		$vid = absint( $variation_id );

		$months_in = array();
		$down_in   = array();
		$price_in  = array();

		if ( isset( $_POST['emicc_emi_packages'][ $vid ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$block = wp_unslash( $_POST['emicc_emi_packages'][ $vid ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $block['months'] ) ) {
				$months_in = (array) $block['months'];
			}
			if ( isset( $block['downpayment'] ) ) {
				$down_in = (array) $block['downpayment'];
			}
			if ( isset( $block['price'] ) ) {
				$price_in = (array) $block['price'];
			}
		}

		$this->save_packages_to_product( $variation, $this->parse_packages_from_post( $months_in, $down_in, $price_in ) );
	}

	/* ------------------------------------------------------------------ *
	 *  FRONTEND (single product)
	 * ------------------------------------------------------------------ */

	/**
	 * Product page par EMI packages + selector render karo.
	 */
	public function render_product_emi() {
		global $product;

		if ( $product->is_type( 'variable' ) ) {
			if ( ! $this->variable_has_emi_packages( $product ) ) {
				return;
			}
			$this->render_emi_shell( true );
			return;
		}

		$data = emicc_get_emi_data( $product );
		if ( ! $data ) {
			return;
		}

		$this->render_emi_shell( false, $data['packages'] );
	}

	/**
	 * Kya is variable product ki kisi variation me EMI packages hain?
	 *
	 * @param WC_Product_Variable $product Variable product.
	 * @return bool
	 */
	protected function variable_has_emi_packages( $product ) {
		foreach ( $product->get_children() as $variation_id ) {
			if ( emicc_get_packages( $variation_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * EMI box shell render karo (simple = plans ke saath, variable = khali shell).
	 *
	 * @param bool       $hidden   Variable product par shuru me chhupa ho.
	 * @param array|null $packages Simple product packages.
	 */
	protected function render_emi_shell( $hidden = false, $packages = null ) {
		?>
		<div class="emicc-product-emi<?php echo $hidden ? ' emicc-variable-emi' : ''; ?>"<?php echo $hidden ? ' hidden' : ''; ?>>
			<div class="emicc-emi-heading">
				<span class="emicc-emi-title"><?php esc_html_e( 'Easy EMI Plans', 'emi-checkout' ); ?></span>
			</div>

			<ul class="emicc-plan-list">
				<?php
				if ( $packages ) {
					$this->render_plan_list_items( $packages );
				}
				?>
			</ul>
			<p class="emicc-emi-note"><?php esc_html_e( 'EMI is an installment plan only. No online payment — after you confirm the order, it will be arranged/collected at the shop.', 'emi-checkout' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Plan list items render (simple product PHP, variable JS bhi same structure use karta hai).
	 *
	 * @param array $packages Computed packages.
	 */
	protected function render_plan_list_items( $packages ) {
		$first = true;
		foreach ( $packages as $index => $pkg ) {
			?>
			<li class="emicc-plan-option">
				<label>
					<input type="radio" name="emicc_plan" value="<?php echo esc_attr( $index ); ?>" <?php checked( $first ); ?> />
					<span class="emicc-plan-name">
						<?php
						/* translators: %d: number of months */
						printf( esc_html__( '%d Months', 'emi-checkout' ), (int) $pkg['months'] );
						?>
					</span>
					<span class="emicc-plan-total-col">
						<?php
						/* translators: %s: total payment */
						printf( esc_html__( 'Total: %s', 'emi-checkout' ), wp_kses_post( wc_price( $pkg['price'] ) ) );
						?>
						<?php if ( $pkg['downpayment'] > 0 ) : ?>
							<small class="emicc-plan-down">
								<?php
								/* translators: %s: downpayment */
								printf( esc_html__( 'Advance: %s', 'emi-checkout' ), wp_strip_all_tags( wc_price( $pkg['downpayment'] ) ) );
								?>
							</small>
						<?php endif; ?>
					</span>
					<span class="emicc-plan-amount">
						<?php
						/* translators: %s: monthly amount */
						printf( esc_html__( '%s / month', 'emi-checkout' ), wp_kses_post( wc_price( $pkg['monthly'] ) ) );
						?>
					</span>
				</label>
			</li>
			<?php
			$first = false;
		}
	}

	/**
	 * WooCommerce variation JSON me EMI packages add karo (frontend swap ke liye).
	 *
	 * @param array                $data      Variation data.
	 * @param WC_Product_Variable  $product   Parent product.
	 * @param WC_Product_Variation $variation Variation product.
	 * @return array
	 */
	public function add_variation_emi_data( $data, $product, $variation ) {
		$packages = emicc_get_packages( $variation );
		$emi      = emicc_get_emi_data( $variation );

		if ( $emi && ! empty( $emi['packages'] ) ) {
			$data['emicc_packages'] = $this->prepare_packages_for_js( $emi['packages'] );

			$min_price = min( wp_list_pluck( $packages, 'price' ) );

			if ( empty( $data['display_price'] ) || (float) $data['display_price'] <= 0 ) {
				$data['display_price']         = $min_price;
				$data['display_regular_price'] = $min_price;
				$data['price_html']            = wc_price( $min_price );
			}

			$data['is_in_stock']       = true;
			$data['is_purchasable']    = true;
			$data['is_visible']        = true;
			$data['variation_is_active'] = true;
			$data['availability_html'] = '';
		} else {
			$data['emicc_packages'] = array();
		}

		return $data;
	}

	/**
	 * Packages ko frontend JS ke liye format karo.
	 *
	 * @param array $packages Computed packages.
	 * @return array
	 */
	protected function prepare_packages_for_js( $packages ) {
		$out = array();
		foreach ( $packages as $index => $pkg ) {
			$out[] = array(
				'index'        => (int) $index,
				'months'       => (int) $pkg['months'],
				'downpayment'  => (float) $pkg['downpayment'],
				'price_html'   => $this->plain_price_text( $pkg['price'] ),
				'advance_html' => $this->plain_price_text( $pkg['downpayment'] ),
				'monthly_html' => $this->plain_price_text( $pkg['monthly'] ),
			);
		}
		return $out;
	}

	/**
	 * wc_price output ko plain readable text me convert karo (HTML entities ke bagair).
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	protected function plain_price_text( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Agar product ke EMI packages hain to use purchasable banao
	 * chahe regular price set na ho.
	 *
	 * @param bool       $purchasable Existing flag.
	 * @param WC_Product $product     Product.
	 * @return bool
	 */
	public function make_purchasable( $purchasable, $product ) {
		if ( $purchasable ) {
			return true;
		}
		if ( emicc_get_packages( $product ) ) {
			return true;
		}
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				if ( emicc_get_packages( $variation_id ) ) {
					return true;
				}
			}
		}
		return $purchasable;
	}

	/**
	 * Jab regular price na ho to lowest package total ko price ki tarah dikhao.
	 *
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function price_html( $html, $product ) {
		if ( $product->is_type( 'variable' ) ) {
			$min_prices = array();
			foreach ( $product->get_children() as $variation_id ) {
				$pkgs = emicc_get_packages( $variation_id );
				if ( $pkgs ) {
					$min_prices[] = min( wp_list_pluck( $pkgs, 'price' ) );
				}
			}
			if ( ! empty( $min_prices ) ) {
				$emi_from = sprintf(
					/* translators: %s: starting total price */
					esc_html__( 'From %s', 'emi-checkout' ),
					wp_strip_all_tags( wc_price( min( $min_prices ) ) )
				);
				if ( '' === $product->get_price() || '' === $html ) {
					return $emi_from;
				}
			}
		}

		if ( '' !== $product->get_price() && '' !== $html ) {
			return $html;
		}

		$packages = emicc_get_packages( $product );
		if ( empty( $packages ) ) {
			return $html;
		}

		$totals = wp_list_pluck( $packages, 'price' );
		$min    = min( $totals );

		/* translators: %s: starting total price */
		return sprintf( esc_html__( 'From %s', 'emi-checkout' ), wc_price( $min ) );
	}

	/**
	 * EMI packages wali variation ko active rakho.
	 *
	 * @param bool                   $active    Is active.
	 * @param WC_Product_Variation   $variation Variation.
	 * @return bool
	 */
	public function variation_is_active( $active, $variation ) {
		if ( $active ) {
			return true;
		}
		return emicc_get_packages( $variation ) ? true : $active;
	}

	/**
	 * EMI packages wali variation ko visible rakho.
	 *
	 * @param bool                   $visible       Is visible.
	 * @param int                    $variation_id  Variation ID.
	 * @param int                    $product_id    Parent ID.
	 * @param WC_Product_Variation   $variation     Variation object.
	 * @return bool
	 */
	public function variation_is_visible( $visible, $variation_id, $product_id, $variation ) {
		unset( $product_id, $variation );
		if ( $visible ) {
			return true;
		}
		return emicc_get_packages( $variation_id ) ? true : $visible;
	}

	/**
	 * EMI-only variations ko in-stock treat karo.
	 *
	 * @param string               $status  Stock status.
	 * @param WC_Product_Variation $product Variation.
	 * @return string
	 */
	public function variation_stock_status( $status, $product ) {
		if ( 'instock' === $status ) {
			return $status;
		}
		if ( emicc_get_packages( $product ) ) {
			return 'instock';
		}
		return $status;
	}

	/**
	 * Variable parent ko in-stock rakho jab kisi variation me EMI packages hon.
	 *
	 * @param bool       $in_stock In stock flag.
	 * @param WC_Product $product  Product.
	 * @return bool
	 */
	public function parent_is_in_stock( $in_stock, $product ) {
		if ( $in_stock || ! $product->is_type( 'variable' ) ) {
			return $in_stock;
		}
		foreach ( $product->get_children() as $variation_id ) {
			if ( emicc_get_packages( $variation_id ) ) {
				return true;
			}
		}
		return $in_stock;
	}

	/**
	 * Variation dropdown ke saath button swatches render karo.
	 *
	 * @param string $html Original dropdown HTML.
	 * @param array  $args   Dropdown args.
	 * @return string
	 */
	public function variation_swatches_html( $html, $args ) {
		if ( empty( $args['options'] ) || ! is_array( $args['options'] ) ) {
			return $html;
		}

		$attribute = $args['attribute'] ?? '';
		$selected  = $args['selected'] ?? '';

		$swatches  = '<div class="emicc-variation-swatches" data-attribute="' . esc_attr( $attribute ) . '">';
		foreach ( $args['options'] as $option ) {
			$is_selected = ( (string) $selected === (string) $option );
			$swatches   .= sprintf(
				'<button type="button" class="emicc-swatch%s" data-value="%s" aria-pressed="%s">%s</button>',
				$is_selected ? ' selected' : '',
				esc_attr( $option ),
				$is_selected ? 'true' : 'false',
				esc_html( $option )
			);
		}
		$swatches .= '</div>';

		return '<div class="emicc-variation-control">' . $swatches . '<div class="emicc-variation-select">' . $html . '</div></div>';
	}

	/* ------------------------------------------------------------------ *
	 *  CART / ORDER
	 * ------------------------------------------------------------------ */

	/**
	 * Add-to-cart par chosen EMI package ko cart item ke saath store karo.
	 *
	 * @param array $cart_item_data Existing cart item data.
	 * @param int   $product_id     Product ID.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id ) {
		if ( ! isset( $_REQUEST['emicc_plan'] ) || '' === $_REQUEST['emicc_plan'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $cart_item_data;
		}

		$index = (int) wc_clean( wp_unslash( $_REQUEST['emicc_plan'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$target_id = absint( $product_id );
		if ( ! empty( $_REQUEST['variation_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$target_id = absint( $_REQUEST['variation_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$data = emicc_get_emi_data( $target_id );
		if ( ! $data || ! isset( $data['packages'][ $index ] ) ) {
			return $cart_item_data;
		}

		$pkg = $data['packages'][ $index ];

		$cart_item_data['emicc_emi'] = array(
			'months'      => $pkg['months'],
			'monthly'     => $pkg['monthly'],
			'downpayment' => $pkg['downpayment'],
			'price'       => $pkg['price'],
		);

		// Unique key taa-ke alag EMI packages alag cart lines banein.
		$cart_item_data['emicc_unique'] = md5( 'emicc_' . $index . '_' . microtime() );

		return $cart_item_data;
	}

	/**
	 * Cart calculation par selected package ki price line item par set karo.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function adjust_cart_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['emicc_emi']['price'] ) && isset( $cart_item['data'] ) ) {
				$cart_item['data']->set_price( (float) $cart_item['emicc_emi']['price'] );
			}
		}
	}

	/**
	 * Cart/checkout par EMI breakdown dikhao.
	 *
	 * @param array $item_data Existing item data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['emicc_emi'] ) ) {
			return $item_data;
		}

		$emi = $cart_item['emicc_emi'];

		$item_data[] = array(
			'key'   => __( 'EMI Plan', 'emi-checkout' ),
			'value' => sprintf(
				/* translators: 1: months, 2: monthly amount */
				__( '%1$d months — %2$s / month', 'emi-checkout' ),
				(int) $emi['months'],
				wp_strip_all_tags( wc_price( $emi['monthly'] ) )
			),
		);

		if ( $emi['downpayment'] > 0 ) {
			$item_data[] = array(
				'key'   => __( 'Downpayment', 'emi-checkout' ),
				'value' => wp_strip_all_tags( wc_price( $emi['downpayment'] ) ),
			);
		}

		return $item_data;
	}

	/**
	 * Order line item par EMI meta save karo.
	 *
	 * @param WC_Order_Item_Product $item          Order line item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order.
	 */
	public function save_order_line_item( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['emicc_emi'] ) ) {
			return;
		}

		$emi = $values['emicc_emi'];

		$item->add_meta_data(
			__( 'EMI Plan', 'emi-checkout' ),
			sprintf(
				/* translators: 1: months, 2: monthly amount */
				__( '%1$d months — %2$s / month', 'emi-checkout' ),
				(int) $emi['months'],
				wp_strip_all_tags( wc_price( $emi['monthly'] ) )
			),
			true
		);

		if ( $emi['downpayment'] > 0 ) {
			$item->add_meta_data(
				__( 'EMI Downpayment', 'emi-checkout' ),
				wp_strip_all_tags( wc_price( $emi['downpayment'] ) ),
				true
			);
		}

		// Raw values (reporting ke liye).
		$item->add_meta_data( '_emicc_months', (int) $emi['months'], true );
		$item->add_meta_data( '_emicc_monthly', wc_format_decimal( $emi['monthly'] ), true );
		$item->add_meta_data( '_emicc_emi_price', wc_format_decimal( $emi['price'] ), true );
	}
}
