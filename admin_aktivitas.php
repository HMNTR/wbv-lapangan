<?php
// Admin: jejak aktivitas (audit trail)
require __DIR__ . '/admin_inc.php';

$u = intOrNull($_GET['u'] ?? null);
$cari = trim((string)($_GET['q'] ?? ''));
$hal = max(1, (int)($_GET['hal'] ?? 1)); $per = 100;
$where = []; $p = [];
if ($u) { $where[] = 'a.user_id=?'; $p[] = $u; }
if ($cari !== '') { $where[] = '(a.aksi LIKE ? OR a.objek LIKE ?)'; $p[] = "%$cari%"; $p[] = "%$cari%"; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = (int)row("SELECT COUNT(*) n FROM aktivitas a $w", $p)['n'];
$list = rows("SELECT a.*, u.username, u.nama, s.kode, k.ke FROM aktivitas a LEFT JOIN users u ON u.id=a.user_id
              LEFT JOIN kunjungan k ON k.id=a.kunjungan_id LEFT JOIN subjek s ON s.id=k.subjek_id
              $w ORDER BY a.id DESC LIMIT $per OFFSET " . (($hal - 1) * $per), $p);
$users = rows("SELECT id, username FROM users ORDER BY username");
page_head('Admin — Jejak Aktivitas');
admin_nav();
?>
<form class="card" method="get" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end">
  <div><label class="f">Pengguna</label><select name="u" style="width:auto"><option value="">semua</option><?php foreach ($users as $x) echo "<option value='{$x['id']}'" . ($u === (int)$x['id'] ? ' selected' : '') . ">" . h($x['username']) . "</option>"; ?></select></div>
  <div><label class="f">Cari aksi/objek</label><input type="text" name="q" value="<?= h($cari) ?>"></div>
  <button class="btn sec">Saring</button>
</form>
<div class="card tbl-wrap">
  <p class="muted"><?= $total ?> catatan</p>
  <table><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Kunjungan</th><th>IP</th></tr>
  <?php foreach ($list as $a) echo "<tr><td style='white-space:nowrap'>" . h($a['waktu']) . "</td><td>" . h($a['username'] ?? '–') . "</td><td>" . h($a['aksi']) . "</td><td>" . h($a['objek']) . "</td><td>" . ($a['kunjungan_id'] ? h(($a['kode'] ?? '?') . ' · ' . ($a['ke'] ?? '')) : '') . "</td><td class='muted'>" . h($a['ip']) . "</td></tr>"; ?>
  </table>
  <p><?php for ($i = 1; $i <= max(1, (int)ceil($total / $per)); $i++) echo "<a class='btn sm " . ($i === $hal ? '' : 'sec') . "' href='?hal=$i&u=" . h((string)$u) . "&q=" . urlencode($cari) . "'>$i</a> "; ?></p>
</div>
<?php page_foot();
