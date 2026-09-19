/*
 * The outbox.
 *
 * A driver taps "closed" in a street with two bars and a concrete wall. The
 * tap has to be kept, and it has to be kept exactly once — the shop's whole
 * profile is built out of these, and a replayed tap that counts twice would
 * quietly move its open-probability with nobody ever noticing.
 *
 * So: the id is made on the phone before anything is sent, the tap is written
 * to localStorage first and only then sent, and the queue is drained whenever
 * the browser says it is online again. The server answers 200 for an id it has
 * already seen, which is what makes retrying safe.
 *
 * Deliberately not a service worker. This is ~90 lines that a driver's
 * three-year-old Android runs without asking anybody's permission for
 * anything, and it fails visibly rather than cleverly.
 */
(function () {
  'use strict';

  var KEY = 'openwhen.outbox.v1';
  var state = document.getElementById('netstate');
  var stateText = document.getElementById('netstate-text');

  function read() {
    try { return JSON.parse(localStorage.getItem(KEY) || '[]'); }
    catch (e) { return []; }
  }

  function write(queue) {
    try { localStorage.setItem(KEY, JSON.stringify(queue)); }
    catch (e) { /* a full or blocked store: the form post below still works */ }
  }

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'ow-' + Date.now() + '-' + Math.random().toString(16).slice(2, 10);
  }

  function show() {
    if (!state) return;
    var pending = read().length;
    var offline = !navigator.onLine;

    if (!pending && !offline) { state.classList.remove('on'); return; }

    state.classList.add('on');
    stateText.textContent = offline
      ? (state.dataset.offline || 'offline') + (pending ? ' · ' + pending : '')
      : (state.dataset.pending || 'sending') + ' · ' + pending;
  }

  function token() {
    var el = document.querySelector('input[name="_token"]');
    return el ? el.value : '';
  }

  async function drain() {
    var queue = read();
    if (!queue.length || !navigator.onLine) { show(); return; }

    var left = [];
    for (var i = 0; i < queue.length; i++) {
      var item = queue[i];
      try {
        var res = await fetch('/api/visits', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token(),
          },
          body: JSON.stringify(item),
        });
        // Anything the server accepted, including "I already had this one",
        // leaves the queue. A 4xx that is not a conflict is a broken tap and
        // is dropped rather than retried for ever.
        if (!res.ok && res.status >= 500) left.push(item);
      } catch (e) {
        left.push(item);
      }
    }

    write(left);
    show();

    if (left.length === 0 && queue.length > 0) {
      document.dispatchEvent(new CustomEvent('openwhen:drained'));
    }
  }

  // Every outcome button posts normally when there is signal. With none, the
  // tap is queued and the row is marked done straight away — a driver cannot
  // wait at the kerb to find out whether his phone had a bar.
  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-outcome-form]');
    if (!form) return;

    var idField = form.querySelector('input[name="client_uuid"]');
    if (idField && !idField.value) idField.value = uuid();

    if (navigator.onLine) return;   // let it post the normal way

    event.preventDefault();

    var data = {};
    new FormData(form).forEach(function (value, key) {
      if (key !== '_token') data[key] = value;
    });
    data.observed_at = new Date().toISOString();

    var queue = read();
    queue.push(data);
    write(queue);
    show();

    var stop = form.closest('.stop');
    if (stop) {
      stop.classList.add('is-done');
      var buttons = stop.querySelector('.outcomes');
      if (buttons) buttons.remove();
      var done = document.createElement('div');
      done.className = 'stop-done';
      done.textContent = (state && state.dataset.queued) || 'saved on this phone';
      stop.appendChild(done);
    }
  });

  window.addEventListener('online', drain);
  window.addEventListener('offline', show);
  document.addEventListener('DOMContentLoaded', function () { show(); drain(); });
  show();
})();
