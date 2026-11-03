// Omega&Care chat UI. All text is inserted with textContent (no HTML injection).
(function () {
  const box = document.getElementById('aiChat');
  if (!box) return;
  const log = document.getElementById('aiLog'), form = document.getElementById('aiForm'), input = document.getElementById('aiInput'),
        send = document.getElementById('aiSend'), welcome = document.getElementById('aiWelcome'), count = document.getElementById('aiCount'),
        chips = document.getElementById('aiChips');
  const api = box.dataset.api, csrf = box.dataset.csrf;
  let history = [], busy = false;

  const clock = () => new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  const down = () => log.scrollTo({ top: log.scrollHeight, behavior: 'smooth' });

  function el(tag, cls, text) { const n = document.createElement(tag); if (cls) n.className = cls; if (text) n.textContent = text; return n; }

  function bubble(role, text, extra) {
    extra = extra || {};
    const row = el('div', 'ai-row ' + role + (extra.mode === 'emergency' ? ' emergency' : extra.mode === 'error' ? ' error' : ''));
    if (role === 'bot') row.appendChild(el('span', 'ai-avatar', 'Ω'));
    const col = el('div', 'ai-col');
    if (extra.mode === 'emergency') col.appendChild(el('div', 'ai-alert', '⚠ This may be urgent'));
    col.appendChild(el('div', 'ai-bubble', text));
    if (extra.notice) col.appendChild(el('div', 'ai-note', extra.notice));
    const acts = (extra.actions || []).slice();
    if (extra.mode === 'emergency') acts.unshift({ label: 'Call 911', url: 'tel:911', danger: true });
    if (acts.length) {
      const a = el('div', 'ai-actions');
      acts.forEach(x => {
        const l = el('a', 'ai-act' + (x.danger ? ' danger' : ''));
        l.href = x.url;
        l.appendChild(el('span', 'ai-act-ico', x.danger ? '📞' : /^Book/.test(x.label) ? '📅' : '🩺'));
        l.appendChild(el('span', 'ai-act-t', x.label));
        l.appendChild(el('span', 'ai-act-go', '→'));
        a.appendChild(l);
      });
      col.appendChild(a);
    }
    col.appendChild(el('time', 'ai-time', clock()));
    row.appendChild(col);
    log.appendChild(row); down();
    return row;
  }

  function typing() {
    const row = el('div', 'ai-row bot');
    row.appendChild(el('span', 'ai-avatar', 'Ω'));
    const b = el('div', 'ai-bubble ai-typing'); b.innerHTML = '<i></i><i></i><i></i>';
    const col = el('div', 'ai-col'); col.appendChild(b); row.appendChild(col);
    log.appendChild(row); down(); return row;
  }

  async function ask(text) {
    text = text.trim();
    if (!text || busy) return;
    busy = true; send.disabled = true; welcome.hidden = true;
    bubble('user', text); input.value = ''; grow();
    const t = typing();
    try {
      const res = await fetch(api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: csrf, message: text, history: history.slice(-8) }) });
      const data = await res.json().catch(() => ({ error: 'Unexpected server response.' }));
      t.remove();
      if (!res.ok || data.error) bubble('bot', data.error || 'Something went wrong. Please try again.', { mode: 'error' });
      else { history.push({ role: 'user', text: text }, { role: 'assistant', text: data.reply }); bubble('bot', data.reply, data); }
    } catch (e) {
      t.remove(); bubble('bot', 'I could not reach the server. Check that XAMPP is running and try again.', { mode: 'error' });
    }
    busy = false; send.disabled = false; input.focus();
  }

  function grow() {
    input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 130) + 'px';
    count.textContent = input.value.length + '/600'; count.classList.toggle('warn', input.value.length > 520);
  }
  input.addEventListener('input', grow);
  input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
  form.addEventListener('submit', e => { e.preventDefault(); ask(input.value); });
  chips.querySelectorAll('button').forEach(b => b.onclick = () => ask(b.textContent));

  document.getElementById('aiNew').onclick = () => {
    if (busy) return;
    history = [];
    log.querySelectorAll('.ai-row').forEach(r => r.remove());
    welcome.hidden = false; log.scrollTop = 0; input.focus();
  };
})();
