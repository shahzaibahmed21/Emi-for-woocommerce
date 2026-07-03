/* EMI & Lead Checkout - frontend script */
(function ($) {
	'use strict';

	function highlight($emi) {
		$emi.find('.emicc-plan-option').removeClass('emicc-selected');
		$emi.find('input[name="emicc_plan"]:checked')
			.closest('.emicc-plan-option')
			.addClass('emicc-selected');
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function formatLabel(template, value) {
		if (template.indexOf('%d') !== -1) {
			return template.replace('%d', value);
		}
		return template.replace('%s', value);
	}

	function buildPlanList(packages) {
		if (!packages || !packages.length || !window.emiccL10n) {
			return '';
		}

		var l10n = window.emiccL10n;
		var html = '';

		packages.forEach(function (pkg, i) {
			var checked = i === 0 ? ' checked="checked"' : '';
			var advance = '';

			if (pkg.downpayment > 0) {
				advance = '<small class="emicc-plan-down">' +
					formatLabel(l10n.advance, pkg.advance_html) +
					'</small>';
			}

			html += '<li class="emicc-plan-option">' +
				'<label>' +
				'<input type="radio" name="emicc_plan" value="' + escapeHtml(pkg.index) + '"' + checked + ' />' +
				'<span class="emicc-plan-name">' + escapeHtml(formatLabel(l10n.months, pkg.months)) + '</span>' +
				'<span class="emicc-plan-total-col">' +
				formatLabel(l10n.total, pkg.price_html) +
				advance +
				'</span>' +
				'<span class="emicc-plan-amount">' +
				formatLabel(l10n.monthly, pkg.monthly_html) +
				'</span>' +
				'</label>' +
				'</li>';
		});

		return html;
	}

	function syncSwatches($form) {
		$form.find('.emicc-variation-control select').each(function () {
			var $select = $(this);
			var val = $select.val();
			var $swatches = $select.closest('.emicc-variation-control').find('.emicc-swatch');

			$swatches.removeClass('selected').attr('aria-pressed', 'false');

			if (val) {
				$swatches.filter(function () {
					return String($(this).data('value')) === String(val);
				}).addClass('selected').attr('aria-pressed', 'true');
			}
		});
	}

	$(function () {
		var $form = $('form.variations_form');

		if ($form.length) {
			$form.on('click', '.emicc-swatch', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var value = $btn.data('value');
				var $select = $btn.closest('.emicc-variation-control').find('select');

				$select.val(value).trigger('change');
			});

			$form.on('woocommerce_update_variation_values show_variation reset_data check_variations', function () {
				syncSwatches($form);
			});

			syncSwatches($form);
		}

		var $emi = $('.emicc-product-emi');
		if (!$emi.length) {
			return;
		}

		if ($emi.hasClass('emicc-variable-emi') && $form.length) {
			$form.on('show_variation', function (event, variation) {
				if (variation.emicc_packages && variation.emicc_packages.length) {
					$emi.find('.emicc-plan-list').html(buildPlanList(variation.emicc_packages));
					$emi.removeAttr('hidden');
					highlight($emi);
				} else {
					$emi.find('.emicc-plan-list').empty();
					$emi.attr('hidden', true);
				}
			});

			$form.on('hide_variation', function () {
				$emi.find('.emicc-plan-list').empty();
				$emi.attr('hidden', true);
			});
		}

		$emi.on('change', 'input[name="emicc_plan"]', function () {
			highlight($emi);
		});

		highlight($emi);
	});
})(jQuery);
