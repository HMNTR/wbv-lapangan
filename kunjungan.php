<?php
// Ringkasan kunjungan + data sesi (cuaca, uji ketuk, rekam diam, status)
require __DIR__ . '/inc.php';
require_login();
csrf_check();

$id = (int)($_GET['id'] ?? 0);
$k = kunjungan_or_404($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can_edit()) { http_response_code(403); exit('Tidak berhak.'); }
    if (post('hapus') === 'ya') {
        if (!is_role('admin')) { flash('Hanya admin yang dapat menghapus kunjungan.', 'err'); redirect("kunjungan.php?id=$id"); }
        audit('hapus kunjungan', $k['kode'] . ' kunjungan ' . $k['ke'], $id);
        q("DELETE FROM kunjungan WHERE id=?", [$id]);
        flash('Kunjungan dihapus.');
        redirect('index.php');
    }
    q("UPDATE kunjungan SET no_kendaraan=?, cuaca=?, lintasan=?, uji_ketuk=?, diam_awal_jam=?, diam_akhir_jam=?, status=?, kompensasi_diterima=? WHERE id=?",
      [post('no_kendaraan'), post('cuaca'), post('lintasan'), post('uji_ketuk') ? 1 : 0, post('diam_awal_jam'), post('diam_akhir_jam'),
       post('status', 'rencana'), post('kompensasi_diterima') ? 1 : 0, $id]);
    audit('simpan data sesi', '', $id);
    flash('Data sesi tersimpan.');
    redirect("kunjungan.php?id=$id");
}

