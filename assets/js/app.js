// Availability calendar - every number comes from patient/api_availability.php (database).
(function () {
  const root = document.getElementById('calendar');
  if (!root) return;
  const doctorId = root.dataset.doctor, api = root.dataset.api, panel = document.getElementById('slotPanel');
  const names = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  let cur = new Date(); cur.setDate(1);
  let selected = null;
  const ym = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');

  async function load() {
    root.innerHTML = '<p class="hint">Loading availability...</p>';
    try {
      const res = await fetch(api + '?doctor_id=' + doctorId + '&month=' + ym(cur), { credentials: 'same-origin' });
      if (!res.ok) throw new Error(res.status);
      render(await res.json());
    } catch (e) {
      root.innerHTML = '<div class="alert alert-error">Could not load availability. Refresh the page to try again.</div>';
    }
  }

  function render(data) {
    const y = cur.getFullYear(), m = cur.getMonth();
    const startDow = new Date(y, m, 1).getDay(), days = new Date(y, m + 1, 0).getDate();
    let html = '<div class="cal-head"><button type="button" id="prev" aria-label="Previous month">&larr;</button><b>' + names[m] + ' ' + y +
      '</b><button type="button" id="next" aria-label="Next month">&rarr;</button></div><div class="cal">';
    ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(d => html += '<div class="dow">' + d + '</div>');
    for (let i = 0; i < startDow; i++) html += '<div class="empty-cell"></div>';
    for (let d = 1; d <= days; d++) {
      const key = y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
      const info = data.dates[key];
      let cls = 'none', sub = '', fill = '';
      if (info && !info.past) {
        const pct = Math.round(info.used / info.total * 100);
        fill = '<i class="fill" style="width:' + pct + '%"></i>';
        if (info.status === 'Fully Booked') { cls = 'full'; sub = 'Full'; }
        else if (info.status === 'Almost Full') { cls = 'almost'; sub = info.available + ' left'; }
        else { cls = 'available'; sub = info.available + ' left'; }
      }
      html += '<button type="button" class="day ' + cls + (selected === key ? ' selected' : '') + '" data-date="' + key + '"' +
        (cls === 'none' || cls === 'full' ? ' disabled' : '') + '><b>' + d + '</b><span>' + sub + '</span>' + fill + '</button>';
    }
    root.innerHTML = html + '</div>';
    root.querySelector('#prev').onclick = () => { cur.setMonth(cur.getMonth() - 1); load(); };
    root.querySelector('#next').onclick = () => { cur.setMonth(cur.getMonth() + 1); load(); };
    root.querySelectorAll('.day.available, .day.almost').forEach(b => b.onclick = () => pick(b.dataset.date, data.dates[b.dataset.date]));
    if (selected && data.dates[selected]) showPanel(selected, data.dates[selected]); else panel.hidden = true;
  }

  function pick(key, info) {
    selected = key;
    root.querySelectorAll('.day').forEach(x => x.classList.toggle('selected', x.dataset.date === key));
    showPanel(key, info);
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function countTo(el, to) {
    const from = +el.textContent || 0, t0 = performance.now();
    (function tick(t) { const p = Math.min(1, (t - t0) / 500); el.textContent = Math.round(from + (to - from) * p); if (p < 1) requestAnimationFrame(tick); })(t0);
  }

  function showPanel(key, i) {
    const dt = new Date(key + 'T00:00:00');
    document.getElementById('spDate').textContent = dt.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    countTo(document.getElementById('spTotal'), i.total);
    countTo(document.getElementById('spUsed'), i.used);
    countTo(document.getElementById('spAvail'), i.available);
    const ring = document.getElementById('spRing'), usedPct = i.used / i.total;
    ring.style.strokeDashoffset = String(264 * (1 - usedPct));
    ring.style.stroke = i.status === 'Available' ? 'var(--go)' : i.status === 'Almost Full' ? 'var(--warn)' : 'var(--bad)';
    const cls = i.status === 'Available' ? 'ok' : (i.status === 'Almost Full' ? 'warn' : 'bad');
    document.getElementById('spStatus').innerHTML = '<span class="badge badge-' + cls + '">' + i.status.toUpperCase() + '</span>';
    document.getElementById('spHours').textContent = 'Clinic hours ' + i.start + ' to ' + i.end;
    const btn = document.getElementById('spContinue');
    btn.href = 'appointment_form.php?schedule_id=' + i.schedule_id;
    btn.style.display = i.available > 0 ? '' : 'none';
    panel.hidden = false;
  }
  load();
})();
