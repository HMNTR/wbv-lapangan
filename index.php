<?php
// Dashboard
require __DIR__ . '/inc.php';
require_login();

$today = date('Y-m-d');
$S = row("SELECT
  (SELECT COUNT(*) FROM subjek) subjek,
  (SELECT COUNT(*) FROM kunjungan) kunj,
  (SELECT COUNT(*) FROM kunjungan WHERE status='selesai') kunj_selesai,
  (SELECT COUNT(*) FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE r.tipe='d') seg,
  (SELECT COUNT(*) FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE r.tipe='d' AND l.jam_mulai IS NOT NULL) seg_isi,
  (SELECT COUNT(*) FROM log_baris WHERE kss IS NOT NULL) n_kss,
  (SELECT ROUND(AVG(kss),1) FROM log_baris WHERE kss IS NOT NULL) kss_avg,
  (SELECT MAX(kss) FROM log_baris) kss_max,
  (SELECT COUNT(*) FROM henti) henti,
  (SELECT COUNT(*) FROM pasca WHERE ktd=1) ktd,
  (SELECT MAX(vdv_z) FROM pasca) vdv_max,
  (SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, l.jam_mulai, l.jam_selesai)),0) FROM log_baris l JOIN rundown r ON r.id=l.rundown_id
     WHERE r.tipe='d' AND l.jam_mulai IS NOT NULL AND l.jam_selesai IS NOT NULL) menit");
