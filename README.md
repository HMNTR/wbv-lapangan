# Aplikasi Formulir Lapangan Uji WBV — MV3 Komando (v3)

Aplikasi web PHP + MySQL sederhana untuk mengisi, memantau, dan mencetak formulir pengujian WBV di lapangan. Isinya mengikuti paket formulir lapangan 24–25 November 2026 (layak etik KEPK UNPRI No. 077/KEPK/UNPRI/X/2026).

Aplikasi berjalan **offline** di laptop lapangan (XAMPP/Laragon), tanpa pustaka eksternal atau CDN.

## Peran pengguna (v3)

Setiap akun wajib diisi **nama lengkap beserta gelar** dan **jabatan**. Keduanya tercetak di kolom tanda tangan.

| Peran | Hak akses | Menyetujui formulir |
|---|---|---|
| **Admin** | Semua menu + Admin (pengguna, rundown, pengaturan, jejak aktivitas, cadangan) | – |
| **Ketua Penelitian** | Mengisi semua formulir. Satu-satunya yang dapat menyimpan F-4 "Lanjut" bila ada kriteria tunda | F-4, F-7 |
| **Peneliti** | Mengisi semua formulir, Informasi & IC, ekspor | F-4, F-6, F-7 |
| **Pendamping Pindad (Bangprod)** | Melihat formulir (hanya baca) dan mencetak | F-4, F-6, F-7 |
| **Pendamping QA** | Melihat formulir (hanya baca) dan mencetak | F-6 |

Peran tenaga kesehatan sudah dihapus. Fungsinya digantikan oleh Ketua Penelitian, yang menyetujui F-4 dan memutuskan bila ada kriteria tunda.

### Persetujuan elektronik

Di bawah F-4, F-6 pagi, F-6 siang, dan F-7 ada panel **Persetujuan elektronik**.

- Pengguna dengan peran yang sesuai mencentang "Saya telah memeriksa isian", lalu menekan **Setujui**.
- Nama, jabatan, dan waktu persetujuan tercatat dan tercetak di kolom tanda tangan.
- Bila isian diubah setelah disetujui, muncul tanda **data berubah setelah disetujui**.
- Persetujuan dapat dibatalkan oleh pemberinya sendiri atau oleh admin.

## Lembar Informasi & Informed Consent

Buka lewat menu **Kunjungan → Informasi & IC** (pada baris subjek).

1. **Langkah 1 — Lembar Informasi.** Ke-21 bagian dokumen (pembuka + butir 1–20) ditampilkan apa adanya. Setiap bagian harus dicentang **Sudah dibaca/dijelaskan**.
2. **Langkah 2 — Persetujuan.** Formulir baru terbuka setelah semua bagian dicentang. Isiannya:
   - tanggal penjelasan (aplikasi menolak bila belum ≥ 24 jam);
   - nama, usia, alamat, HP;
   - pernyataan 1–3;
   - pilihan video wajah dan dihubungi kembali;
   - SETUJU / TIDAK SETUJU;
   - **tanda tangan digital** subjek, peneliti, dan saksi (bisa dengan jari di tablet atau mouse).
3. Setelah tersimpan, persetujuan terkunci dan dapat **dicetak rangkap dua**. Penarikan persetujuan dapat dicatat kapan saja.

Teks informasi ada di `ic_teks.php`. Setiap persetujuan menyimpan sidik versi teks, sehingga perubahan teks setelah tanda tangan akan terdeteksi.

Data IC memuat nama subjek, jadi hanya dapat dibuka oleh Admin, Ketua, dan Peneliti.

## Instalasi baru (XAMPP di Windows)

1. Pasang XAMPP (PHP 8.0 ke atas), lalu jalankan **Apache** dan **MySQL**.
2. Salin folder `wbv-lapangan` ke `C:\xampp\htdocs\`.
3. Buka `http://localhost/phpmyadmin`, pilih **Import**, unggah `schema.sql`, lalu klik **Go**.
4. Bila perlu, sesuaikan `db_user`/`db_pass` di `config.php` (bawaan XAMPP: `root` tanpa kata sandi).
5. Buka `http://localhost/wbv-lapangan/`, masuk sebagai `admin`/`admin123`, lalu ganti kata sandi.
6. Di **Admin → Pengaturan**, isi batas aman tekanan darah dan nadi sesuai ketetapan Ketua Penelitian.

> ⚠️ **`schema.sql` menghapus semua tabel lama.** Gunakan hanya untuk instalasi baru.

