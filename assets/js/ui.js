// Shared interactions: theme, dropdowns, preferences, toasts, count-up, ticket tilt, live table filter.
(function () {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.dataset.motion === 'reduce';

  // dark / light (button lives in the settings menu)
  const tb = document.getElementById('themeBtn');
  const paint = () => tb && tb.setAttribute('aria-pressed', document.documentElement.dataset.theme === 'dark');
  if (tb) {
    paint();
    tb.onclick = e => {
      e.stopPropagation();
      const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = next;
      try { localStorage.setItem('qc-theme', next); } catch (err) {}
      paint();
    };
  }

  // dropdowns are <details> elements (work without JS); this only closes them on outside click / Esc
  const dds = [...document.querySelectorAll('details[data-dd]')];
  dds.forEach(d => d.addEventListener('toggle', () => { if (d.open) dds.forEach(o => { if (o !== d) o.open = false; }); }));
  document.addEventListener('click', e => dds.forEach(d => { if (d.open && !d.contains(e.target)) d.open = false; }));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') dds.forEach(d => { if (d.open) { d.open = false; d.querySelector('summary').focus(); } }); });

  // settings page: appearance preferences (saved in this browser)
  document.querySelectorAll('[data-pref]').forEach(r => {
    const key = r.dataset.pref, def = { theme: 'light', size: 'normal', motion: 'full' }[key];
    let saved = def; try { saved = localStorage.getItem('qc-' + key) || def; } catch (e) {}
    r.checked = r.value === saved;
    r.onchange = () => {
      try { localStorage.setItem('qc-' + key, r.value); } catch (e) {}
      const d = document.documentElement;
      if (key === 'theme') d.dataset.theme = r.value === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : r.value;
      if (key === 'size') d.dataset.size = r.value;
      if (key === 'motion') d.dataset.motion = r.value;
      paint();
    };
  });

  // flash messages -> toasts
  const box = document.getElementById('toasts');
  document.querySelectorAll('.alert.flash').forEach(a => {
    const t = document.createElement('div');
    t.className = 'toast ' + (a.classList.contains('alert-error') ? 'error' : a.classList.contains('alert-info') ? 'info' : 'ok');
    t.setAttribute('role', 'status');
    t.innerHTML = '<span></span><button type="button" aria-label="Dismiss">&times;</button>';
    t.firstChild.textContent = a.textContent;
    const close = () => { t.classList.add('out'); setTimeout(() => t.remove(), 260); };
    t.querySelector('button').onclick = close;
    box.appendChild(t); a.remove();
    setTimeout(close, 6000);
  });

  // stat numbers count up
  if (!reduce) document.querySelectorAll('.stat b').forEach(el => {
    const m = el.textContent.trim().match(/^(\D*)(\d+)$/);
    if (!m || +m[2] === 0) return;
    const end = +m[2], t0 = performance.now();
    (function tick(t) {
      const p = Math.min(1, (t - t0) / 700);
      el.textContent = m[1] + Math.round(end * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(tick);
    })(t0);
  });

  // search box above long tables
  document.querySelectorAll('.table-wrap').forEach(w => {
    const rows = w.querySelectorAll('tbody tr');
    if (rows.length < 7) return;
    const i = document.createElement('input');
    i.className = 'filter'; i.type = 'search'; i.placeholder = 'Type to filter this table...';
    w.parentNode.insertBefore(i, w);
    i.oninput = () => { const q = i.value.toLowerCase(); rows.forEach(r => r.hidden = !r.textContent.toLowerCase().includes(q)); };
  });

  // hero: department chips drive the sample ticket, ticket tilts with the pointer
  const stub = document.getElementById('stub');
  if (stub) {
    document.querySelectorAll('.chip').forEach(c => c.onclick = () => {
      document.querySelectorAll('.chip').forEach(x => x.classList.toggle('on', x === c));
      document.getElementById('stubDept').textContent = c.dataset.name;
      document.getElementById('stubDocs').textContent = c.dataset.docs + (c.dataset.docs === '1' ? ' doctor' : ' doctors');
      document.getElementById('stubNo').textContent = String(1 + Math.floor(Math.random() * 99)).padStart(3, '0');
    });
    if (!reduce) {
      const wrap = stub.parentNode;
      wrap.addEventListener('pointermove', e => {
        const r = wrap.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
        stub.style.transform = 'rotateY(' + x * 14 + 'deg) rotateX(' + -y * 12 + 'deg)';
      });
      wrap.addEventListener('pointerleave', () => stub.style.transform = '');
    }
  }
})();
