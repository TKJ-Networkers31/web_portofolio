/*
 * navigation.js
 * - Menu mobile (disclosure): buka/tutup, Esc, klik di luar, ganti breakpoint.
 * - Penanda bagian aktif pada navigasi lokal (aria-current="location").
 * Tanpa JavaScript, semua tautan navigasi tetap terlihat dan dapat dipakai.
 */
(function () {
  'use strict';

  var header = document.querySelector('.site-header');
  var toggle = document.querySelector('[data-nav-toggle]');
  var panel = document.getElementById('site-menu');

  if (!header) {
    return;
  }

  /* ---------- Menu mobile ---------- */
  if (toggle && panel) {
    var mobileQuery = window.matchMedia('(max-width: 767px)');

    var isOpen = function () {
      return toggle.getAttribute('aria-expanded') === 'true';
    };

    var setOpen = function (open, returnFocus) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
      panel.classList.toggle('is-open', open);

      if (!open && returnFocus) {
        toggle.focus();
      }
    };

    toggle.addEventListener('click', function () {
      setOpen(!isOpen(), false);
    });

    document.addEventListener('keydown', function (event) {
      if ((event.key === 'Escape' || event.key === 'Esc') && isOpen()) {
        var active = document.activeElement;
        var focusInside = active === toggle || panel.contains(active);
        setOpen(false, focusInside);
      }
    });

    document.addEventListener('click', function (event) {
      if (isOpen() && !header.contains(event.target)) {
        setOpen(false, false);
      }
    });

    panel.addEventListener('click', function (event) {
      var target = event.target;
      if (mobileQuery.matches && target && typeof target.closest === 'function' && target.closest('a')) {
        setOpen(false, false);
      }
    });

    var onBreakpointChange = function () {
      if (!mobileQuery.matches) {
        setOpen(false, false);
      }
    };

    if (typeof mobileQuery.addEventListener === 'function') {
      mobileQuery.addEventListener('change', onBreakpointChange);
    } else if (typeof mobileQuery.addListener === 'function') {
      mobileQuery.addListener(onBreakpointChange);
    }
  }

  /* ---------- Bagian aktif ---------- */
  var links = header.querySelectorAll('.nav-local a[href^="#"]');
  if (!links.length || !('IntersectionObserver' in window)) {
    return;
  }

  var linkById = {};
  var sections = [];

  Array.prototype.forEach.call(links, function (link) {
    var id = link.getAttribute('href').slice(1);
    var section = id ? document.getElementById(id) : null;
    if (section) {
      linkById[id] = link;
      sections.push(section);
    }
  });

  if (!sections.length) {
    return;
  }

  var current = null;

  var setCurrent = function (id) {
    if (id === current) {
      return;
    }

    if (current && linkById[current]) {
      linkById[current].removeAttribute('aria-current');
    }

    current = id;

    if (current && linkById[current]) {
      linkById[current].setAttribute('aria-current', 'location');
    }
  };

  var observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        var id = entry.target.id;
        if (entry.isIntersecting) {
          setCurrent(id);
        } else if (current === id) {
          setCurrent(null);
        }
      });
    },
    { rootMargin: '-45% 0px -50% 0px', threshold: 0 }
  );

  sections.forEach(function (section) {
    observer.observe(section);
  });
})();