$kel = row("SELECT * FROM kelaikan WHERE kunjungan_id=?", [$id]);
$ps = row("SELECT * FROM pasca WHERE kunjungan_id=?", [$id]);
$henti = rows("SELECT * FROM henti WHERE kunjungan_id=? ORDER BY jam", [$id]);
$stat = row("SELECT
   SUM(r.tipe='d') n_drive,
   SUM(r.tipe='d' AND (l.jam_mulai IS NOT NULL)) drive_isi,
   SUM(l.kss IS NOT NULL) n_kss, MAX(l.kss) kss_max,
   SUM(CASE WHEN r.tipe='d' AND r.sesi='pagi' THEN r.menit ELSE 0 END) rencana_pagi,
   SUM(CASE WHEN r.tipe='d' AND r.sesi='siang' THEN r.menit ELSE 0 END) rencana_siang
 FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE l.kunjungan_id=?", [$id]);
$kssSeries = rows("SELECT r.sesi, r.urut, r.waktu, l.jam_mulai, l.kss FROM log_baris l JOIN rundown r ON r.id=l.rundown_id
                   WHERE l.kunjungan_id=? AND l.kss IS NOT NULL ORDER BY r.sesi='siang', r.urut", [$id]);

page_head('Ringkasan Kunjungan', $k);
?>
<?php if ($kel && $kel['keputusan'] === 'tunda'): ?><div class="alert bad">F-4: sesi DITUNDA — <?= h($kel['alasan_tunda']) ?></div><?php endif; ?>
<?php if ($stat['kss_max'] !== null && (int)$stat['kss_max'] >= 7): ?><div class="alert bad">Tercatat KSS ≥ 7 (maks <?= (int)$stat['kss_max'] ?>). Pastikan penghentian dan istirahat tercatat.</div><?php endif; ?>

<div class="grid">
  <div class="card"><b>F-4 Kelaikan</b><br><?= $kel ? h($kel['keputusan'] ?? 'belum diputuskan') . " · KSS awal " . h($kel['kss_awal']) : "<span class='pill'>belum diisi</span>" ?><br><a href="kelaikan.php?k=<?= $id ?>">Isi/ubah →</a></div>
  <div class="card"><b>F-6 Log</b><br>Segmen mengemudi berjam: <?= (int)$stat['drive_isi'] ?>/<?= (int)$stat['n_drive'] ?> · KSS tercatat: <?= (int)$stat['n_kss'] ?><br>
    Rencana mengemudi: pagi <?= (int)$stat['rencana_pagi'] ?>′ · siang <?= (int)$stat['rencana_siang'] ?>′<br>
    <a href="log.php?k=<?= $id ?>&sesi=pagi">Pagi →</a> · <a href="log.php?k=<?= $id ?>&sesi=siang">Siang →</a></div>
  <div class="card"><b>Persetujuan elektronik</b><br><?php foreach (FORM_SLOT as $fm => $sl) { $ok = persetujuan($id, $fm); echo h(FORM_LABEL[$fm]) . ': ' . count($ok) . '/' . count($sl) . ' ' . (count($ok) === count($sl) ? '✓' : '') . '<br>'; } ?></div>
  <div class="card"><b>Cetak</b><br><a href="cetak.php?k=<?= $id ?>" target="_blank">Semua formulir kunjungan ini →</a><br><span class="muted">F-4, F-6 pagi/siang, F-7</span></div>
  <div class="card"><b>F-7 Pasca-sesi</b><br><?= $ps ? "KSS akhir " . h($ps['kss_akhir']) . " · VDV " . h($ps['vdv_z']) . ($ps['ktd'] ? " · <span class='pill bad'>KTD</span>" : '') : "<span class='pill'>belum diisi</span>" ?><br><a href="pasca.php?k=<?= $id ?>">Isi/ubah →</a></div>
</div>

<form method="post" class="card" data-track>
  <?= csrf_field() ?>
  <h2 style="margin-top:0">Data sesi</h2>
  <div class="grid">
    <div><label class="f">No. kendaraan</label><input type="text" name="no_kendaraan" value="<?= h($k['no_kendaraan']) ?>"></div>
    <div><label class="f">Cuaca / suhu luar</label><input type="text" name="cuaca" value="<?= h($k['cuaca']) ?>" placeholder="cerah, 27 °C"></div>
    <div><label class="f">Kondisi lintasan</label><?= opt('lintasan', $k['lintasan'], ['kering' => 'Kering', 'basah' => 'Basah']) ?></div>
    <div><label class="f">Uji ketuk pelat dada</label><label class="chk"><input type="checkbox" name="uji_ketuk" value="1" <?= $k['uji_ketuk'] ? 'checked' : '' ?>> OK</label></div>
    <div><label class="f">Rekam diam 30″ awal (jam)</label><input type="time" name="diam_awal_jam" value="<?= h(substr((string)$k['diam_awal_jam'], 0, 5)) ?>"> <button type="button" class="btn sm sec now" data-now="diam_awal_jam">sekarang</button></div>
    <div><label class="f">Rekam diam 30″ akhir (jam)</label><input type="time" name="diam_akhir_jam" value="<?= h(substr((string)$k['diam_akhir_jam'], 0, 5)) ?>"> <button type="button" class="btn sm sec now" data-now="diam_akhir_jam">sekarang</button></div>
    <div><label class="f">Status kunjungan</label><select name="status"><?php foreach (['rencana', 'berjalan', 'selesai', 'berhenti'] as $st) echo "<option" . ($k['status'] === $st ? ' selected' : '') . ">$st</option>"; ?></select></div>
    <div><label class="f">Kompensasi <?= h(setting('kompensasi')) ?></label><label class="chk"><input type="checkbox" name="kompensasi_diterima" value="1" <?= $k['kompensasi_diterima'] ? 'checked' : '' ?>> sudah diterima subjek</label></div>
  </div>
  <p><button class="btn">Simpan</button></p>
</form>

<?php if ($kssSeries): ?>
<div class="card"><h2 style="margin-top:0">Perjalanan KSS</h2>
<?php
  $w = 900; $hgt = 180; $n = count($kssSeries); $dx = $n > 1 ? ($w - 60) / ($n - 1) : 0;
  $pts = []; foreach ($kssSeries as $i => $p) { $x = 40 + $i * $dx; $y = 15 + ($hgt - 40) * (1 - ((int)$p['kss'] - 1) / 8); $pts[] = [$x, $y, $p]; }
  echo "<div class='tbl-wrap'><svg viewBox='0 0 $w $hgt' width='100%' role='img' aria-label='Grafik KSS'>";
  $y7 = 15 + ($hgt - 40) * (1 - 6 / 8);
  echo "<rect x='40' y='15' width='" . ($w - 60) . "' height='" . ($y7 - 15) . "' fill='#fbe0dd'/>";
  for ($g = 1; $g <= 9; $g += 2) { $yy = 15 + ($hgt - 40) * (1 - ($g - 1) / 8); echo "<line x1='40' x2='" . ($w - 20) . "' y1='$yy' y2='$yy' stroke='#ddd'/><text x='30' y='" . ($yy + 4) . "' font-size='11' text-anchor='end'>$g</text>"; }
  echo "<polyline fill='none' stroke='#1f5fbf' stroke-width='2' points='" . implode(' ', array_map(fn($p) => round($p[0]) . ',' . round($p[1]), $pts)) . "'/>";
  foreach ($pts as $p) echo "<circle cx='" . round($p[0]) . "' cy='" . round($p[1]) . "' r='3.5' fill='" . ((int)$p[2]['kss'] >= 7 ? '#b3261e' : '#1f5fbf') . "'><title>" . h(($p[2]['jam_mulai'] ? substr($p[2]['jam_mulai'], 0, 5) : $p[2]['waktu']) . ' — KSS ' . $p[2]['kss']) . "</title></circle>";
  echo "</svg></div><p class='muted'>Area merah = KSS ≥ 7 (batas henti). Arahkan kursor ke titik untuk melihat jam.</p>";
?>
</div>
<?php endif; ?>

<?php if ($henti): ?>
<div class="card"><h2 style="margin-top:0">Penghentian lebih awal</h2><table><tr><th>Jam</th><th>Alasan</th><th>Tindakan</th><th>Catatan</th></tr>
<?php foreach ($henti as $x) echo "<tr><td>" . h(substr($x['jam'], 0, 5)) . "</td><td>" . h($x['alasan']) . "</td><td>" . h($x['tindakan'] === 'sesi_diakhiri' ? 'Sesi diakhiri' : 'Istirahat lalu lanjut') . "</td><td>" . h($x['catatan']) . "</td></tr>"; ?>
</table></div>
<?php endif; ?>

<?php if (is_role('admin')): ?><form method="post" onsubmit="return confirm('Hapus kunjungan ini beserta seluruh isiannya?')">
  <?= csrf_field() ?><input type="hidden" name="hapus" value="ya"><button class="btn bad sm">Hapus kunjungan</button>
</form><?php endif; ?>
<?php page_foot();
