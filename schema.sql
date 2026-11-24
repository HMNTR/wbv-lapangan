-- =====================================================================
-- Aplikasi Formulir Lapangan Uji WBV — MV3 Komando, PT Pindad
-- Layak etik KEPK UNPRI No. 077/KEPK/UNPRI/X/2026
-- MySQL 5.7+/MariaDB 10.3+  ·  utf8mb4
-- PERINGATAN: berkas ini MENGHAPUS tabel lama. Jangan impor ulang setelah pengujian dimulai.
-- =====================================================================
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS wbv_lapangan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wbv_lapangan;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS informed_consent, persetujuan_form, aktivitas, pengaturan, users, henti, pasca, log_baris, kelaikan, kunjungan, subjek, rundown;
SET FOREIGN_KEY_CHECKS = 1;

-- F-3 Data demografis (hanya kode, tanpa nama)
CREATE TABLE subjek (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(10) NOT NULL UNIQUE,
  usia TINYINT UNSIGNED NULL,
  jenis_kelamin ENUM('L','P') NULL,
  tinggi_cm SMALLINT UNSIGNED NULL,
  berat_kg DECIMAL(5,1) NULL,
  pengalaman_mv3_bulan SMALLINT UNSIGNED NULL,
  pengalaman_total_tahun TINYINT UNSIGNED NULL,
  jam_mengemudi_minggu TINYINT UNSIGNED NULL,
  tidur_rata_jam DECIMAL(3,1) NULL,
  kacamata ENUM('tidak','kacamata','lensa') NULL,
  rokok_batang_hari TINYINT UNSIGNED NULL,
  kopi_gelas_hari TINYINT UNSIGNED NULL,
  mcu_terakhir VARCHAR(20) NULL,
  ic_tanggal DATE NULL,
  catatan TEXT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Template rundown (24 = skenario tim manekin, 25 = skenario WBV)
-- tipe: d = mengemudi, j = jeda/KSS, r = rekam diam, x = lain-lain
CREATE TABLE rundown (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hari VARCHAR(4) NOT NULL,
  sesi ENUM('pagi','siang') NOT NULL,
  urut SMALLINT NOT NULL,
  waktu VARCHAR(30) NOT NULL,
  menit SMALLINT NULL,
  kegiatan VARCHAR(255) NOT NULL,
  keterangan VARCHAR(255) NULL,
  tipe ENUM('d','j','r','x') NOT NULL,
  id_rekaman VARCHAR(40) NULL,
  jalur_v VARCHAR(30) NULL,
  KEY (hari, sesi, urut)
) ENGINE=InnoDB;

-- Kunjungan (satu hari pengujian per subjek)
CREATE TABLE kunjungan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subjek_id INT NOT NULL,
  ke TINYINT UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  template_hari VARCHAR(4) NOT NULL,
  skenario VARCHAR(60) NOT NULL,
  kendaraan VARCHAR(60) NOT NULL DEFAULT 'MV3 Komando',
  no_kendaraan VARCHAR(30) NULL,
  cuaca VARCHAR(40) NULL,
  lintasan ENUM('kering','basah') NULL,
  uji_ketuk TINYINT(1) NOT NULL DEFAULT 0,
  diam_awal_jam TIME NULL,
  diam_akhir_jam TIME NULL,
  status ENUM('rencana','berjalan','selesai','berhenti') NOT NULL DEFAULT 'rencana',
  kompensasi_diterima TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (subjek_id, ke),
  FOREIGN KEY (subjek_id) REFERENCES subjek(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- F-4 Kelaikan pra-sesi
CREATE TABLE kelaikan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kunjungan_id INT NOT NULL UNIQUE,
  jam_periksa TIME NULL,
  bersedia TINYINT(1) NULL,
  jam_tidur VARCHAR(20) NULL,
  lama_tidur DECIMAL(3,1) NULL,
  jam_bangun TIME NULL,
  alkohol TINYINT(1) NULL,
  obat_kantuk TINYINT(1) NULL,
  kafein TINYINT(1) NULL,
  keluhan VARCHAR(255) NULL,
  sisa_lelah ENUM('tidak','ringan','berat') NULL,
  td_sistol SMALLINT NULL,
  td_diastol SMALLINT NULL,
  nadi SMALLINT NULL,
  kss_awal TINYINT NULL,
  keputusan ENUM('lanjut','tunda') NULL,
  alasan_tunda VARCHAR(255) NULL,
  siang_jam TIME NULL,
  siang_kss TINYINT NULL,
  siang_keluhan VARCHAR(255) NULL,
  siang_keputusan ENUM('lanjut','berhenti') NULL,
  nakes VARCHAR(80) NULL,
  peneliti VARCHAR(80) NULL,
  updated_by INT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (kunjungan_id) REFERENCES kunjungan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- F-6 Log mengemudi (satu baris per baris rundown)
CREATE TABLE log_baris (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kunjungan_id INT NOT NULL,
  rundown_id INT NOT NULL,
  jam_mulai TIME NULL,
  jam_selesai TIME NULL,
  kss TINYINT NULL,
  v_aktual VARCHAR(30) NULL,
  pass VARCHAR(20) NULL,
  kode_obs VARCHAR(20) NULL,
  catatan VARCHAR(255) NULL,
  jadwal_mulai TIME NULL,
  jadwal_selesai TIME NULL,
  menit_jadwal SMALLINT NULL,
  updated_by INT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY (kunjungan_id, rundown_id),
  FOREIGN KEY (kunjungan_id) REFERENCES kunjungan(id) ON DELETE CASCADE,
  FOREIGN KEY (rundown_id) REFERENCES rundown(id)
) ENGINE=InnoDB;

-- Penghentian lebih awal
CREATE TABLE henti (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kunjungan_id INT NOT NULL,
  jam TIME NOT NULL,
  alasan VARCHAR(120) NOT NULL,
  tindakan ENUM('istirahat_lanjut','sesi_diakhiri') NOT NULL,
  catatan VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kunjungan_id) REFERENCES kunjungan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- F-7 Pasca-sesi
CREATE TABLE pasca (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kunjungan_id INT NOT NULL UNIQUE,
  jam_selesai TIME NULL,
  menit_pagi SMALLINT NULL,
  menit_siang SMALLINT NULL,
  kss_akhir TINYINT NULL,
  semua_selesai TINYINT(1) NULL,
  tidak_selesai VARCHAR(255) NULL,
  vdv_z DECIMAL(6,2) NULL,
  loss_persen DECIMAL(5,2) NULL,
  kulit ENUM('normal','kemerahan') NULL,
  kulit_tindakan VARCHAR(255) NULL,
  keluhan VARCHAR(255) NULL,
  ktd TINYINT(1) NULL,
  ktd_uraian TEXT NULL,
  berkas_lengkap TINYINT(1) NULL,
  berkas_hilang VARCHAR(255) NULL,
  bersedia_data TINYINT(1) NULL,
  deviasi VARCHAR(255) NULL,
  updated_by INT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (kunjungan_id) REFERENCES kunjungan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Tambahan v2: pengguna, pengaturan, jejak aktivitas ----------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(40) NOT NULL UNIQUE,
  nama VARCHAR(120) NOT NULL,
  jabatan VARCHAR(120) NOT NULL DEFAULT '',
  peran ENUM('admin','ketua','peneliti','bangprod','qa') NOT NULL DEFAULT 'peneliti',
  password_hash VARCHAR(255) NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  wajib_ganti TINYINT(1) NOT NULL DEFAULT 1,
  login_terakhir DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pengaturan (
  kunci VARCHAR(40) PRIMARY KEY,
  nilai VARCHAR(255) NOT NULL,
  label VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS aktivitas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  aksi VARCHAR(60) NOT NULL,
  objek VARCHAR(255) NULL,
  kunjungan_id INT NULL,
  ip VARCHAR(45) NULL,
  waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (waktu), KEY (kunjungan_id)
) ENGINE=InnoDB;

-- Akun awal: admin / admin123 (wajib diganti saat login pertama)
INSERT IGNORE INTO users (username, nama, jabatan, peran, password_hash, wajib_ganti)
VALUES ('admin', 'Administrator', 'Admin aplikasi', 'admin', '$2y$10$hgWnUwUww/dH4rikqeT..e8KMuUWg9w0PhiWwHFYfgmZ/yzlKKEO6', 1);

INSERT IGNORE INTO pengaturan (kunci, nilai, label) VALUES
 ('no_etik', '077/KEPK/UNPRI/X/2026', 'Nomor layak etik'),
 ('peneliti', 'Periyadi, S.T., M.T.', 'Peneliti utama'),
 ('kompensasi', 'Rp. 150.000', 'Kompensasi per kunjungan'),
 ('td_sistol_max', '160', 'Batas tekanan darah sistolik (tunda bila ≥) — ditetapkan Ketua Penelitian'),
 ('td_diastol_max', '100', 'Batas tekanan darah diastolik (tunda bila ≥)'),
 ('nadi_min', '50', 'Nadi minimum (×/menit)'),
 ('nadi_max', '110', 'Nadi maksimum (×/menit)'),
 ('tidur_min_jam', '5', 'Lama tidur minimum (jam)'),
 ('kss_tunda', '7', 'KSS batas tunda/henti'),
 ('lokasi', 'Area uji PT Pindad (Persero), Bandung', 'Lokasi pengujian'),
 ('kendaraan', 'MV3 Komando', 'Kendaraan uji');


-- ---------- Tambahan v3: persetujuan elektronik & informed consent ----------
CREATE TABLE IF NOT EXISTS persetujuan_form (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kunjungan_id INT NOT NULL,
  form ENUM('f4','f6_pagi','f6_siang','f7') NOT NULL,
  slot ENUM('ketua','peneliti','bangprod','qa') NOT NULL,
  user_id INT NOT NULL,
  nama VARCHAR(120) NOT NULL,
  jabatan VARCHAR(120) NOT NULL,
  waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ip VARCHAR(45) NULL,
  UNIQUE KEY (kunjungan_id, form, slot),
  FOREIGN KEY (kunjungan_id) REFERENCES kunjungan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Lembar informasi + persetujuan setelah penjelasan (memuat nama subjek: akses terbatas)
CREATE TABLE IF NOT EXISTS informed_consent (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subjek_id INT NOT NULL UNIQUE,
  versi_teks CHAR(40) NOT NULL,
  bagian_dibaca TEXT NOT NULL,
  tgl_penjelasan DATETIME NOT NULL,
  nama VARCHAR(120) NOT NULL,
  usia TINYINT UNSIGNED NULL,
  alamat VARCHAR(255) NULL,
  hp VARCHAR(30) NULL,
  video ENUM('bersedia','tidak') NOT NULL,
  dihubungi ENUM('bersedia','tidak') NOT NULL,
  setuju TINYINT(1) NOT NULL,
  tempat VARCHAR(60) NOT NULL DEFAULT 'Bandung',
  ttd_subjek MEDIUMTEXT NOT NULL,
  peneliti_user_id INT NOT NULL,
  peneliti_nama VARCHAR(120) NOT NULL,
  peneliti_jabatan VARCHAR(120) NOT NULL,
  ttd_peneliti MEDIUMTEXT NOT NULL,
  saksi_nama VARCHAR(120) NOT NULL,
  saksi_jabatan VARCHAR(120) NOT NULL,
  ttd_saksi MEDIUMTEXT NOT NULL,
  ditandatangani_at DATETIME NOT NULL,
  ditarik_at DATETIME NULL,
  ditarik_catatan VARCHAR(255) NULL,
  ditarik_hapus_data TINYINT(1) NULL,
  FOREIGN KEY (subjek_id) REFERENCES subjek(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO rundown (hari,sesi,urut,waktu,menit,kegiatan,keterangan,tipe,id_rekaman,jalur_v) VALUES
('24','pagi',1,'09.30–09.40',10,'Mobil diam, mesin menyala (data diam 10′)','','r','DIS-MV3-24-DIAM',NULL),
('24','pagi',2,'09.40–09.56',16,'Jalur Sinus 1 & 2','8× (S1→S2)','d','DIS-MV3-24-A01',NULL),
('24','pagi',3,'09.56–10.10',14,'Jeda; break, Indonesia Raya','','j',NULL,NULL),
('24','pagi',4,'10.10–10.14',4,'Jalur Sinus 1 & 2','2× (S1→S2)','d','DIS-MV3-24-A02',NULL),
('24','pagi',5,'10.14–10.21',7,'Jeda ke jalur selanjutnya','','j',NULL,NULL),
('24','pagi',6,'10.21–10.41',20,'Menanjak 4 km/jam & menurun','5× naik-turun','d','DIS-MV3-24-A03',NULL),
('24','pagi',7,'10.41–10.45',4,'Jeda ke kecepatan selanjutnya','','j',NULL,NULL),
('24','pagi',8,'10.45–10.48',3,'Jalur Datar 10 km/jam','','d','DIS-MV3-24-A04',NULL),
('24','pagi',9,'10.48–10.49',1,'Jeda','','j',NULL,NULL),
('24','pagi',10,'10.49–10.52',3,'Jalur Datar 20 km/jam','','d','DIS-MV3-24-A05',NULL),
('24','pagi',11,'10.52–10.53',1,'Jeda','','j',NULL,NULL),
('24','pagi',12,'10.53–10.56',3,'Jalur Datar 30 km/jam','','d','DIS-MV3-24-A06',NULL),
('24','pagi',13,'10.56–10.57',1,'Jeda koordinasi','','j',NULL,NULL),
('24','pagi',14,'10.57–11.00',3,'Jalur Datar 40 km/jam','','d','DIS-MV3-24-A07',NULL),
('24','pagi',15,'11.00–11.05',5,'Jeda ke jalur selanjutnya','','j',NULL,NULL),
('24','pagi',16,'11.05–11.12',7,'Jalur Miring 10 km/jam','5× kiri-kanan','d','DIS-MV3-24-A08',NULL),
('24','pagi',17,'11.12–11.14',2,'Jeda','','j',NULL,NULL),
('24','pagi',18,'11.14–11.21',7,'Jalur Miring 20 km/jam','5× kiri-kanan','d','DIS-MV3-24-A09',NULL),
('24','pagi',19,'11.21–11.23',2,'Jeda','','j',NULL,NULL),
('24','pagi',20,'11.23–11.30',7,'Jalur Miring 30 km/jam','5× kiri-kanan','d','DIS-MV3-24-A10',NULL),
('24','pagi',21,'11.30–11.32',2,'Jeda ke jalur selanjutnya','','j',NULL,NULL),
('24','pagi',22,'11.32–11.45',13,'Jalur Balok Sejajar 8 km/jam','12× lintasan','d','DIS-MV3-24-A11',NULL),
('24','pagi',23,'11.45–11.47',2,'Jeda','','j',NULL,NULL),
('24','pagi',24,'11.47–12.00',13,'Jalur Balok Sejajar 10 km/jam','12× lintasan','d','DIS-MV3-24-A12',NULL),
('24','pagi',25,'12.00–12.01',1,'Berhenti, kembali ke bengkel','','j',NULL,NULL),
('24','pagi',26,'12.01–12.05',4,'Rekam diam 30″; evaluasi, cek & simpan data','','x',NULL,NULL),
('24','siang',1,'13.30–13.37',7,'Jalur Miring 10 km/jam','5× kiri-kanan','d','DIS-MV3-24-B01',NULL),
('24','siang',2,'13.37–13.39',2,'Jeda ke kecepatan selanjutnya','','j',NULL,NULL),
('24','siang',3,'13.39–13.46',7,'Jalur Miring 20 km/jam','5× kiri-kanan','d','DIS-MV3-24-B02',NULL),
('24','siang',4,'13.46–13.49',3,'Jeda ke kecepatan selanjutnya','','j',NULL,NULL),
('24','siang',5,'13.49–13.56',7,'Jalur Miring 30 km/jam','5× kiri-kanan','d','DIS-MV3-24-B03',NULL),
('24','siang',6,'13.56–13.58',2,'Jeda ke jalur selanjutnya','','j',NULL,NULL),
('24','siang',7,'13.58–14.00',2,'Jalur Datar 10 km/jam','','d','DIS-MV3-24-B04',NULL),
('24','siang',8,'14.00–14.02',2,'Jalur Datar 20 km/jam','','d','DIS-MV3-24-B05',NULL),
('24','siang',9,'14.02–14.04',2,'Jalur Datar 30 km/jam','','d','DIS-MV3-24-B06',NULL),
('24','siang',10,'14.04–14.06',2,'Jalur Datar 40 km/jam','','d','DIS-MV3-24-B07',NULL),
('24','siang',11,'14.06–14.10',4,'Jeda ke jalur selanjutnya','','j',NULL,NULL),
('24','siang',12,'14.10–14.30',20,'Menanjak 5 km/jam & menurun','5× naik-turun','d','DIS-MV3-24-B08',NULL),
('24','siang',13,'14.30–14.34',4,'Jeda ke jalur selanjutnya','','j',NULL,NULL),
('24','siang',14,'14.34–14.49',15,'Sinus 1 & 2','7× (S1→S2)','d','DIS-MV3-24-B09',NULL),
('24','siang',15,'14.49–14.51',2,'Jeda ke jalur selanjutnya','','j',NULL,NULL),
('24','siang',16,'14.51–15.04',13,'Jalur Balok Sejajar 8 km/jam','12× lintasan','d','DIS-MV3-24-B10',NULL),
('24','siang',17,'15.04–15.06',2,'Jeda ke kecepatan selanjutnya','','j',NULL,NULL),
('24','siang',18,'15.06–15.19',13,'Jalur Balok Sejajar 10 km/jam','12× lintasan','d','DIS-MV3-24-B11',NULL),
('24','siang',19,'15.19–15.30',11,'Cek & simpan data','','x',NULL,NULL),
('25','pagi',1,'09.20–09.30',10,'Cek pra-uji + KSS awal (F-4)','','j',NULL,NULL),
('25','pagi',2,'09.30–09.40',10,'Mobil nyala diam (data diam 10′, mesin menyala)','','r',NULL,NULL),
('25','pagi',3,'09.40 — mulai',40,'D1 — Jalan datar U1, 20 & 40 km/jam (20′ per kecepatan)','','d','DIS-MV3-D1-{SUBJ}-R1','U1 · 20'),
('25','pagi',4,'09.50',NULL,'','','d',NULL,'U1 · 20'),
('25','pagi',5,'10.00',NULL,'','','d',NULL,'U1 · 40'),
('25','pagi',6,'10.10',NULL,'','','d',NULL,'U1 · 40'),
('25','pagi',7,'10.20 — selesai',NULL,'','','d',NULL,'U1 · 40'),
('25','pagi',8,'10.20–10.25',5,'Unduh data D1 (jalan datar U1)','','j',NULL,NULL),
('25','pagi',9,'10.25 — mulai',40,'D2 — Jalur datar U1 + balok sejajar U4, 20 km/jam (masing-masing 20′ tiap jalur)','','d','DIS-MV3-D2-{SUBJ}-R1','U1 · 20'),
('25','pagi',10,'10.35',NULL,'','','d',NULL,'U1 · 20'),
('25','pagi',11,'10.45',NULL,'','','d',NULL,'U4 · 20'),
('25','pagi',12,'10.55',NULL,'','','d',NULL,'U4 · 20'),
('25','pagi',13,'11.05 — selesai',NULL,'','','d',NULL,'U4 · 20'),
('25','pagi',14,'11.05–11.10',5,'Unduh data D2 (U1 + U4)','','j',NULL,NULL),
('25','pagi',15,'11.10 — mulai',40,'D3 — Kejut tunggal terkontrol U1 + U2 (miring 30°) + U4. v dicatat: U1 …… U2 …… U4 ……','','d','DIS-MV3-D3-{SUBJ}-R1',NULL),
('25','pagi',16,'11.20',NULL,'','','d',NULL,NULL),
('25','pagi',17,'11.30',NULL,'','','d',NULL,NULL),
('25','pagi',18,'11.40',NULL,'','','d',NULL,NULL),
('25','pagi',19,'11.50 — selesai',NULL,'','','d',NULL,NULL),
('25','pagi',20,'11.50–11.55',5,'Unduh data D3 (kejut tunggal terkontrol)','','j',NULL,NULL),
('25','pagi',21,'11.55–12.20',25,'Evaluasi dan persiapan ISHOMA','','x',NULL,NULL),
('25','siang',1,'13.20–13.40',20,'Persiapan smart mannequin Ronde 2 · cek ulang KSS (F-4)','','j',NULL,NULL),
('25','siang',2,'13.40 — mulai',90,'D4 — Paparan berkelanjutan U1 + U2 + U4 sampai ujung (90′). v dicatat: U1 …… U2 …… U4 ……','','d','DIS-MV3-D4-{SUBJ}-R1',NULL),
('25','siang',3,'13.50',NULL,'','','d',NULL,NULL),
('25','siang',4,'14.00',NULL,'','','d',NULL,NULL),
('25','siang',5,'14.10',NULL,'','','d',NULL,NULL),
('25','siang',6,'14.20',NULL,'','','d',NULL,NULL),
('25','siang',7,'14.30',NULL,'','','d',NULL,NULL),
('25','siang',8,'14.40',NULL,'','','d',NULL,NULL),
('25','siang',9,'14.50',NULL,'','','d',NULL,NULL),
('25','siang',10,'15.00',NULL,'','','d',NULL,NULL),
('25','siang',11,'15.10 — selesai',NULL,'','','d',NULL,NULL),
('25','siang',12,'15.10–15.20',10,'Unduh & backup data paparan berkelanjutan; pemulihan pengemudi','','j',NULL,NULL),
('25','siang',13,'15.20 — mulai',30,'Kejut tunggal U1 + U2 + U4, 10 & 20 km/jam × 4 repetisi. Pembagian v: U1 …… U2 …… U4 ……','','d','DIS-MV3-D3b-{SUBJ}-R1',NULL),
('25','siang',14,'15.30',NULL,'','','d',NULL,NULL),
('25','siang',15,'15.40',NULL,'','','d',NULL,NULL),
('25','siang',16,'15.50 — selesai',NULL,'','','d',NULL,NULL),
('25','siang',17,'15.50–15.55',5,'Closing: backup data, cek kualitas data keempat skenario','','x',NULL,NULL);
