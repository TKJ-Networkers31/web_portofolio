/*
 * reveal.js
 * Menambahkan kelas ".active" pada elemen ".reveal" saat masuk viewport
 * (IntersectionObserver). Menghormati prefers-reduced-motion. Jika
 * IntersectionObserver tidak tersedia, semua elemen langsung ditampilkan.
 */
(function () {
  'use strict';

  var items = document.querySelectorAll('.reveal');
  if (!items.length) {
    return;
  }

  var showAll = function () {
    Array.prototype.forEach.call(items, function (item) {
      item.classList.add('active');
    });
  };

  var prefersReduced =
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (prefersReduced || !('IntersectionObserver' in window)) {
    showAll();
    return;
  }

  var observer = new IntersectionObserver(
    function (entries, obs) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('active');
          obs.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12, rootMargin: '0px 0px -8% 0px' }
  );

  Array.prototype.forEach.call(items, function (item) {
    observer.observe(item);
  });
})();
