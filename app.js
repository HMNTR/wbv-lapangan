// Tombol "sekarang" mengisi jam saat ini; peringatan KSS ≥ 7; tanda perubahan belum disimpan.
document.addEventListener('click', e => {
  const b = e.target.closest('[data-now]');
  if (!b) return;
  e.preventDefault();
  const inp = document.querySelector(`[name="${b.dataset.now}"]`);
  if (inp) { const d = new Date(); inp.value = d.toTimeString().slice(0, 5); inp.dispatchEvent(new Event('change', { bubbles: true })); }
});
let dirty = false;
document.addEventListener('change', e => {
  if (e.target.form && e.target.form.dataset.track !== undefined) dirty = true;
  if (e.target.matches('select.kss')) {
    const v = parseInt(e.target.value || '0', 10);
    const tr = e.target.closest('tr');
    if (tr) tr.classList.toggle('kss-high', v >= 7);
    if (v >= 7) alert('KSS ≥ 7 — hentikan kendaraan dengan aman, subjek beristirahat ≥ 20 menit.\nLanjut hanya bila KSS ≤ 5. Catat di "Penghentian lebih awal".');
  }
});
document.addEventListener('submit', () => { dirty = false; });
window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

// ---- Informed consent: buka langkah 2 setelah semua bagian dicentang ----
(function () {
  const cks = document.querySelectorAll('.bacacek');
  const fs = document.getElementById('ickonsen');
  if (!cks.length || !fs) return;
  const upd = () => {
    const n = [...cks].filter(c => c.checked).length;
    document.getElementById('icnum').textContent = `${n}/${cks.length}`;
    document.getElementById('icbar').style.width = (100 * n / cks.length) + '%';
    fs.disabled = n !== cks.length;
    document.getElementById('ickunci').style.display = fs.disabled ? '' : 'none';
    cks.forEach(c => c.closest('.icsec').classList.toggle('done', c.checked));
  };
  cks.forEach(c => c.addEventListener('change', upd));
  upd();
})();

// ---- Bantalan tanda tangan (canvas) ----
document.querySelectorAll('canvas.sig').forEach(cv => {
  const ctx = cv.getContext('2d');
  const out = cv.parentElement.querySelector(`input[name="${cv.dataset.target}"]`);
  let drawing = false, drawn = false;
  const reset = () => { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cv.width, cv.height); ctx.strokeStyle = '#ddd'; ctx.beginPath(); ctx.moveTo(20, cv.height - 30); ctx.lineTo(cv.width - 20, cv.height - 30); ctx.stroke(); drawn = false; out.value = ''; };
  reset();
  const pos = e => { const r = cv.getBoundingClientRect(); return [(e.clientX - r.left) * cv.width / r.width, (e.clientY - r.top) * cv.height / r.height]; };
  cv.addEventListener('pointerdown', e => { if (cv.closest('fieldset')?.disabled) return; drawing = true; cv.setPointerCapture(e.pointerId); ctx.strokeStyle = '#0a1f5c'; ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.beginPath(); ctx.moveTo(...pos(e)); });
  cv.addEventListener('pointermove', e => { if (!drawing) return; ctx.lineTo(...pos(e)); ctx.stroke(); drawn = true; });
  const end = () => { if (!drawing) return; drawing = false; if (drawn) { out.value = cv.toDataURL('image/png'); dirty = true; } };
  cv.addEventListener('pointerup', end); cv.addEventListener('pointerleave', end);
  cv.parentElement.querySelector('.sigclear')?.addEventListener('click', reset);
});
