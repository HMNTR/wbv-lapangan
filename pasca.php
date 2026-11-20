<?php
// F-7 Ringkasan pasca-sesi
require __DIR__ . '/inc.php';
require_login();
csrf_check();

$kid = (int)($_GET['k'] ?? 0);
$k = kunjungan_or_404($kid);
const DEVIASI = ['Sesuai protokol', 'Mengemudi > 60′ tanpa istirahat ≥ 10′', 'Mengemudi > 120′ dalam satu sesi', 'Lainnya'];

// hitung menit mengemudi aktual dari log (selisih jam mulai–selesai segmen mengemudi)
$akt = row("SELECT
   SUM(CASE WHEN r.sesi='pagi'  AND l.jam_mulai IS NOT NULL AND l.jam_selesai IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, l.jam_mulai, l.jam_selesai) END) pagi,
   SUM(CASE WHEN r.sesi='siang' AND l.jam_mulai IS NOT NULL AND l.jam_selesai IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, l.jam_mulai, l.jam_selesai) END) siang
 FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE l.kunjungan_id=? AND r.tipe='d'", [$kid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can_edit()) { http_response_code(403); exit('Tidak berhak mengubah F-7.'); }
    $dev = array_values(array_intersect(DEVIASI, (array)($_POST['deviasi'] ?? [])));
    $f = ['jam_selesai' => post('jam_selesai'), 'menit_pagi' => intOrNull(post('menit_pagi')), 'menit_siang' => intOrNull(post('menit_siang')),
          'kss_akhir' => intOrNull(post('kss_akhir')), 'semua_selesai' => post('semua_selesai'), 'tidak_selesai' => post('tidak_selesai'),
          'vdv_z' => post('vdv_z'), 'loss_persen' => post('loss_persen'), 'kulit' => post('kulit'), 'kulit_tindakan' => post('kulit_tindakan'),
          'keluhan' => post('keluhan'), 'ktd' => post('ktd'), 'ktd_uraian' => post('ktd_uraian'), 'berkas_lengkap' => post('berkas_lengkap'),
          'berkas_hilang' => post('berkas_hilang'), 'bersedia_data' => post('bersedia_data'),
          'deviasi' => $dev ? implode('; ', $dev) . (post('deviasi_lain') ? ' — ' . post('deviasi_lain') : '') : null];
    $f['updated_by'] = uid();
    $cols = array_keys($f);
    q("INSERT INTO pasca (kunjungan_id," . implode(',', $cols) . ") VALUES (:kid,:" . implode(',:', $cols) . ")
       ON DUPLICATE KEY UPDATE " . implode(',', array_map(fn($c) => "$c=VALUES($c)", $cols)), $f + ['kid' => $kid]);
    q("UPDATE kunjungan SET kompensasi_diterima=? WHERE id=?", [post('kompensasi') ? 1 : 0, $kid]);
    if ($f['semua_selesai'] === '1') q("UPDATE kunjungan SET status='selesai' WHERE id=? AND status<>'berhenti'", [$kid]);
    audit('simpan F-7', (string)$f['ktd'] === '1' ? 'KTD tercatat' : '', $kid);
    flash('F-7 tersimpan.' . ((string)$f['ktd'] === '1' ? ' KTD tercatat — laporkan ke KEPK UNPRI.' : ''));
    redirect("pasca.php?k=$kid");
}

