<?php
// F-4 Lembar kelaikan pra-sesi (+ cek ulang setelah ISHOMA)
require __DIR__ . '/inc.php';
require_login();
csrf_check();
// semua peran dapat melihat; hanya Admin/Ketua/Peneliti yang dapat mengubah

$kid = (int)($_GET['k'] ?? 0);
$k = kunjungan_or_404($kid);
$B = batas();

// Evaluasi otomatis aturan tunda sesuai protokol
function evaluasi(array $d, array $B): array {
    $r = [];
    if (isset($d['bersedia']) && $d['bersedia'] !== null && (int)$d['bersedia'] === 0) $r[] = 'subjek tidak bersedia hari ini';
    if ($d['lama_tidur'] !== null && $d['lama_tidur'] !== '' && (float)$d['lama_tidur'] < $B['tidur_min_jam']) $r[] = "tidur < {$B['tidur_min_jam']} jam";
    if ((string)($d['alkohol'] ?? '') === '1') $r[] = 'alkohol dalam 24 jam';
    if ((string)($d['obat_kantuk'] ?? '') === '1') $r[] = 'obat penyebab kantuk dalam 24 jam';
    if ($d['kss_awal'] !== null && $d['kss_awal'] !== '' && (int)$d['kss_awal'] >= $B['kss_tunda']) $r[] = "KSS awal ≥ {$B['kss_tunda']}";
    if ($d['td_sistol'] && (int)$d['td_sistol'] >= $B['td_sistol_max']) $r[] = 'tekanan darah sistolik di atas batas';
    if ($d['td_diastol'] && (int)$d['td_diastol'] >= $B['td_diastol_max']) $r[] = 'tekanan darah diastolik di atas batas';
    if ($d['nadi'] && ((int)$d['nadi'] < $B['nadi_min'] || (int)$d['nadi'] > $B['nadi_max'])) $r[] = 'nadi di luar batas';
    if (($d['sisa_lelah'] ?? '') === 'berat') $r[] = 'sisa lelah berat (perlu penilaian Ketua Penelitian)';
    return $r;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can_edit()) { http_response_code(403); exit('Tidak berhak mengubah F-4.'); }
    $f = [];
    foreach (['jam_periksa','bersedia','jam_tidur','lama_tidur','jam_bangun','alkohol','obat_kantuk','kafein','keluhan','sisa_lelah',
              'td_sistol','td_diastol','nadi','kss_awal','keputusan','alasan_tunda','siang_jam','siang_kss','siang_keluhan','siang_keputusan','nakes','peneliti'] as $c) $f[$c] = post($c);
    $alasan = evaluasi($f, $B);
    if ($alasan && $f['keputusan'] === 'lanjut' && !(post('override') && is_role('ketua'))) {
        flash('Ada kriteria tunda: ' . implode('; ', $alasan) . '. Keputusan "Lanjut" hanya bisa disimpan oleh Ketua Penelitian (login sebagai Ketua dan centang persetujuan).', 'err');
        $_SESSION['kel_draft'] = $f;
        redirect("kelaikan.php?k=$kid");
    }
    if ($f['keputusan'] === 'tunda' && !$f['alasan_tunda']) $f['alasan_tunda'] = implode('; ', $alasan);
    $f['updated_by'] = uid();
    $cols = array_keys($f);
    q("INSERT INTO kelaikan (kunjungan_id," . implode(',', $cols) . ") VALUES (:kid,:" . implode(',:', $cols) . ")
       ON DUPLICATE KEY UPDATE " . implode(',', array_map(fn($c) => "$c=VALUES($c)", $cols)), $f + ['kid' => $kid]);
    if ($f['keputusan'] === 'lanjut') q("UPDATE kunjungan SET status='berjalan' WHERE id=? AND status='rencana'", [$kid]);
    unset($_SESSION['kel_draft']);
    audit('simpan F-4', 'keputusan: ' . ($f['keputusan'] ?? '-'), $kid);
    flash('F-4 tersimpan.');
    redirect("kelaikan.php?k=$kid");
}

$d = $_SESSION['kel_draft'] ?? row("SELECT * FROM kelaikan WHERE kunjungan_id=?", [$kid]) ?? [];
unset($_SESSION['kel_draft']);
$v = fn($c) => h($d[$c] ?? '');
$t = fn($c) => h(substr((string)($d[$c] ?? ''), 0, 5));
$alasan = $d ? evaluasi(array_merge(array_fill_keys(['lama_tidur','kss_awal','td_sistol','td_diastol','nadi'], null), $d), $B) : [];

page_head('F-4 Kelaikan Pra-Sesi', $k);
?>
<?php if ($alasan): ?><div class="alert bad">Kriteria tunda terpenuhi: <?= h(implode('; ', $alasan)) ?></div>
<?php elseif ($d): ?><div class="alert ok">Tidak ada kriteria tunda.</div><?php endif; ?>

