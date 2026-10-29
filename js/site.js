/**
 * js/site.js
 *
 * The only script on the site. It asks for confirmation before a form with a
 * data-confirm attribute submits, which covers Deactivate, Reactivate, and
 * Delete. Nothing else runs, and there is no inline script anywhere, so the
 * Content-Security-Policy can stay at script-src 'self'.
 */
(function () {
  'use strict';

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  // Switching the media type on the add or edit form shows that type's
  // fields. Progressive enhancement only: without it, every field is shown
  // and the server validates the ones that matter.
  var typeSelect = document.querySelector('[data-media-type-select]');
  if (typeSelect) {
    var applyType = function () {
      var value = typeSelect.value;
      document.querySelectorAll('[data-for-type]').forEach(function (group) {
        var types = (group.getAttribute('data-for-type') || '').split(/\s+/);
        group.hidden = types.indexOf(value) === -1;
      });
    };
    typeSelect.addEventListener('change', applyType);
    applyType();
  }
})();