$d = row("SELECT * FROM pasca WHERE kunjungan_id=?", [$kid]) ?? [];
$v = fn($c) => h($d[$c] ?? '');
$kssLast = row("SELECT l.kss FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE l.kunjungan_id=? AND l.kss IS NOT NULL ORDER BY r.sesi='siang' DESC, r.urut DESC LIMIT 1", [$kid]);
$k = kunjungan_or_404($kid);
page_head('F-7 Ringkasan Pasca-Sesi', $k);
?>
<form method="post" class="card" data-track>
  <?= csrf_field() ?>
  <fieldset <?= can_edit() ? '' : 'disabled' ?>>
  <div class="grid">
    <div><label class="f">Jam selesai</label><input type="time" name="jam_selesai" value="<?= h(substr((string)($d['jam_selesai'] ?? ''), 0, 5)) ?>"> <button type="button" class="btn sm sec now" data-now="jam_selesai">sekarang</button></div>
    <div><label class="f">Total mengemudi pagi (menit) — dari log: <?= (int)$akt['pagi'] ?></label><input type="number" name="menit_pagi" value="<?= $v('menit_pagi') ?: (int)$akt['pagi'] ?>"></div>
    <div><label class="f">Total mengemudi siang (menit) — dari log: <?= (int)$akt['siang'] ?></label><input type="number" name="menit_siang" value="<?= $v('menit_siang') ?: (int)$akt['siang'] ?>"></div>
    <div><label class="f">1. KSS akhir hari ini <?= $kssLast ? '(KSS terakhir di log: ' . (int)$kssLast['kss'] . ')' : '' ?></label><?= kss_select('kss_akhir', $d['kss_akhir'] ?? null) ?></div>
    <div><label class="f">2. Seluruh segmen/skenario selesai?</label><?= yn('semua_selesai', $d['semua_selesai'] ?? null) ?><input type="text" name="tidak_selesai" value="<?= $v('tidak_selesai') ?>" placeholder="yang tidak selesai"></div>
    <div><label class="f">3. VDV kumulatif harian seat pad, sumbu z (m/s^1,75)</label><input type="number" step="0.01" name="vdv_z" value="<?= $v('vdv_z') ?>"><span class="muted"><?= h(vdv_zona($d['vdv_z'] ?? null)) ?></span></div>
    <div><label class="f">4. Kehilangan cuplikan logger (%) — sasaran &lt; 0,5</label><input type="number" step="0.01" name="loss_persen" value="<?= $v('loss_persen') ?>"></div>
    <div><label class="f">5. Kondisi dada/kulit setelah tali dilepas</label><?= opt('kulit', $d['kulit'] ?? null, ['normal' => 'Normal', 'kemerahan' => 'Kemerahan']) ?><input type="text" name="kulit_tindakan" value="<?= $v('kulit_tindakan') ?>" placeholder="tindakan"></div>
    <div><label class="f">6. Keluhan subjek</label><input type="text" name="keluhan" value="<?= $v('keluhan') ?>" placeholder="kosongkan bila tidak ada"></div>
    <div><label class="f">7. Kejadian Tidak Diinginkan (KTD)</label><?= yn('ktd', $d['ktd'] ?? null) ?></div>
    <div><label class="f">8. Kelengkapan berkas IMU-1/2/3 & backup</label><?= yn('berkas_lengkap', $d['berkas_lengkap'] ?? null, 'Lengkap', 'Ada yang hilang') ?><input type="text" name="berkas_hilang" value="<?= $v('berkas_hilang') ?>" placeholder="yang hilang"></div>
    <div><label class="f">9. Subjek tetap bersedia datanya digunakan</label><?= yn('bersedia_data', $d['bersedia_data'] ?? null) ?></div>
  </div>
  <label class="f" style="margin-top:.8rem">Uraian KTD/keluhan dan tindak lanjut</label><textarea name="ktd_uraian"><?= $v('ktd_uraian') ?></textarea>
  <h2>Catatan pelaksanaan terhadap protokol (untuk laporan akhir ke KEPK)</h2>
  <?php foreach (DEVIASI as $x) { $c = str_contains((string)($d['deviasi'] ?? ''), $x) ? 'checked' : ''; echo "<label class='chk'><input type='checkbox' name='deviasi[]' value='" . h($x) . "' $c> " . h($x) . "</label>"; } ?>
  <input type="text" name="deviasi_lain" placeholder="uraian lainnya" style="margin-top:.4rem">
  <h2>Tanda terima kompensasi</h2>
  <label class="chk"><input type="checkbox" name="kompensasi" value="1" <?= $k['kompensasi_diterima'] ? 'checked' : '' ?>> Subjek telah menerima <?= h(setting('kompensasi')) ?> (paraf di formulir kertas)</label>
  <?php if (can_edit()): ?><div class="sticky"><button class="btn">Simpan F-7</button></div><?php endif; ?>
  </fieldset>
</form>
<?= panel_persetujuan($kid, 'f7') ?>
<?php if ((string)($d['bersedia_data'] ?? '') === '0'): ?><div class="alert bad">Subjek menarik persetujuan penggunaan data: hapus data rekaman kunjungan ini dari logger/laptop dan catat penghapusannya.</div><?php endif; ?>
<?php page_foot();
