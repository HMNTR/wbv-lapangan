<?php
// Menyetujui / membatalkan persetujuan elektronik formulir
require __DIR__ . '/inc.php';
require_login();
csrf_check();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');

$kid = (int)post('k'); $form = (string)post('form'); $slot = (string)post('slot');
$kembali = (string)post('kembali', 'index.php');
$path = basename((string)parse_url($kembali, PHP_URL_PATH));
$qs = (string)parse_url($kembali, PHP_URL_QUERY);
$kembali = preg_match('/^[a-z_]+\.php$/', $path) ? $path . ($qs !== '' && preg_match('/^[A-Za-z0-9_=&%.\-]*$/', $qs) ? "?$qs" : '') : 'index.php';
$k = kunjungan_or_404($kid);
if (!isset(FORM_SLOT[$form]) || !in_array($slot, FORM_SLOT[$form], true)) { flash('Persetujuan tidak valid.', 'err'); redirect($kembali); }

if (post('aksi') === 'setujui') {
    if (!is_role($slot)) { flash('Anda tidak berwenang menyetujui sebagai ' . PERAN[$slot] . '.', 'err'); redirect($kembali); }
    if (!post('yakin')) { flash('Centang pernyataan pemeriksaan terlebih dahulu.', 'err'); redirect($kembali); }
    if (!form_terisi($kid, $form)) { flash('Formulir belum diisi.', 'err'); redirect($kembali); }
    $u = row("SELECT nama, jabatan FROM users WHERE id=?", [uid()]);
    q("INSERT INTO persetujuan_form (kunjungan_id, form, slot, user_id, nama, jabatan, ip) VALUES (?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), nama=VALUES(nama), jabatan=VALUES(jabatan), waktu=CURRENT_TIMESTAMP, ip=VALUES(ip)",
      [$kid, $form, $slot, uid(), $u['nama'], $u['jabatan'], $_SERVER['REMOTE_ADDR'] ?? null]);
    audit('setujui ' . FORM_LABEL[$form], PERAN[$slot], $kid);
    flash(FORM_LABEL[$form] . ' disetujui sebagai ' . PERAN[$slot] . '.');
} elseif (post('aksi') === 'batal') {
    $a = row("SELECT * FROM persetujuan_form WHERE kunjungan_id=? AND form=? AND slot=?", [$kid, $form, $slot]);
    if ($a && ((int)$a['user_id'] === uid() || is_role('admin'))) {
        q("DELETE FROM persetujuan_form WHERE id=?", [$a['id']]);
        audit('batal persetujuan ' . FORM_LABEL[$form], PERAN[$slot] . ' (' . $a['nama'] . ')', $kid);
        flash('Persetujuan dibatalkan.');
    } else flash('Tidak berhak membatalkan persetujuan ini.', 'err');
}
redirect($kembali);
