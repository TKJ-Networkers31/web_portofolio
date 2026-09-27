/*
 * admin-media.js — PHASE 5.3
 *
 * Small, dependency-free progressive-enhancement script shared by:
 *   - admin/media.php        (tabs, drag & drop upload, copy path)
 *   - admin/profile.php      (asset-picker <dialog> for Profile Photo)
 *   - admin/project-media.php (asset-picker <dialog> for "Add Existing Media")
 *
 * Every feature here degrades gracefully: without JS the Upload tab is
 * still a plain <input type="file"> form, and pickers just don't open
 * (the "Add External URL" / manual path field is still usable, and a
 * server-rendered fallback list is included where a picker exists).
 */
(function () {
  'use strict';

  /* ---------- Tabs (Upload / Add External URL / Import-Export) ---------- */
  document.querySelectorAll('[data-admin-tabs]').forEach(function (group) {
    var tabs = group.querySelectorAll('.admin-tabs__tab');
    var panels = group.querySelectorAll('.admin-tabs__panel');

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var targetId = tab.getAttribute('data-tab-target');

        tabs.forEach(function (t) { t.classList.remove('is-active'); });
        tab.classList.add('is-active');

        panels.forEach(function (panel) {
          var isTarget = panel.id === targetId;
          panel.hidden = !isTarget;
          panel.classList.toggle('is-active', isTarget);
        });
      });
    });
  });

  /* ---------- Drag & drop upload ---------- */
  document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
    var input = zone.querySelector('[data-dropzone-input]');
    var filenameEl = zone.querySelector('[data-dropzone-filename]');

    if (!input) {
      return;
    }

    var showFilename = function () {
      if (filenameEl && input.files && input.files[0]) {
        filenameEl.textContent = 'Selected: ' + input.files[0].name;
      }
    };

    input.addEventListener('change', showFilename);

    ['dragenter', 'dragover'].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        zone.classList.add('is-dragover');
      });
    });

    ['dragleave', 'drop'].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        zone.classList.remove('is-dragover');
      });
    });

    zone.addEventListener('drop', function (e) {
      var files = e.dataTransfer && e.dataTransfer.files;
      if (files && files.length) {
        input.files = files;
        showFilename();
      }
    });
  });

  /* ---------- Copy asset path ---------- */
  document.querySelectorAll('[data-copy-path]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var value = btn.getAttribute('data-copy-path') || '';
      var original = btn.textContent;

      var done = function () {
        btn.textContent = 'Copied!';
        window.setTimeout(function () { btn.textContent = original; }, 1500);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done, done);
      } else {
        done();
      }
    });
  });

  /* ---------- Reusable asset-picker <dialog> ----------
   * Opened by any [data-open-picker="dialogId"] button. Inside the
   * dialog, each [data-media-pick] item carries data-path / data-preview
   * (data-preview falls back to data-path for images). Clicking one
   * writes into the target hidden input + preview <img> declared on the
   * trigger button (data-target-input / data-target-preview), then
   * closes the dialog — no page reload, no AJAX call needed since the
   * picker's items are already server-rendered into the dialog.
   */
  document.querySelectorAll('[data-open-picker]').forEach(function (trigger) {
    var dialog = document.getElementById(trigger.getAttribute('data-open-picker'));
    if (!dialog) {
      return;
    }

    trigger.addEventListener('click', function () {
      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else {
        dialog.setAttribute('open', 'open');
      }
    });

    var targetInput = trigger.getAttribute('data-target-input')
      ? document.getElementById(trigger.getAttribute('data-target-input'))
      : null;
    var targetPreview = trigger.getAttribute('data-target-preview')
      ? document.getElementById(trigger.getAttribute('data-target-preview'))
      : null;
    var targetType = trigger.getAttribute('data-target-type')
      ? document.getElementById(trigger.getAttribute('data-target-type'))
      : null;
    var targetAlt = trigger.getAttribute('data-target-alt')
      ? document.getElementById(trigger.getAttribute('data-target-alt'))
      : null;

    dialog.querySelectorAll('[data-media-pick]').forEach(function (item) {
      item.addEventListener('click', function () {
        var path = item.getAttribute('data-path') || '';

        if (targetInput) {
          targetInput.value = path;
          targetInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (targetPreview) {
          targetPreview.src = item.getAttribute('data-preview') || path;
          targetPreview.hidden = false;
        }
        if (targetType && item.getAttribute('data-type')) {
          targetType.value = item.getAttribute('data-type');
        }
        if (targetAlt && item.getAttribute('data-alt') && targetAlt.value === '') {
          targetAlt.value = item.getAttribute('data-alt');
        }

        if (typeof dialog.close === 'function') {
          dialog.close();
        } else {
          dialog.removeAttribute('open');
        }
      });
    });

    dialog.querySelectorAll('[data-close-picker]').forEach(function (closeBtn) {
      closeBtn.addEventListener('click', function () {
        if (typeof dialog.close === 'function') {
          dialog.close();
        } else {
          dialog.removeAttribute('open');
        }
      });
    });
  });

  /* ---------- Remove-photo confirmation is handled by the existing
   * onsubmit="return confirm(...)" pattern already used elsewhere in
   * admin — no extra JS needed for it. */
})();
