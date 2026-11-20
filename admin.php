<?php
// Admin: kelola pengguna
require __DIR__ . '/admin_inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'tambah') {
        $un = strtolower(trim((string)post('username', '')));
        $pw = (string)post('password', '');
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $un)) flash('Username 3–40 karakter: huruf kecil, angka, titik, minus, garis bawah.', 'err');
        elseif (strlen($pw) < 8) flash('Kata sandi awal minimal 8 karakter.', 'err');
        elseif (!isset(PERAN[post('peran')])) flash('Peran tidak valid.', 'err');
        else {
            try {
                if (!trim((string)post('nama', '')) || !trim((string)post('jabatan', ''))) { flash('Nama lengkap dan jabatan wajib diisi.', 'err'); redirect('admin.php'); }
                q("INSERT INTO users (username, nama, jabatan, peran, password_hash, wajib_ganti) VALUES (?,?,?,?,?,1)", [$un, trim((string)post('nama')), trim((string)post('jabatan')), post('peran'), password_hash($pw, PASSWORD_DEFAULT)]);
                audit('admin: tambah pengguna', "$un (" . post('peran') . ")");
                flash("Pengguna $un ditambahkan. Ia wajib mengganti kata sandi saat login pertama.");
            } catch (PDOException $e) { flash('Username sudah dipakai.', 'err'); }
        }
    } else {
        $id = (int)post('id');
        $t = row("SELECT * FROM users WHERE id=?", [$id]);
        if (!$t) { flash('Pengguna tidak ditemukan.', 'err'); redirect('admin.php'); }
        $adminAktif = (int)row("SELECT COUNT(*) n FROM users WHERE peran='admin' AND aktif=1")['n'];
        $isLastAdmin = $t['peran'] === 'admin' && $t['aktif'] && $adminAktif <= 1;
        if ($aksi === 'ubah') {
            $peran = post('peran');
            if (!isset(PERAN[$peran])) { flash('Peran tidak valid.', 'err'); redirect('admin.php'); }
            if ($isLastAdmin && $peran !== 'admin') { flash('Tidak bisa mengubah peran admin terakhir.', 'err'); redirect('admin.php'); }
            if (!trim((string)post('nama', '')) || !trim((string)post('jabatan', ''))) { flash('Nama lengkap dan jabatan wajib diisi.', 'err'); redirect('admin.php'); }
            q("UPDATE users SET nama=?, jabatan=?, peran=? WHERE id=?", [trim((string)post('nama')), trim((string)post('jabatan')), $peran, $id]);
            if ($id === uid()) { $_SESSION['user']['nama'] = trim((string)post('nama')); $_SESSION['user']['jabatan'] = trim((string)post('jabatan')); $_SESSION['user']['peran'] = $peran; }
            audit('admin: ubah pengguna', "{$t['username']} → $peran");
            flash('Data pengguna diperbarui.');
        } elseif ($aksi === 'reset') {
            $pw = (string)post('password', '');
            if (strlen($pw) < 8) { flash('Kata sandi minimal 8 karakter.', 'err'); redirect('admin.php'); }
            q("UPDATE users SET password_hash=?, wajib_ganti=1 WHERE id=?", [password_hash($pw, PASSWORD_DEFAULT), $id]);
            audit('admin: reset kata sandi', $t['username']);
            flash("Kata sandi {$t['username']} direset; wajib diganti saat login berikutnya.");
        } elseif ($aksi === 'aktif') {
            if ($id === uid()) { flash('Tidak bisa menonaktifkan akun sendiri.', 'err'); redirect('admin.php'); }
            if ($isLastAdmin) { flash('Tidak bisa menonaktifkan admin terakhir.', 'err'); redirect('admin.php'); }
            q("UPDATE users SET aktif=1-aktif WHERE id=?", [$id]);
            audit('admin: ' . ($t['aktif'] ? 'nonaktifkan' : 'aktifkan') . ' pengguna', $t['username']);
            flash('Status pengguna diubah.');
        }
    }
    redirect('admin.php');
}

