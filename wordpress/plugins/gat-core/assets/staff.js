/* GAT staff portal: mobile menu, and a warning before leaving unsaved edits. */
(function () {
	'use strict';
	var btn = document.querySelector('.p-menu-btn');
	var nav = document.getElementById('p-nav');
	if (btn && nav) {
		btn.addEventListener('click', function () {
			var open = nav.classList.toggle('is-open');
			btn.setAttribute('aria-expanded', String(open));
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && nav.classList.contains('is-open')) {
				nav.classList.remove('is-open');
				btn.setAttribute('aria-expanded', 'false');
				btn.focus();
			}
		});
	}
	// Warn before navigating away from a changed form.
	var dirty = false;
	document.querySelectorAll('form[method="post"]').forEach(function (f) {
		f.addEventListener('input', function () { dirty = true; });
		f.addEventListener('change', function () { dirty = true; });
		f.addEventListener('submit', function () { dirty = false; });
	});
	window.addEventListener('beforeunload', function (e) {
		if (dirty) { e.preventDefault(); e.returnValue = ''; }
	});
})();