## Pembaruan dari versi sebelumnya (data tetap aman)

- Dari **v2**: ekspor cadangan → timpa berkas aplikasi → impor `migrasi_v2_ke_v3.sql`. Akun bertipe tenaga kesehatan dialihkan menjadi Peneliti. Lengkapi jabatan setiap akun di Admin → Pengguna.
- Dari **v1**: jalankan `migrasi_v1_ke_v2.sql` lalu `migrasi_v2_ke_v3.sql`.

### Rincian dari v1

1. **Unduh/ekspor cadangan database terlebih dahulu.**
2. Timpa berkas aplikasi dengan berkas v2. `config.php` lama boleh dipakai lagi.
3. phpMyAdmin → `wbv_lapangan` → **Import** → `migrasi_v1_ke_v2.sql`.

   Migrasi hanya menambah tabel/kolom, tidak menghapus data, dan aman bila terjalankan dua kali. Perlu MariaDB, yang sudah bawaan XAMPP/Laragon.
4. Masuk dengan `admin`/`admin123`.

## Alur di lapangan

1. **Admin (sebelum hari uji):**
   - buat akun (nama + jabatan) untuk Ketua Penelitian, peneliti/asisten, pendamping Bangprod, dan pendamping QA;
   - isi pengaturan batas aman;
   - cek rundown di **Admin → Rundown**.
2. **Peneliti:** tambah subjek `S01` (F-3), lalu buat Kunjungan 1 (24 Nov, template 24) dan Kunjungan 2 (25 Nov, template 25).
3. **Peneliti/Ketua Penelitian:** isi **Informasi & IC** (bila belum) lalu **F-4**. Aturan tunda dicek otomatis; keputusan "Lanjut" saat ada kriteria tunda hanya bisa disimpan Ketua Penelitian.
4. **Peneliti:** mengisi **F-6** selama mengemudi:
   - tombol ⏱ mengisi jam sekarang;
   - KSS di setiap jeda atau setiap 10 menit;
   - KSS ≥ 7 memunculkan peringatan.
5. **Akhir hari:**
   - isi **F-7**;
   - minta persetujuan elektronik dari Ketua, Peneliti, Bangprod, dan QA di setiap formulir;
   - cetak semua formulir kunjungan (**🖨 Cetak**); persetujuan elektronik sudah tercetak di kolom tanda tangan;
   - **Admin → Cadangan → Unduh**.
6. **Setelah pengujian:** gunakan **Ekspor CSV** (log + ID rekaman untuk memotong berkas logger WBV, linimasa KSS untuk label model).

Tanda tangan basah tetap di kertas. Cetakan aplikasi sudah memuat isian dan kolom tanda tangan, jadi formulir kosong tidak perlu diisi ulang dengan tangan.

## Keamanan

- Kata sandi disimpan ter-hash (bcrypt).
- Sesi diperbarui setiap login, dan login gagal diperlambat.
- Semua formulir memakai token CSRF, dan semua kueri memakai *prepared statement*.
- Hanya kode subjek yang disimpan, tanpa nama.
- Jalankan hanya di `localhost` atau jaringan tertutup. Jangan dipublikasikan ke internet tanpa HTTPS.
- Pengguna tidak dihapus, hanya dinonaktifkan, agar jejak siapa mengisi tetap utuh.

## Struktur berkas

```
config.php                 koneksi database
inc.php                    inti: PDO, login & peran, pengaturan, audit, layout
schema.sql                 instalasi baru (struktur + rundown 24/25 + akun admin)
migrasi_v1_ke_v2.sql       pembaruan dari v1 tanpa menghapus data
migrasi_v2_ke_v3.sql       pembaruan dari v2 tanpa menghapus data
ic.php, ic_teks.php        lembar informasi + informed consent (tanda tangan digital)
persetujuan.php            persetujuan elektronik formulir
login.php logout.php akun.php
index.php                  dashboard
kunjungan_list.php         daftar kunjungan & subjek
subjek.php                 F-3
kunjungan_baru.php kunjungan.php
kelaikan.php               F-4
log.php                    F-6 + penghentian lebih awal
pasca.php                  F-7
rekap.php                  F-8
cetak.php                  cetak A4 (F-3, F-4, F-6, F-7, F-8)
kss.php                    kartu KSS
ekspor.php                 ekspor CSV
admin_inc.php admin.php admin_rundown.php admin_pengaturan.php admin_aktivitas.php admin_backup.php
style.css app.js
```
