<?php
require __DIR__ . '/inc.php';
require_login();

$subjek = rows("SELECT s.*, (SELECT COUNT(*) FROM kunjungan k WHERE k.subjek_id=s.id) n FROM subjek s ORDER BY s.kode");
$kunj = rows("SELECT k.*, s.kode,
    (SELECT keputusan FROM kelaikan WHERE kunjungan_id=k.id) kel,
    (SELECT COUNT(*) FROM log_baris l WHERE l.kunjungan_id=k.id AND (l.jam_mulai IS NOT NULL OR l.kss IS NOT NULL)) terisi,
    (SELECT COUNT(*) FROM rundown r WHERE r.hari=k.template_hari) total,
    (SELECT MAX(kss) FROM log_baris l WHERE l.kunjungan_id=k.id) kssmax,
    (SELECT id FROM pasca WHERE kunjungan_id=k.id) ps
  FROM kunjungan k JOIN subjek s ON s.id=k.subjek_id ORDER BY k.tanggal, s.kode");

page_head('Kunjungan dan Subjek');
?>
<div class="card">
  <h2 style="margin-top:0">Kunjungan</h2>
  <?php if (!$kunj): ?><p class="muted">Belum ada kunjungan. Tambahkan subjek terlebih dahulu, lalu buat kunjungan.</p>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <tr><th>Tanggal</th><th>Subjek</th><th>Ke</th><th>Skenario</th><th>F-4</th><th>F-6 terisi</th><th>KSS maks</th><th>F-7</th><th>Status</th><th></th></tr>
    <?php foreach ($kunj as $k): ?>
    <tr>
      <td><?= h(fmt_tgl($k['tanggal'])) ?></td><td><b><?= h($k['kode']) ?></b></td><td><?= h($k['ke']) ?></td><td><?= h($k['skenario']) ?></td>
      <td><?= $k['kel'] === 'lanjut' ? "<span class='pill ok'>lanjut</span>" : ($k['kel'] === 'tunda' ? "<span class='pill bad'>tunda</span>" : "<span class='pill'>belum</span>") ?></td>
      <td><?= (int)$k['terisi'] ?>/<?= (int)$k['total'] ?></td>
      <td><?= $k['kssmax'] === null ? '–' : ((int)$k['kssmax'] >= 7 ? "<span class='pill bad'>{$k['kssmax']}</span>" : h($k['kssmax'])) ?></td>
      <td><?= $k['ps'] ? "<span class='pill ok'>ada</span>" : "<span class='pill'>belum</span>" ?></td>
      <td><?= h($k['status']) ?></td>
      <td><a class="btn sm" href="kunjungan.php?id=<?= (int)$k['id'] ?>">Buka</a> <a class="btn sm sec" target="_blank" href="cetak.php?k=<?= (int)$k['id'] ?>">🖨</a></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
  <?php if (can_edit()): ?><p><a class="btn" href="kunjungan_baru.php">+ Kunjungan baru</a></p><?php endif; ?>
</div>

<div class="card">
  <h2 style="margin-top:0">Subjek</h2>
  <?php if ($subjek): ?>
  <table>
    <tr><th>Kode</th><th>Usia</th><th>Pengalaman MV3</th><th>IC ditandatangani</th><th>Kunjungan</th><th></th></tr>
    <?php foreach ($subjek as $s): ?>
    <tr><td><b><?= h($s['kode']) ?></b></td><td><?= h($s['usia']) ?></td><td><?= h($s['pengalaman_mv3_bulan']) ?> bln</td>
      <td><?= h(fmt_tgl($s['ic_tanggal'])) ?: '<span class="pill bad">belum</span>' ?></td><td><?= (int)$s['n'] ?></td>
      <td><?php if (can_edit()): ?><a class="btn sm" href="ic.php?s=<?= (int)$s['id'] ?>">Informasi &amp; IC</a> <a class="btn sm sec" href="subjek.php?id=<?= (int)$s['id'] ?>">F-3</a><?php endif; ?> <a class="btn sm sec" href="rekap.php?s=<?= (int)$s['id'] ?>">F-8 Rekap</a></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <?php if (can_edit()): ?><p><a class="btn sec" href="subjek.php">+ Subjek baru</a></p><?php endif; ?>
</div>
<?php page_foot();
