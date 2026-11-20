<?php
require __DIR__ . '/inc.php';
require_login();
page_head('Kartu Karolinska Sleepiness Scale (KSS)');
$kss = [1 => 'Sangat-sangat waspada', 'Sangat waspada', 'Waspada', 'Cukup waspada', 'Tidak waspada, tetapi juga tidak mengantuk',
    'Ada sedikit tanda mengantuk', 'Mengantuk, tetapi tidak perlu berusaha untuk tetap terjaga', 'Mengantuk, perlu sedikit usaha untuk tetap terjaga',
    'Sangat mengantuk, perlu usaha keras untuk tetap terjaga (melawan kantuk)'];
?>
<p style="font-size:1.3rem"><b>“Seberapa mengantuk Anda dalam 5 menit terakhir?”</b> <i>Sebutkan satu angka dari 1 sampai 9.</i></p>
<table class="kssbig"><?php foreach ($kss as $n => $t) echo "<tr" . ($n >= 7 ? " class='hi'" : '') . "><td>$n</td><td>" . h($t) . "</td></tr>"; ?></table>
<p class="muted">Sumber: Åkerstedt &amp; Gillberg (1990). Skor 7–9: kendaraan dihentikan dengan aman.</p>
<?php page_foot();
