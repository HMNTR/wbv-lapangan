<?php
// Atur / geser jadwal satu sesi kunjungan: jam otomatis mengikuti durasi
require __DIR__ . '/inc.php';
require_editor();
csrf_check();
jw_pastikan_kolom();

$kid = (int)($_GET['k'] ?? 0);
$sesi = ($_GET['sesi'] ?? 'pagi') === 'siang' ? 'siang' : 'pagi';
$k = kunjungan_or_404($kid);
$self = "jadwal.php?k=$kid&sesi=$sesi";
$rows = rows("SELECT l.id, l.jam_mulai, l.menit_jadwal, l.jadwal_mulai, l.jadwal_selesai, r.waktu, r.menit, r.kegiatan, r.tipe
              FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE l.kunjungan_id=? AND r.sesi=? ORDER BY r.urut", [$kid, $sesi]);
if (!$rows) { flash('Tidak ada baris jadwal pada sesi ini.', 'err'); redirect("log.php?k=$kid&sesi=$sesi"); }
$byId = []; foreach ($rows as $r) $byId[(int)$r['id']] = $r;

$aksi = post('aksi');
$hasil = null; $anchorId = null; $anchorMin = null; $dur = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aksi === 'reset') {
    q("UPDATE log_baris l JOIN rundown r ON r.id=l.rundown_id SET l.jadwal_mulai=NULL, l.jadwal_selesai=NULL, l.menit_jadwal=NULL WHERE l.kunjungan_id=? AND r.sesi=?", [$kid, $sesi]);
    audit('jadwal dikembalikan ke rundown asli', "sesi $sesi", $kid);
    flash('Jadwal sesi ' . $sesi . ' dikembalikan ke rundown asli.');
    redirect($self);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($aksi, ['pratinjau', 'simpan'], true)) {
    $anchorId = (int)post('anchor');
    if (!isset($byId[$anchorId]) || jw_jenis($byId[$anchorId]) === 'titik') { flash('Pilih baris awal pergeseran.', 'err'); redirect($self); }
    foreach ((array)($_POST['dur'] ?? []) as $id => $v) if (isset($byId[(int)$id]) && $v !== '' && is_numeric($v) && (int)$v >= 0 && (int)$v <= 600) $dur[(int)$id] = (int)$v;
    $geser = post('geser');
    if ($geser !== null && is_numeric($geser)) $anchorMin = jw_mulai_sekarang($byId[$anchorId]) + (int)$geser;
    elseif (post('jam_baru')) $anchorMin = jw_min(str_replace(':', '.', post('jam_baru')));
    else { flash('Isi jam mulai baru atau jumlah menit pergeseran.', 'err'); redirect($self); }
    $hasil = jw_hitung($rows, $anchorId, $anchorMin, $dur);
    if ($aksi === 'simpan') {
        $u = db()->prepare("UPDATE log_baris SET jadwal_mulai=?, jadwal_selesai=?, menit_jadwal=? WHERE id=? AND kunjungan_id=?");
        foreach ($rows as $r) {
            $x = $hasil[$r['id']];
            $md = null;
            if (jw_jenis($r) !== 'titik') { $d = $dur[$r['id']] ?? jw_durasi($r); $md = ($r['menit'] !== null && (int)$d === (int)$r['menit']) ? null : $d; }
            $u->execute([jw_time($x['m']), jw_time($x['s']), $md, $r['id'], $kid]);
        }
        $lama = jw_mulai_sekarang($byId[$anchorId]); $sel = $anchorMin - $lama;
        audit('geser jadwal ' . $sesi, mb_substr($byId[$anchorId]['kegiatan'] ?: $byId[$anchorId]['waktu'], 0, 60) . ' → ' . jw_fmt($anchorMin) . ' (' . ($sel >= 0 ? '+' : '') . $sel . ' menit)', $kid);
        flash('Jadwal tersimpan. Mulai baris terpilih ' . jw_fmt($anchorMin) . ' (' . ($sel >= 0 ? '+' : '') . $sel . ' menit); baris sesudahnya mengikuti durasi.');
        redirect("log.php?k=$kid&sesi=$sesi");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['anchor'], $_GET['jam']) && isset($byId[(int)$_GET['anchor']]) && preg_match('/^\d{1,2}[:.]\d{2}$/', (string)$_GET['jam'])) {
    $anchorId = (int)$_GET['anchor']; $anchorMin = jw_min(str_replace(':', '.', (string)$_GET['jam']));
    $hasil = jw_hitung($rows, $anchorId, $anchorMin, []);
}
page_head('Atur / Geser Jadwal — ' . ($sesi === 'pagi' ? 'Pagi' : 'Siang'), $k);
$adaUbah = (bool)array_filter($rows, fn($r) => !empty($r['jadwal_mulai']));
?>
<div class="card">
  <p><b>Cara pakai:</b> pilih baris tempat jadwal mulai bergeser, isi <b>jam mulai baru</b> (atau <b>geser ± menit</b>). Bila perlu ubah <b>durasi</b> baris mana pun. Semua baris sesudahnya dihitung otomatis: jam mulai = jam selesai baris sebelumnya, jam selesai = jam mulai + durasi. Baris KSS 10-menitan di dalam skenario ikut bergeser.</p>
  <p class="muted">Rundown asli tidak berubah; jadwal baru hanya berlaku untuk kunjungan ini. Isian log (jam aktual, KSS, catatan) tidak tersentuh.</p>
