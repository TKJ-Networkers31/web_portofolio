/*
 * lightbox.js — PHASE 5.3
 *
 * Progressive enhancement for the project detail Gallery
 * (public_html/project.php): intercepts clicks on any [data-lightbox]
 * link and shows the full-size image in an overlay instead of
 * navigating away. Without JavaScript, or if this script fails to
 * load, every gallery image is still a plain <a href="..."> straight
 * to the full-size file — nothing is lost.
 *
 * No build step, no library — matches the existing plain-JS pattern
 * used by navigation.js / reveal.js.
 */
(function () {
  'use strict';

  var links = document.querySelectorAll('[data-lightbox]');
  if (!links.length) {
    return;
  }

  var items = Array.prototype.map.call(links, function (link) {
    return {
      href: link.getAttribute('href'),
      caption: link.getAttribute('data-caption') || ''
    };
  });

  var overlay = document.createElement('div');
  overlay.className = 'lightbox-overlay';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-label', 'Image preview');
  overlay.hidden = true;
  overlay.innerHTML =
    '<button type="button" class="lightbox-overlay__close" aria-label="Close">&times;</button>' +
    '<button type="button" class="lightbox-overlay__nav lightbox-overlay__nav--prev" aria-label="Previous image">&larr;</button>' +
    '<figure class="lightbox-overlay__figure">' +
      '<img class="lightbox-overlay__img" alt="">' +
      '<figcaption class="lightbox-overlay__caption"></figcaption>' +
    '</figure>' +
    '<button type="button" class="lightbox-overlay__nav lightbox-overlay__nav--next" aria-label="Next image">&rarr;</button>';

  document.body.appendChild(overlay);

  var imgEl = overlay.querySelector('.lightbox-overlay__img');
  var captionEl = overlay.querySelector('.lightbox-overlay__caption');
  var closeBtn = overlay.querySelector('.lightbox-overlay__close');
  var prevBtn = overlay.querySelector('.lightbox-overlay__nav--prev');
  var nextBtn = overlay.querySelector('.lightbox-overlay__nav--next');

  var currentIndex = 0;

  var show = function (index) {
    currentIndex = (index + items.length) % items.length;
    var item = items[currentIndex];

    imgEl.src = item.href;
    imgEl.alt = item.caption;
    captionEl.textContent = item.caption;
    captionEl.hidden = item.caption === '';

    var multiple = items.length > 1;
    prevBtn.hidden = !multiple;
    nextBtn.hidden = !multiple;
  };

  var open = function (index) {
    show(index);
    overlay.hidden = false;
    document.documentElement.classList.add('lightbox-open');
    closeBtn.focus();
  };

  var close = function () {
    overlay.hidden = true;
    document.documentElement.classList.remove('lightbox-open');
  };

  links.forEach(function (link, index) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      open(index);
    });
  });

  closeBtn.addEventListener('click', close);
  prevBtn.addEventListener('click', function () { show(currentIndex - 1); });
  nextBtn.addEventListener('click', function () { show(currentIndex + 1); });

  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) {
      close();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (overlay.hidden) {
      return;
    }
    if (e.key === 'Escape' || e.key === 'Esc') {
      close();
    } else if (e.key === 'ArrowLeft') {
      show(currentIndex - 1);
    } else if (e.key === 'ArrowRight') {
      show(currentIndex + 1);
    }
  });
})();
