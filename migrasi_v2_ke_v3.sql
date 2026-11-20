-- =====================================================================
-- Migrasi WBV Lapangan v2 -> v3 (TIDAK menghapus data)
-- Peran tenaga kesehatan dihapus (akun nakes dialihkan ke Peneliti),
-- ditambah peran Ketua Penelitian, Pendamping Pindad (Bangprod), Pendamping QA,
-- kolom jabatan pengguna, persetujuan elektronik, dan informed consent.
-- Untuk pembaruan dari v1: jalankan migrasi_v1_ke_v2.sql lebih dahulu.
-- =====================================================================
SET NAMES utf8mb4;
USE wbv_lapangan;
ALTER TABLE users MODIFY peran ENUM('admin','peneliti','nakes','ketua','bangprod','qa') NOT NULL DEFAULT 'peneliti';
UPDATE users SET peran='peneliti' WHERE peran='nakes';
ALTER TABLE users MODIFY peran ENUM('admin','ketua','peneliti','bangprod','qa') NOT NULL DEFAULT 'peneliti';
ALTER TABLE users MODIFY nama VARCHAR(120) NOT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS jabatan VARCHAR(120) NOT NULL DEFAULT '' AFTER nama;
UPDATE users SET jabatan='Admin aplikasi' WHERE username='admin' AND jabatan='';
UPDATE pengaturan SET label='Batas tekanan darah sistolik (tunda bila ≥) — ditetapkan Ketua Penelitian' WHERE kunci='td_sistol_max';

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
