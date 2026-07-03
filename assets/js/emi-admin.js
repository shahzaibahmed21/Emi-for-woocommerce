/* EMI & Lead Checkout - admin (product edit) script */
(function ($) {
	'use strict';

	$(function () {
		$(document).on('click', '.emicc-add-package', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.emicc-packages');
			var $tpl = $wrap.find('.emicc-package-row-tpl').first();
			if (!$tpl.length) {
				$tpl = $('#emicc-package-row-tpl');
			}
			if ($tpl.length) {
				$wrap.find('.emicc-packages-body').append($tpl.html());
			}
		});

		$(document).on('click', '.emicc-remove-package', function (e) {
			e.preventDefault();
			$(this).closest('tr.emicc-package-row').remove();
		});
	});
})(jQuery);
