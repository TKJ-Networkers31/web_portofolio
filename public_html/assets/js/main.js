/*
 * main.js
 * - Menandai bahwa JavaScript berhasil dimuat (kelas "is-ready" pada <html>).
 *   Skrip kecil di <head> memakai penanda ini sebagai pengaman: jika skrip
 *   gagal dimuat, mode JavaScript dibatalkan agar konten tidak tersembunyi.
 * - Menonaktifkan navigasi tautan dummy (data-dummy) supaya halaman tidak
 *   melompat ke atas.
 */
(function () {
  'use strict';

  document.documentElement.classList.add('is-ready');

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || typeof target.closest !== 'function') {
      return;
    }

    var dummy = target.closest('[data-dummy]');
    if (dummy) {
      event.preventDefault();
    }
  });
})();
