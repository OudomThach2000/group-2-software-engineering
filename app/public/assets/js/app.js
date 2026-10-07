// Front-end behaviour.  Owner: Sethouday Prum.
//
// Progressive enhancement only: every page works without this script, and the server
// checks every value again. A page opts in by adding one of these attributes:
//   data-submit-once   on a form: disables its submit button after one tap
//                      (the button may set data-busy-text, e.g. "Sending…")
//   data-confirm="..." on a form or button: asks before an action that cannot be undone
//   data-copy="..."    on a button: copies the text, e.g. a tracking reference
//   data-max-bytes="N" on a file input: refuses a file larger than N bytes before upload
(function () {
  'use strict';

  // data-confirm runs first, so a cancelled action never disables the button.
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var button = event.submitter;
    var question = (button && button.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
    if (question && !window.confirm(question)) {
      event.preventDefault();
      return;
    }
    if (form.hasAttribute('data-submit-once')) {
      if (form.dataset.submitted) {
        event.preventDefault();
        return;
      }
      form.dataset.submitted = '1';
      form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (b) {
        b.disabled = true;
        if (b.dataset.busyText) b.textContent = b.dataset.busyText;
      });
    }
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-copy]');
    if (!button) return;
    var text = button.getAttribute('data-copy');
    var label = button.textContent;
    var done = function (message) {
      button.textContent = message;
      setTimeout(function () { button.textContent = label; }, 2000);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(
        function () { done('Copied'); },
        function () { done('Copy failed'); }
      );
    } else {
      done('Copy failed');
    }
  });

  document.addEventListener('change', function (event) {
    var input = event.target;
    if (!input.matches('input[type="file"][data-max-bytes]')) return;
    var max = parseInt(input.getAttribute('data-max-bytes'), 10);
    var file = input.files && input.files[0];
    var note = input.parentNode.querySelector('.js-size-error');
    if (note) note.remove();
    if (file && file.size > max) {
      input.value = '';   // keep the form sendable without the photo
      note = document.createElement('span');
      note.className = 'error js-size-error';
      note.setAttribute('role', 'alert');
      note.textContent = 'This photo is too large (maximum ' + Math.round(max / 1048576) + ' MB). Please choose a smaller one.';
      input.insertAdjacentElement('afterend', note);
    }
  });
})();
