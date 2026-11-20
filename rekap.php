<?php
// F-8 Rekap kehadiran dan kompensasi per subjek
require __DIR__ . '/inc.php';
require_login();

$sid = (int)($_GET['s'] ?? 0);
$s = row("SELECT * FROM subjek WHERE id=?", [$sid]);
if (!$s) { http_response_code(404); exit('Subjek tidak ditemukan.'); }
$list = rows("SELECT k.*, kl.kss_awal, kl.keputusan, p.kss_akhir, p.menit_pagi, p.menit_siang, p.vdv_z, p.ktd
              FROM kunjungan k LEFT JOIN kelaikan kl ON kl.kunjungan_id=k.id LEFT JOIN pasca p ON p.kunjungan_id=k.id
              WHERE k.subjek_id=? ORDER BY k.ke", [$sid]);
page_head('F-8 Rekap Kehadiran dan Kompensasi — ' . $s['kode']);
?>
<div class="card tbl-wrap">
<table>
  <tr><th>Kunjungan</th><th>Tanggal</th><th>Skenario</th><th>Status</th><th>F-4</th><th>KSS awal / akhir</th><th>Mengemudi (menit)</th><th>VDV z</th><th>KTD</th><th>Kompensasi</th></tr>
  <?php $tot = 0; foreach ($list as $k): $m = (int)$k['menit_pagi'] + (int)$k['menit_siang']; $tot += $k['kompensasi_diterima'] ? nilai_kompensasi() : 0; ?>
  <tr><td><?= (int)$k['ke'] ?></td><td><?= h(fmt_tgl($k['tanggal'])) ?></td><td><?= h($k['skenario']) ?></td><td><?= h($k['status']) ?></td>
      <td><?= h($k['keputusan'] ?? '–') ?></td><td><?= h($k['kss_awal'] ?? '–') ?> / <?= h($k['kss_akhir'] ?? '–') ?></td>
      <td><?= $m ?: '–' ?></td><td><?= h($k['vdv_z'] ?? '–') ?></td><td><?= $k['ktd'] ? "<span class='pill bad'>Ya</span>" : 'Tidak' ?></td>
      <td><?= $k['kompensasi_diterima'] ? h(setting('kompensasi')) . ' ✓' : '<span class="pill warn">belum</span>' ?></td></tr>
  <?php endforeach; ?>
</table>
<p class="muted">Total kompensasi diterima: Rp. <?= number_format($tot, 0, ',', '.') ?></p>
</div>
<p><a class="btn" target="_blank" href="cetak.php?s=<?= $sid ?>&f=f8">🖨 Cetak F-8</a> <a class="btn sec" target="_blank" href="cetak.php?s=<?= $sid ?>&f=f3">🖨 Cetak F-3</a> <a class="btn sec" href="index.php">Kembali</a></p>
<?php page_foot();