</div>
<form method="post" class="card" data-track>
  <?= csrf_field() ?>
  <div class="grid">
    <div><label class="f">Mulai geser dari baris</label>
      <select name="anchor" id="anchor">
        <?php foreach ($rows as $r) { if (jw_jenis($r) === 'titik') continue;
          $sel = ((int)$r['id'] === (int)($anchorId ?? 0)) ? ' selected' : '';
          echo "<option value='{$r['id']}' data-jam='" . h(jw_fmt(jw_mulai_sekarang($r))) . "' data-aktual='" . h($r['jam_mulai'] ? jw_fmt(jw_dbmin($r['jam_mulai'])) : '') . "'$sel>" . h(jw_label($r) . ' · ' . mb_substr($r['kegiatan'] ?: '', 0, 55)) . "</option>"; } ?>
      </select>
      <div class="muted" id="anchorinfo"></div></div>
    <div><label class="f">Jam mulai baru</label><input type="time" name="jam_baru" id="jam_baru" value="<?= $anchorMin !== null ? h(str_replace('.', ':', jw_fmt($anchorMin))) : '' ?>">
      <button type="button" class="btn sm sec" data-now="jam_baru">sekarang</button> <button type="button" class="btn sm sec" id="pakaiaktual">pakai jam aktual ⏱</button></div>
    <div><label class="f">atau geser ± menit (mis. 15 atau -10)</label><input type="number" name="geser" value="<?= h(post('geser')) ?>" placeholder="kosongkan bila memakai jam mulai baru"></div>
  </div>

  <div class="tbl-wrap" style="margin-top:.8rem"><table class="log">
    <tr><th>Jadwal asli (rundown)</th><th>Jadwal berlaku</th><?= $hasil ? '<th>Jadwal baru</th>' : '' ?><th>Durasi (menit)</th><th>Kegiatan</th></tr>
    <?php foreach ($rows as $r): $j = jw_jenis($r); $x = $hasil[$r['id']] ?? null;
      $baru = $x ? ($j === 'titik' ? jw_fmt($x['m']) . (str_contains($r['waktu'], 'selesai') ? ' — selesai' : '') : jw_fmt($x['m']) . ($j === 'blok' ? ' — mulai (s.d. ' . jw_fmt($x['s']) . ')' : '–' . jw_fmt($x['s']))) : '';
      $beda = $x && $baru !== '' && strpos(jw_label($r), jw_fmt($x['m'])) !== 0; ?>
    <tr class="t-<?= h($r['tipe']) ?>">
      <td class="muted"><?= h($r['waktu']) ?></td>
      <td><?= h(jw_label($r)) ?></td>
      <?php if ($hasil): ?><td><?= $beda ? '<b>' . h($baru) . '</b>' : h($baru) ?><?= $x['luar'] ? " <span class='pill warn'>di luar durasi</span>" : '' ?></td><?php endif; ?>
      <td><?= $j === 'titik' ? '<span class="muted">titik KSS</span>' : "<input type='number' name='dur[{$r['id']}]' min='0' max='600' style='width:5.5rem' value='" . h($dur[$r['id']] ?? jw_durasi($r)) . "'>" . ($r['menit_jadwal'] !== null ? " <span class='pill'>diubah</span>" : '') ?></td>
      <td><?= h($r['kegiatan']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <div class="sticky"><button class="btn sec" name="aksi" value="pratinjau">Pratinjau</button> <button class="btn" name="aksi" value="simpan">Simpan jadwal baru</button> <a class="btn sec" href="log.php?k=<?= $kid ?>&sesi=<?= $sesi ?>">Kembali ke log</a></div>
</form>
<?php if ($adaUbah): ?>
<form method="post" onsubmit="return confirm('Kembalikan jadwal sesi ini ke rundown asli?')"><?= csrf_field() ?><input type="hidden" name="aksi" value="reset"><button class="btn sm bad">Kembalikan ke rundown asli</button></form>
<?php endif; ?>
<script>
(function(){const s=document.getElementById('anchor'),info=document.getElementById('anchorinfo'),jb=document.getElementById('jam_baru');
 const upd=()=>{const o=s.selectedOptions[0];info.textContent='Jadwal berlaku: '+o.dataset.jam+(o.dataset.aktual?' · jam aktual tercatat: '+o.dataset.aktual:'');};
 s.addEventListener('change',upd);upd();
 document.getElementById('pakaiaktual').addEventListener('click',()=>{const a=s.selectedOptions[0].dataset.aktual;if(a){jb.value=a.replace('.',':');}else alert('Baris ini belum punya jam mulai aktual di log.');});
})();
</script>
<?php page_foot();
