<?php
require __DIR__ . '/inc.php';
if (user()) redirect('index.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = row("SELECT * FROM users WHERE username=? AND aktif=1", [trim((string)($_POST['username'] ?? ''))]);
    // jeda kecil untuk memperlambat tebakan kata sandi
    usleep(300000);
    if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'nama' => $u['nama'], 'jabatan' => (string)($u['jabatan'] ?? ''), 'peran' => $u['peran'], 'wajib_ganti' => (int)$u['wajib_ganti']];
        q("UPDATE users SET login_terakhir=NOW() WHERE id=?", [$u['id']]);
        audit('login', $u['username']);
        redirect($u['wajib_ganti'] ? 'akun.php' : 'index.php');
    }
    audit('login gagal', trim((string)($_POST['username'] ?? '')));
    flash('Username atau kata sandi salah, atau akun nonaktif.', 'err');
    redirect('login.php');
}
page_head('Masuk');
?>
<form method="post" class="card" style="max-width:380px">
  <?= csrf_field() ?>
  <label class="f">Username</label>
  <input type="text" name="username" autofocus required autocomplete="username">
  <label class="f" style="margin-top:.6rem">Kata sandi</label>
  <input type="password" name="password" required autocomplete="current-password">
  <p><button class="btn">Masuk</button></p>
  <p class="muted">Aplikasi menyimpan data kesehatan subjek (tekanan darah, nadi, KSS). Gunakan akun masing-masing dan jangan bagikan kata sandi.</p>
</form>
<?php page_foot();
