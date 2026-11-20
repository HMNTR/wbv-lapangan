-- =====================================================================
-- Migrasi WBV Lapangan v1 -> v2 (TIDAK menghapus data)
-- Jalankan sekali lewat phpMyAdmin > database wbv_lapangan > Import.
-- =====================================================================
SET NAMES utf8mb4;
USE wbv_lapangan;
ALTER TABLE subjek    ADD COLUMN IF NOT EXISTS updated_by INT NULL;
ALTER TABLE kelaikan  ADD COLUMN IF NOT EXISTS updated_by INT NULL;
ALTER TABLE log_baris ADD COLUMN IF NOT EXISTS updated_by INT NULL;
ALTER TABLE pasca     ADD COLUMN IF NOT EXISTS updated_by INT NULL;
ALTER TABLE henti     ADD COLUMN IF NOT EXISTS created_by INT NULL;
-- ---------- Tambahan v2: pengguna, pengaturan, jejak aktivitas ----------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(40) NOT NULL UNIQUE,
  nama VARCHAR(80) NOT NULL,
  peran ENUM('admin','peneliti','nakes') NOT NULL DEFAULT 'peneliti',
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
INSERT IGNORE INTO users (username, nama, peran, password_hash, wajib_ganti)
VALUES ('admin', 'Administrator', 'admin', '$2y$10$hgWnUwUww/dH4rikqeT..e8KMuUWg9w0PhiWwHFYfgmZ/yzlKKEO6', 1);

INSERT IGNORE INTO pengaturan (kunci, nilai, label) VALUES
 ('no_etik', '077/KEPK/UNPRI/X/2026', 'Nomor layak etik'),
 ('peneliti', 'Periyadi, S.T., M.T.', 'Peneliti utama'),
 ('kompensasi', 'Rp. 150.000', 'Kompensasi per kunjungan'),
 ('td_sistol_max', '160', 'Batas tekanan darah sistolik (tunda bila ≥)'),
 ('td_diastol_max', '100', 'Batas tekanan darah diastolik (tunda bila ≥)'),
 ('nadi_min', '50', 'Nadi minimum (×/menit)'),
 ('nadi_max', '110', 'Nadi maksimum (×/menit)'),
 ('tidur_min_jam', '5', 'Lama tidur minimum (jam)'),
 ('kss_tunda', '7', 'KSS batas tunda/henti'),
 ('lokasi', 'Area uji PT Pindad (Persero), Bandung', 'Lokasi pengujian'),
 ('kendaraan', 'MV3 Komando', 'Kendaraan uji');