$kunj = rows("SELECT k.*, s.kode, kl.keputusan, kl.kss_awal, p.kss_akhir, p.vdv_z, p.ktd, p.id ps,
    (SELECT COUNT(*) FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE l.kunjungan_id=k.id AND r.tipe='d') seg,
    (SELECT COUNT(*) FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE l.kunjungan_id=k.id AND r.tipe='d' AND l.jam_mulai IS NOT NULL) seg_isi,
    (SELECT MAX(kss) FROM log_baris WHERE kunjungan_id=k.id) kss_max,
    (SELECT COUNT(*) FROM henti WHERE kunjungan_id=k.id) n_henti
  FROM kunjungan k JOIN subjek s ON s.id=k.subjek_id
  LEFT JOIN kelaikan kl ON kl.kunjungan_id=k.id LEFT JOIN pasca p ON p.kunjungan_id=k.id
  ORDER BY k.tanggal, s.kode");

// peringatan
$warn = [];
foreach ($kunj as $k) {
    $lbl = "{$k['kode']} kunjungan {$k['ke']} (" . fmt_tgl($k['tanggal']) . ")";
    if ((int)$k['kss_max'] >= (int)setting('kss_tunda') && !(int)$k['n_henti']) $warn[] = ['bad', "$lbl: KSS ≥ " . setting('kss_tunda') . " tercatat tetapi belum ada catatan penghentian.", "log.php?k={$k['id']}&sesi=pagi"];
    if ($k['ktd']) $warn[] = ['bad', "$lbl: Kejadian Tidak Diinginkan — laporkan ke KEPK UNPRI.", "pasca.php?k={$k['id']}"];
    if ($k['keputusan'] === 'tunda') $warn[] = ['warn', "$lbl: sesi ditunda menurut F-4.", "kelaikan.php?k={$k['id']}"];
    if ($k['tanggal'] <= $today && !$k['keputusan']) $warn[] = ['warn', "$lbl: F-4 kelaikan belum diisi.", "kelaikan.php?k={$k['id']}"];
    if ($k['tanggal'] < $today && !$k['ps']) $warn[] = ['warn', "$lbl: F-7 pasca-sesi belum diisi.", "pasca.php?k={$k['id']}"];
    if ($k['ps'] && !$k['kompensasi_diterima']) $warn[] = ['warn', "$lbl: kompensasi belum ditandai diterima.", "pasca.php?k={$k['id']}"];
}
foreach (rows("SELECT s.id, s.kode, s.ic_tanggal, ic.setuju, ic.ditarik_at, ic.ditarik_hapus_data, ic.id icid FROM subjek s LEFT JOIN informed_consent ic ON ic.subjek_id=s.id") as $x) {
    $lk = can_edit() ? "ic.php?s={$x['id']}" : 'kunjungan_list.php';
    if ($x['ditarik_at']) $warn[] = ['bad', "Subjek {$x['kode']}: persetujuan DITARIK" . ($x['ditarik_hapus_data'] ? ' dan meminta data dihapus' : '') . '.', $lk];
    elseif ($x['icid'] && !$x['setuju']) $warn[] = ['bad', "Subjek {$x['kode']}: menyatakan TIDAK SETUJU — jangan diikutkan.", $lk];
    elseif (!$x['icid'] && !$x['ic_tanggal']) $warn[] = ['bad', "Subjek {$x['kode']}: informed consent belum diisi.", $lk];
}
$tunggu = rows("SELECT k.id, s.kode, k.ke, COUNT(p.id) n FROM kunjungan k JOIN subjek s ON s.id=k.subjek_id LEFT JOIN persetujuan_form p ON p.kunjungan_id=k.id GROUP BY k.id");
$nSlot = array_sum(array_map('count', FORM_SLOT));
$app = []; foreach ($tunggu as $x) $app[$x['id']] = (int)$x['n'];
if (!can_edit() && user()) foreach ($kunj as $k) foreach (FORM_SLOT as $fm => $sl) if (in_array(user()['peran'], $sl, true) && form_terisi((int)$k['id'], $fm) && !isset(persetujuan((int)$k['id'], $fm)[user()['peran']]))
    $warn[] = ['warn', "{$k['kode']} kunjungan {$k['ke']}: " . FORM_LABEL[$fm] . " menunggu persetujuan Anda.", ($fm === 'f4' ? "kelaikan.php?k={$k['id']}" : ($fm === 'f7' ? "pasca.php?k={$k['id']}" : "log.php?k={$k['id']}&sesi=" . substr($fm, 3)))];

$kssSeries = rows("SELECT l.kunjungan_id, k.ke, s.kode, k.tanggal, r.sesi, r.urut, r.waktu, l.jam_mulai, l.kss
   FROM log_baris l JOIN rundown r ON r.id=l.rundown_id JOIN kunjungan k ON k.id=l.kunjungan_id JOIN subjek s ON s.id=k.subjek_id
   WHERE l.kss IS NOT NULL ORDER BY k.tanggal, s.kode, r.sesi='siang', r.urut");
$akt = rows("SELECT a.*, u.nama FROM aktivitas a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 10");

page_head('Dashboard');
$pct = $S['seg'] ? round(100 * $S['seg_isi'] / $S['seg']) : 0;
$card = fn($n, $l, $cls = '') => "<div class='stat $cls'><div class='n'>" . h($n) . "</div><div class='l'>" . h($l) . "</div></div>";
?>
<div class="stats">
  <?= $card($S['subjek'], 'Subjek') ?>
  <?= $card("{$S['kunj_selesai']}/{$S['kunj']}", 'Kunjungan selesai') ?>
  <?= $card("$pct%", "Segmen mengemudi tercatat ({$S['seg_isi']}/{$S['seg']})") ?>
  <?= $card($S['menit'] . '′', 'Total mengemudi tercatat') ?>
  <?= $card($S['n_kss'] ? "{$S['kss_avg']} / {$S['kss_max']}" : '–', "KSS rata-rata / maks ({$S['n_kss']} titik)", (int)$S['kss_max'] >= (int)setting('kss_tunda') ? 'bad' : '') ?>
  <?= $card($S['henti'], 'Penghentian lebih awal', $S['henti'] ? 'warn' : '') ?>
  <?= $card($S['ktd'], 'Kejadian tidak diinginkan', $S['ktd'] ? 'bad' : 'ok') ?>
  <?= $card($S['vdv_max'] !== null ? $S['vdv_max'] : '–', 'VDV z harian maks (m/s^1,75)', $S['vdv_max'] !== null && $S['vdv_max'] >= 8.5 ? 'warn' : '') ?>
</div>

<?php if ($warn): ?>
<div class="card"><h2 style="margin-top:0">Perlu perhatian</h2>
<?php foreach ($warn as [$c, $t, $u]) echo "<div class='alert $c'><a href='" . h($u) . "'>" . h($t) . "</a></div>"; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2 style="margin-top:0">Progres kunjungan</h2>
  <?php if (!$kunj): ?><p class="muted">Belum ada kunjungan. <?php if (can_edit()): ?><a href="subjek.php">Tambah subjek</a>, lalu <a href="kunjungan_baru.php">buat kunjungan</a>.<?php endif; ?></p><?php else: ?>
  <div class="tbl-wrap"><table>
    <tr><th>Tanggal</th><th>Subjek</th><th>Skenario</th><th>F-4</th><th>F-6 segmen tercatat</th><th>KSS awal/maks/akhir</th><th>VDV z</th><th>F-7</th><th>Persetujuan</th><th></th></tr>
    <?php foreach ($kunj as $k): $p = $k['seg'] ? round(100 * $k['seg_isi'] / $k['seg']) : 0; ?>
    <tr<?= $k['tanggal'] === $today ? " class='today'" : '' ?>>
      <td><?= h(fmt_tgl($k['tanggal'])) ?><?= $k['tanggal'] === $today ? " <span class='pill ok'>hari ini</span>" : '' ?></td>
      <td><b><?= h($k['kode']) ?></b> · <?= (int)$k['ke'] ?></td><td><?= h($k['skenario']) ?></td>
      <td><?= $k['keputusan'] === 'lanjut' ? "<span class='pill ok'>lanjut</span>" : ($k['keputusan'] === 'tunda' ? "<span class='pill bad'>tunda</span>" : "<span class='pill'>belum</span>") ?></td>
      <td><div class="bar"><span style="width:<?= $p ?>%"></span></div><span class="muted"><?= (int)$k['seg_isi'] ?>/<?= (int)$k['seg'] ?></span></td>
      <td><?= h($k['kss_awal'] ?? '–') ?> / <?= $k['kss_max'] !== null && (int)$k['kss_max'] >= 7 ? "<b class='bad'>{$k['kss_max']}</b>" : h($k['kss_max'] ?? '–') ?> / <?= h($k['kss_akhir'] ?? '–') ?></td>
      <td><?= h($k['vdv_z'] ?? '–') ?></td>
      <td><?= $k['ps'] ? "<span class='pill ok'>ada</span>" : "<span class='pill'>belum</span>" ?></td>
      <td><?php $n = $app[$k['id']] ?? 0; echo "<span class='pill " . ($n >= $nSlot ? 'ok' : '') . "'>$n/$nSlot</span>"; ?></td>
      <td style="white-space:nowrap"><a class="btn sm" href="kunjungan.php?id=<?= (int)$k['id'] ?>">Buka</a> <a class="btn sm sec" target="_blank" href="cetak.php?k=<?= (int)$k['id'] ?>">🖨</a></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>

<?php if ($kssSeries):
  // grafik KSS: satu garis per kunjungan, sumbu x = urutan titik
  $by = []; foreach ($kssSeries as $r) $by[$r['kunjungan_id']][] = $r;
  $maxN = max(array_map('count', $by)); $W = 1000; $H = 220; $L = 40; $R = 20; $T = 15; $Bm = 30;
  $x = fn($i) => $L + ($maxN > 1 ? $i * ($W - $L - $R) / ($maxN - 1) : 0);
  $y = fn($v) => $T + ($H - $T - $Bm) * (1 - ($v - 1) / 8);
  $warna = ['#1f5fbf', '#c26b00', '#1e7d4f', '#7a3fb0', '#b3261e'];
?>
<div class="card"><h2 style="margin-top:0">Perjalanan KSS per kunjungan</h2>
<div class="tbl-wrap"><svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" role="img" aria-label="Grafik KSS per kunjungan">
  <rect x="<?= $L ?>" y="<?= $T ?>" width="<?= $W - $L - $R ?>" height="<?= $y(7) - $T ?>" fill="#fbe0dd"/>
  <?php for ($g = 1; $g <= 9; $g += 2): ?><line x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= $y($g) ?>" y2="<?= $y($g) ?>" stroke="#ddd"/><text x="<?= $L - 8 ?>" y="<?= $y($g) + 4 ?>" font-size="11" text-anchor="end"><?= $g ?></text><?php endfor; ?>
  <?php $ci = 0; foreach ($by as $kid => $pts): $c = $warna[$ci++ % count($warna)]; ?>
    <polyline fill="none" stroke="<?= $c ?>" stroke-width="2" points="<?= implode(' ', array_map(fn($i) => round($x($i)) . ',' . round($y((int)$pts[$i]['kss'])), array_keys($pts))) ?>"/>
    <?php foreach ($pts as $i => $p): ?><circle cx="<?= round($x($i)) ?>" cy="<?= round($y((int)$p['kss'])) ?>" r="3.5" fill="<?= (int)$p['kss'] >= 7 ? '#b3261e' : $c ?>"><title><?= h($p['kode'] . ' ' . fmt_tgl($p['tanggal']) . ' ' . ($p['jam_mulai'] ? jam($p['jam_mulai']) : $p['waktu']) . ' — KSS ' . $p['kss']) ?></title></circle><?php endforeach; ?>
  <?php endforeach; ?>
</svg></div>
<p class="muted"><?php $ci = 0; foreach ($by as $kid => $pts) { $c = $warna[$ci++ % count($warna)]; echo "<span style='color:$c'>■</span> " . h($pts[0]['kode'] . ' · ' . fmt_tgl($pts[0]['tanggal'])) . ' &nbsp; '; } ?> Area merah = KSS ≥ 7.</p>
</div>
<?php endif; ?>

<div class="grid">
  <div class="card"><h2 style="margin-top:0">Aksi cepat</h2>
    <?php if (can_edit()): ?><p><a class="btn" href="kunjungan_baru.php">+ Kunjungan baru</a> <a class="btn sec" href="subjek.php">+ Subjek</a></p><?php endif; ?>
    <p><a class="btn sec" href="kunjungan_list.php">Daftar kunjungan</a> <a class="btn sec" href="kss.php">Kartu KSS</a></p>
    <?php if (can_edit()): ?><p><a class="btn sec" href="ekspor.php">Ekspor CSV</a></p><?php endif; ?>
  </div>
  <div class="card"><h2 style="margin-top:0">Aktivitas terbaru</h2>
    <?php if (!$akt): ?><p class="muted">Belum ada aktivitas.</p><?php else: ?>
    <table class="compact"><?php foreach ($akt as $a) echo "<tr><td class='muted'>" . h(substr($a['waktu'], 5, 11)) . "</td><td>" . h($a['nama'] ?? '–') . "</td><td>" . h($a['aksi']) . ($a['objek'] ? ' · <span class="muted">' . h($a['objek']) . '</span>' : '') . "</td></tr>"; ?></table>
    <?php if (is_role('admin')): ?><p><a href="admin_aktivitas.php">Semua aktivitas →</a></p><?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php page_foot();
