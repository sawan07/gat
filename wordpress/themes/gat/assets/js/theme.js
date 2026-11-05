/* Global Alliance Trust theme: menu, search, news ticker, advertiser carousel. */
(function () {
	'use strict';

	// Mobile menu.
	var nav = document.getElementById('main-nav');
	var menuBtn = document.querySelector('.menu-toggle');
	if (nav && menuBtn) {
		menuBtn.addEventListener('click', function () {
			var open = nav.classList.toggle('is-open');
			menuBtn.setAttribute('aria-expanded', String(open));
		});
	}

	// Submenus (tap / keyboard).
	document.querySelectorAll('.submenu-toggle').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var li = btn.closest('li');
			var open = li.classList.toggle('is-open');
			btn.setAttribute('aria-expanded', String(open));
		});
	});

	// Search.
	var searchBtn = document.querySelector('.search-toggle');
	var search = document.getElementById('site-search');
	if (searchBtn && search) {
		searchBtn.addEventListener('click', function () {
			var open = search.hasAttribute('hidden');
			search.toggleAttribute('hidden', !open);
			searchBtn.setAttribute('aria-expanded', String(open));
			if (open) {
				var f = search.querySelector('input[type=search]');
				if (f) { f.focus(); }
			}
		});
	}

	// News ticker: duplicate the items so the scroll loops seamlessly.
	var ticker = document.querySelector('[data-ticker]');
	if (ticker) {
		var section = ticker.closest('.ticker');
		var track = ticker.querySelector('.ticker__track');
		Array.prototype.slice.call(track.children).forEach(function (li) {
			var c = li.cloneNode(true);
			c.setAttribute('aria-hidden', 'true');
			c.querySelectorAll('a').forEach(function (a) { a.tabIndex = -1; });
			track.appendChild(c);
		});
		var dur = Math.max(20, track.children.length * 5);
		track.style.animationDuration = dur + 's';
		var pause = section.querySelector('[data-ticker-pause]');
		var prev = section.querySelector('[data-ticker-prev]');
		if (pause) {
			pause.addEventListener('click', function () {
				var p = section.classList.toggle('is-paused');
				pause.setAttribute('aria-pressed', String(p));
				pause.setAttribute('aria-label', p ? 'Play' : 'Pause');
				pause.innerHTML = p
					? '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>'
					: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6v12M15 6v12"/></svg>';
			});
		}
		if (prev) {
			// Restart the loop from the beginning.
			prev.addEventListener('click', function () {
				track.style.animation = 'none';
				void track.offsetWidth;
				track.style.animation = '';
				track.style.animationDuration = dur + 's';
			});
		}
	}

	// Advertiser carousel: pages of 3 (2 on small screens), auto-advance.
	document.querySelectorAll('[data-carousel]').forEach(function (car) {
		var card = car.closest('.partner-card');
		var track = car.querySelector('.partner-carousel__track');
		var tiles = track.children;
		var dotsEl = card.querySelector('[data-carousel-dots]');
		var page = 0;
		var timer;

		function perPage() { var w = car.clientWidth; return w > 640 ? 5 : w > 250 ? 3 : 2; }
		function pages() { return Math.max(1, Math.ceil(tiles.length / perPage())); }
		function render() {
			var pp = perPage();
			Array.prototype.forEach.call(tiles, function (t) { t.style.flexBasis = 'calc((100% - ' + (10 * (pp - 1)) + 'px) / ' + pp + ')'; });
			if (page >= pages()) { page = 0; }
			var first = tiles[page * pp];
			track.style.transform = first ? 'translateX(-' + (first.offsetLeft - tiles[0].offsetLeft) + 'px)' : '';
			if (dotsEl) {
				dotsEl.innerHTML = '';
				for (var i = 0; i < pages(); i++) {
					var d = document.createElement('i');
					if (i === page) { d.className = 'on'; }
					dotsEl.appendChild(d);
				}
			}
			card.querySelectorAll('[data-carousel-prev],[data-carousel-next],[data-carousel-dots]').forEach(function (el) { el.style.visibility = pages() < 2 ? 'hidden' : ''; });
		}
		function go(delta) { page = (page + delta + pages()) % pages(); render(); }
		function auto() {
			clearInterval(timer);
			if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
				timer = setInterval(function () { go(1); }, 5000);
			}
		}
		var nextBtn = card.querySelector('[data-carousel-next]');
		var prevBtn = card.querySelector('[data-carousel-prev]');
		if (nextBtn) { nextBtn.addEventListener('click', function () { go(1); auto(); }); }
		if (prevBtn) { prevBtn.addEventListener('click', function () { go(-1); auto(); }); }
		card.addEventListener('mouseenter', function () { clearInterval(timer); });
		card.addEventListener('mouseleave', auto);
		window.addEventListener('resize', render);
		render();
		auto();
	});
})();