<form method="post" class="card" data-track>
  <?= csrf_field() ?>
  <fieldset <?= can_edit() ? '' : 'disabled' ?>>
  <div class="grid">
    <div><label class="f">Jam pemeriksaan</label><input type="time" name="jam_periksa" value="<?= $t('jam_periksa') ?>"> <button type="button" class="btn sm sec now" data-now="jam_periksa">sekarang</button></div>
    <div><label class="f">1. Subjek menyatakan ulang (lisan) bersedia ikut hari ini</label><?= yn('bersedia', $d['bersedia'] ?? null) ?></div>
    <div><label class="f">2. Jam tidur semalam (mis. 22.00–05.00)</label><input type="text" name="jam_tidur" value="<?= $v('jam_tidur') ?>"></div>
    <div><label class="f">Lama tidur (jam) — tunda bila &lt; <?= $B['tidur_min_jam'] ?></label><input type="number" step="0.5" name="lama_tidur" value="<?= $v('lama_tidur') ?>"></div>
    <div><label class="f">3. Jam bangun</label><input type="time" name="jam_bangun" value="<?= $t('jam_bangun') ?>"></div>
    <div><label class="f">4. Alkohol dalam 24 jam — tunda bila Ya</label><?= yn('alkohol', $d['alkohol'] ?? null) ?></div>
    <div><label class="f">5. Obat penyebab kantuk dalam 24 jam — tunda bila Ya</label><?= yn('obat_kantuk', $d['obat_kantuk'] ?? null) ?></div>
    <div><label class="f">6. Kafein dalam 3 jam (dicatat)</label><?= yn('kafein', $d['kafein'] ?? null) ?></div>
    <div><label class="f">7. Keluhan kesehatan saat ini</label><input type="text" name="keluhan" value="<?= $v('keluhan') ?>" placeholder="kosongkan bila tidak ada"></div>
    <?php if ((int)$k['ke'] > 1): ?>
    <div><label class="f">7a. Sisa lelah/pegal dari pengujian sebelumnya</label><?= opt('sisa_lelah', $d['sisa_lelah'] ?? null, ['tidak' => 'Tidak', 'ringan' => 'Ringan', 'berat' => 'Berat']) ?></div>
    <?php endif; ?>
    <div><label class="f">8. Tekanan darah (mmHg) — batas <?= $B['td_sistol_max'] ?>/<?= $B['td_diastol_max'] ?></label>
      <div style="display:flex;gap:.4rem"><input type="number" name="td_sistol" placeholder="sistol" value="<?= $v('td_sistol') ?>"><input type="number" name="td_diastol" placeholder="diastol" value="<?= $v('td_diastol') ?>"></div></div>
    <div><label class="f">Nadi (×/menit) — batas <?= $B['nadi_min'] ?>–<?= $B['nadi_max'] ?></label><input type="number" name="nadi" value="<?= $v('nadi') ?>"></div>
    <div><label class="f">9. KSS awal — tunda bila ≥ <?= $B['kss_tunda'] ?></label><?= kss_select('kss_awal', $d['kss_awal'] ?? null) ?> <a href="kss.php" target="_blank" class="muted">kartu KSS</a></div>
  </div>
  <h2>Keputusan</h2>
  <?= opt('keputusan', $d['keputusan'] ?? null, ['lanjut' => 'Lanjut', 'tunda' => 'Ditunda']) ?>
  <div class="grid" style="margin-top:.6rem">
    <div><label class="f">Alasan penundaan (terisi otomatis bila kosong)</label><input type="text" name="alasan_tunda" value="<?= $v('alasan_tunda') ?>"></div>
    <div><label class="f">Pemeriksa tekanan darah/nadi</label><input type="text" name="nakes" value="<?= $v('nakes') ?>" placeholder="nama pemeriksa"></div>
    <div><label class="f">Nama peneliti</label><input type="text" name="peneliti" value="<?= $v('peneliti') ?: h(setting('peneliti')) ?>"></div>
  </div>
  <?php if (is_role('ketua')): ?><p><label class="chk" style="white-space:normal"><input type="checkbox" name="override" value="1"> Sebagai Ketua Penelitian, saya menilai dan menyetujui sesi tetap dilanjutkan meskipun ada kriteria tunda</label></p>
  <?php else: ?><p class="muted">Bila ada kriteria tunda, keputusan "Lanjut" hanya dapat disimpan oleh Ketua Penelitian.</p><?php endif; ?>

  <h2>Cek ulang sebelum sesi siang (setelah ISHOMA)</h2>
  <div class="grid">
    <div><label class="f">Jam</label><input type="time" name="siang_jam" value="<?= $t('siang_jam') ?>"> <button type="button" class="btn sm sec now" data-now="siang_jam">sekarang</button></div>
    <div><label class="f">KSS — tunda bila ≥ <?= $B['kss_tunda'] ?></label><?= kss_select('siang_kss', $d['siang_kss'] ?? null) ?></div>
    <div><label class="f">Keluhan</label><input type="text" name="siang_keluhan" value="<?= $v('siang_keluhan') ?>"></div>
    <div><label class="f">Keputusan</label><?= opt('siang_keputusan', $d['siang_keputusan'] ?? null, ['lanjut' => 'Lanjut', 'berhenti' => 'Berhenti']) ?></div>
  </div>
  <?php if (can_edit()): ?><div class="sticky"><button class="btn">Simpan F-4</button></div><?php endif; ?>
  </fieldset>
</form>
<?= panel_persetujuan($kid, 'f4') ?>
<?php page_foot();
