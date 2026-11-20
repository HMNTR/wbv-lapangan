<?php
// Akun saya: ganti kata sandi
require __DIR__ . '/inc.php';
require_login();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = row("SELECT * FROM users WHERE id=?", [uid()]);
    $lama = (string)($_POST['lama'] ?? ''); $baru = (string)($_POST['baru'] ?? ''); $ulang = (string)($_POST['ulang'] ?? '');
    if (!password_verify($lama, $u['password_hash'])) flash('Kata sandi lama salah.', 'err');
    elseif (strlen($baru) < 8) flash('Kata sandi baru minimal 8 karakter.', 'err');
    elseif ($baru !== $ulang) flash('Konfirmasi kata sandi tidak sama.', 'err');
    elseif ($baru === $lama) flash('Kata sandi baru harus berbeda dari yang lama.', 'err');
    else {
        q("UPDATE users SET password_hash=?, wajib_ganti=0 WHERE id=?", [password_hash($baru, PASSWORD_DEFAULT), uid()]);
        $_SESSION['user']['wajib_ganti'] = 0;
        audit('ganti kata sandi', $u['username']);
        flash('Kata sandi berhasil diganti.');
        redirect('index.php');
    }
    redirect('akun.php');
}
$u = user();
page_head('Akun Saya');
?>
<div class="card" style="max-width:480px">
  <p><b><?= h($u['nama']) ?></b> (<?= h($u['username']) ?>)<br><?= h($u['jabatan'] ?? '') ?> · <?= h(PERAN[$u['peran']] ?? $u['peran']) ?></p>
  <form method="post">
    <?= csrf_field() ?>
    <label class="f">Kata sandi lama</label><input type="password" name="lama" required autocomplete="current-password">
    <label class="f" style="margin-top:.6rem">Kata sandi baru (min. 8 karakter)</label><input type="password" name="baru" required minlength="8" autocomplete="new-password">
    <label class="f" style="margin-top:.6rem">Ulangi kata sandi baru</label><input type="password" name="ulang" required minlength="8" autocomplete="new-password">
    <p><button class="btn">Ganti kata sandi</button></p>
  </form>
</div>
<?php page_foot();
