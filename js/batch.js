/*global dotclear */
'use strict';

dotclear.ready(() => {
  const cfg = dotclear.getData('webp-batch');
  const start = document.getElementById('webp-start');
  const stop = document.getElementById('webp-stop');
  const force = document.getElementById('webp-force');
  const progress = document.getElementById('webp-progress');
  const status = document.getElementById('webp-status');
  const countsBox = document.getElementById('webp-counts');
  const errorsBox = document.getElementById('webp-errors-box');
  const errors = document.getElementById('webp-errors');
  if (!cfg || !start) return;

  const labels = cfg.labels || {};
  const fmt = (s, ...args) => args.reduce((acc, v, i) => acc.replace(`%${i + 1}$s`, v).replace('%s', v), s);

  let running = false;
  let totals = {};

  const showCounts = () => {
    countsBox.innerHTML = '';
    const ul = document.createElement('ul');
    for (const [k, v] of Object.entries(totals)) {
      const li = document.createElement('li');
      li.textContent = `${labels[k] || k}: ${v.toLocaleString()}`;
      ul.append(li);
    }
    countsBox.append(ul);
  };

  const step = async (after) => {
    const body = new URLSearchParams({
      xd_check: dotclear.nonce,
      webp_batch: '1',
      after,
      force: force.checked ? '1' : '0',
    });
    const response = await fetch(cfg.url, {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
  };

  const run = async () => {
    running = true;
    totals = {};
    errors.innerHTML = '';
    errorsBox.hidden = true;
    start.disabled = true;
    force.disabled = true;
    stop.disabled = false;
    progress.hidden = false;
    progress.value = 0;

    let after = '';
    try {
      for (;;) {
        const r = await step(after);
        if (!r.ok) throw new Error(r.message || 'unknown');
        for (const [k, v] of Object.entries(r.counts || {})) totals[k] = (totals[k] || 0) + v;
        for (const e of r.errors || []) {
          const li = document.createElement('li');
          li.textContent = `${e.file}: ${e.message}`;
          errors.append(li);
          errorsBox.hidden = false;
        }
        showCounts();
        progress.max = r.total || 1;
        progress.value = r.position;
        after = r.after;
        if (r.done) {
          status.textContent = fmt(cfg.finished, r.total.toLocaleString());
          break;
        }
        if (!running) {
          status.textContent = fmt(cfg.stopped, r.position.toLocaleString(), r.total.toLocaleString());
          break;
        }
        status.textContent = fmt(cfg.running, r.position.toLocaleString(), r.total.toLocaleString());
      }
    } catch (e) {
      status.textContent = fmt(cfg.failed, e.message);
    }
    running = false;
    start.disabled = false;
    force.disabled = false;
    stop.disabled = true;
  };

  start.addEventListener('click', run);
  stop.addEventListener('click', () => {
    running = false;
    stop.disabled = true;
  });
});