$users = rows("SELECT u.*, (SELECT COUNT(*) FROM aktivitas a WHERE a.user_id=u.id) n_akt FROM users u ORDER BY u.aktif DESC, u.peran, u.username");
$pilihPeran = fn($val) => implode('', array_map(fn($k, $l) => "<option value='$k'" . ($val === $k ? ' selected' : '') . ">$l</option>", array_keys(PERAN), PERAN));
page_head('Admin — Pengguna');
admin_nav();
?>
<div class="card">
  <table class="compact" style="margin-bottom:.8rem">
    <tr><td><b>Admin</b></td><td>Semua menu + halaman Admin (pengguna, rundown, pengaturan, jejak, cadangan). Tidak memberi persetujuan formulir.</td></tr>
    <tr><td><b>Ketua Penelitian</b></td><td>Mengisi semua formulir; menyetujui F-4 dan F-7; satu-satunya yang dapat menyetujui sesi "Lanjut" bila ada kriteria tunda di F-4.</td></tr>
    <tr><td><b>Peneliti</b></td><td>Mengisi semua formulir, informed consent, ekspor; menyetujui F-4, F-6, F-7.</td></tr>
    <tr><td><b>Pendamping Pindad (Bangprod)</b></td><td>Melihat dan mencetak; menyetujui F-4, F-6, F-7.</td></tr>
    <tr><td><b>Pendamping QA</b></td><td>Melihat dan mencetak; menyetujui F-6.</td></tr>
  </table>
  <div class="tbl-wrap"><table>
    <tr><th>Username</th><th>Nama · Jabatan · Peran</th><th>Status</th><th>Login terakhir</th><th>Reset kata sandi</th><th></th></tr>
    <?php foreach ($users as $u): ?>
    <tr<?= $u['aktif'] ? '' : " style='opacity:.55'" ?>>
      <td><b><?= h($u['username']) ?></b><?= (int)$u['id'] === uid() ? " <span class='pill'>Anda</span>" : '' ?></td>
      <td><form method="post" style="display:flex;gap:.3rem;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="aksi" value="ubah"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <input type="text" name="nama" value="<?= h($u['nama']) ?>" style="width:13rem" placeholder="nama lengkap + gelar" required><input type="text" name="jabatan" value="<?= h($u['jabatan']) ?>" style="width:13rem" placeholder="jabatan" required><select name="peran" style="width:auto"><?= $pilihPeran($u['peran']) ?></select><button class="btn sm sec">Simpan</button></form></td>
      <td><?= $u['aktif'] ? "<span class='pill ok'>aktif</span>" : "<span class='pill bad'>nonaktif</span>" ?><?= $u['wajib_ganti'] ? " <span class='pill warn'>wajib ganti sandi</span>" : '' ?></td>
      <td class="muted"><?= h($u['login_terakhir'] ?? '–') ?></td>
      <td><form method="post" style="display:flex;gap:.3rem"><?= csrf_field() ?><input type="hidden" name="aksi" value="reset"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <input type="text" name="password" placeholder="sandi baru (≥ 8)" minlength="8" style="width:9.5rem"><button class="btn sm sec">Reset</button></form></td>
      <td><form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="aktif"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn sm <?= $u['aktif'] ? 'bad' : '' ?>"><?= $u['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah">
  <h2 style="margin-top:0">Tambah pengguna</h2>
  <div class="grid">
    <div><label class="f">Username</label><input type="text" name="username" required pattern="[a-z0-9._\-]{3,40}" placeholder="mis. periyadi"></div>
    <div><label class="f">Nama lengkap beserta gelar</label><input type="text" name="nama" required placeholder="mis. Periyadi, S.T., M.T."></div>
    <div><label class="f">Jabatan</label><input type="text" name="jabatan" required placeholder="mis. Ketua Penelitian / Staf Bangprod Kendaraan Khusus"></div>
    <div><label class="f">Peran</label><select name="peran"><?= $pilihPeran('peneliti') ?></select></div>
    <div><label class="f">Kata sandi awal (≥ 8, wajib diganti)</label><input type="text" name="password" required minlength="8"></div>
  </div>
  <p><button class="btn">Tambah</button></p>
</form>
<p class="muted">Pengguna tidak dihapus agar jejak siapa mengisi formulir tetap utuh; nonaktifkan bila tidak dipakai lagi.</p>
<?php page_foot();
