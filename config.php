<?php
// ================== KONFIGURASI KONEKSI DATABASE ==================
// Pengguna, kata sandi, kompensasi, dan batas aman kini diatur di aplikasi: menu Admin.
// Nilai di bawah hanya cadangan bila tabel `pengaturan` belum ada.
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'wbv_lapangan',
    'db_user' => 'root',
    'db_pass' => '',            // XAMPP/Laragon default: kosong


    'judul'      => 'Formulir Lapangan Uji WBV — MV3 Komando',
    'no_etik'    => '077/KEPK/UNPRI/X/2026',
    'peneliti'   => 'Periyadi, S.T., M.T.',
    'kompensasi' => 'Rp. 150.000',

    // Batas aman pemeriksaan (ditetapkan Ketua Penelitian; diatur di menu Admin)
    'batas' => [
        'td_sistol_max'  => 160,
        'td_diastol_max' => 100,
        'nadi_min'       => 50,
        'nadi_max'       => 110,
        'tidur_min_jam'  => 5,
        'kss_tunda'      => 7,
    ],
];
