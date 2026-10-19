# BNI Anniversary Celebration - Full-Stack Event Management & Magic Wheel

Website full-stack modern, profesional, dan elegan untuk perayaan **BNI Anniversary Celebration**. Aplikasi ini dirancang untuk mengelola database 1.200+ peserta pegawai BNI, check-in QR Code mandiri dengan validasi NIK 16-digit, serta pengundian hadiah doorprize menggunakan **Magic Wheel** digital interaktif yang menampilkan **Nama Lengkap** peserta secara langsung dari database MySQL.

---

## 1. Fitur Utama

- **Desain & Identitas Khas BNI**: Palet warna resmi BNI Orange (`#F37021`), Navy/Deep Teal (`#005E54` / `#0B2240`), aksen Emas Perayaan (`#F5A623`), modern glassmorphism, dan responsif untuk proyektor maupun smartphone.
- **Database 1.200+ Peserta**:
  - Validasi ketat NIK 16 digit angka (disimpan sebagai string untuk menjaga keaslian format).
  - Import massal file Excel (`.xlsx`) via **PhpSpreadsheet** dan format `.csv`.
  - Deteksi NIK duplikat dan nomor undian ganda.
  - Preview data dan laporan baris-per-baris sebelum disimpan.
  - Pencarian, filter divisi/cabang/kehadiran, pagination cepat, dan export Excel/CSV.
  - Dilengkapi script pengujian instan `seed-1200.php` untuk men-generate 1.200 peserta realistis Indonesia dalam 1 klik.
- **Sistem Check-in QR Code & Validasi NIK**:
  - Satu QR Code umum untuk meja registrasi (tersedia halaman cetak stand meja A4).
  - Peserta cukup scan menggunakan kamera smartphone tanpa perlu menginstal aplikasi.
  - Input NIK divalidasi di backend PHP dengan transaksi atomik dan proteksi CSRF.
  - Masking NIK pada layar publik (`3171********0001`) demi perlindungan privasi.
  - Tiket digital sukses check-in dengan nomor undian dan ucapan selamat datang resmi BNI.
- **Magic Wheel (Pengundian Berdasarkan Nama)**:
  - Menampilkan **NAMA LENGKAP** peserta (bukan NIK) pada roda undian.
  - Nama diambil secara real-time dari MySQL (hanya peserta yang **HADIR** dan memenuhi aturan kemenangan).
  - **True Backend Randomness**: Pemenang dipilih secara acak aman oleh backend PHP (`random_int()`) dengan database transaction & row locking (`FOR UPDATE`) untuk mencegah double-spin atau race condition.
  - Animasi putaran dramatis (7+ detik) yang melambat secara realistis dan berhenti tepat pada nama pemenang terpilih.
  - Efek suara sintetis (ratchet ticks dan fanfare kemenangan) menggunakan HTML5 Web Audio API (berfungsi 100% offline).
  - Ledakan animasi Confetti perayaan dan pop-up pengumuman pemenang berukuran besar.
  - **Mode Presentasi Fullscreen**: Layar gelap megah khusus proyektor panggung utama.
- **Manajemen Hadiah & Aturan Kemenangan**:
  - Pengaturan kuota pemenang dan urutan prioritas undian.
  - Aturan kemenangan: *"Satu peserta maksimal menang 1 kali"* (default) atau *"Boleh menang berulang"*.
- **Role-Based Access Control (RBAC)**:
  - **Super Admin**: Akses seluruh sistem, manajemen akun, dan konfigurasi acara.
  - **Admin**: Pengelolaan data peserta, import/export, hadiah, dan laporan.
  - **Operator**: Akses khusus pemutaran Magic Wheel dan riwayat undian.
  - **Panitia**: Akses meja registrasi, monitor check-in, dan cetak QR Code.
- **Keamanan Enterprise**:
  - PDO Prepared Statements 100% (bebas SQL Injection).
  - Sanitasi `htmlspecialchars()` (bebas XSS).
  - CSRF Token pada form mutasi data.
  - Rate limiting pada login dan endpoint validasi NIK.
  - Hashing password dengan `password_hash()` BCRYPT.
  - Audit log aktivitas untuk setiap perubahan data penting.

---

## 2. Struktur Direktori Proyek

```
hut BNI/
├── admin/
│   ├── admin-footer.php       # Footer template panel admin
│   ├── admin-header.php       # Header & sidebar panel admin
│   ├── audit-logs.php         # Riwayat log aktivitas & keamanan
│   ├── checkin-monitor.php    # Live feed kedatangan peserta real-time
│   ├── dashboard.php          # Dashboard ringkasan eksekutif & statistik
│   ├── draw-history.php       # Riwayat undian Magic Wheel & pembatalan
│   ├── import.php             # Fitur import massal Excel/CSV 1.200 peserta
│   ├── magic-wheel.php        # Arena utama putaran Magic Wheel digital
│   ├── participants.php       # Kelola data peserta, filter & pagination
│   ├── print-qr.php           # Lembar cetak QR Code meja registrasi
│   ├── prizes.php             # Manajemen hadiah & aturan kemenangan
│   ├── reports.php            # Laporan rekap kehadiran & distribusi hadiah
│   ├── settings.php           # Konfigurasi branding & informasi acara
│   ├── unauthorized.php       # Halaman blokir akses tidak sah (403)
│   └── users.php              # Manajemen akun panitia & hak akses
├── api/
│   ├── confirm-checkin.php    # Konfirmasi kehadiran atomik
│   ├── draw-winner.php        # Backend pengundian acak & transaksi database
│   ├── get-draw-results.php   # API daftar pemenang
│   ├── get-eligible-participants.php # API peserta yang berhak diundi
│   ├── stats.php              # API statistik kehadiran & kuota
│   └── validate-nik.php       # API validasi 16-digit NIK & cek status
├── assets/
│   ├── css/
│   │   ├── magic-wheel.css    # Styling arena roda & mode presentasi
│   │   └── style.css          # Desain sistem tema BNI & komponen UI
│   ├── images/
│   │   └── bni-logo.svg       # Logo vektor resmi BNI Anniversary
│   └── js/
│       ├── audio-synth.js     # Synthesizer audio Web Audio API (tick & fanfare)
│       ├── confetti.browser.min.js # Animasi confetti kanvas mandiri
│       ├── magic-wheel.js     # Engine fisika kanvas putaran roda
│       └── main.js            # Interaksi global & tooltips
├── config/
│   └── database.php           # Koneksi PDO MySQL & konfigurasi terpusat
├── includes/
│   ├── auth.php               # Otentikasi sesi & pembatasan peran
│   ├── csrf.php               # Perlindungan token CSRF
│   ├── footer.php             # Footer template publik
│   ├── functions.php          # Helper bisnis, masking NIK, & format tanggal
│   └── header.php             # Header navbar template publik
├── check-in.php               # Halaman mandiri scan QR / input NIK peserta
├── check-in-success.php       # Tiket digital konfirmasi kehadiran sukses
├── composer.json              # Definisi pustaka PhpSpreadsheet & QR Code
├── database.sql               # Skrip skema database MySQL & data awal
├── download-template.php      # Download template import peserta (.xlsx/.csv)
├── export-participants.php    # Export data peserta ke Excel / CSV
├── export-winners.php         # Export daftar pemenang undian ke CSV
├── index.php                  # Landing page perayaan BNI Anniversary
├── login.php                  # Halaman masuk panitia & operator
├── logout.php                 # Proses keluar akun
├── seed-1200.php              # Generator instan 1.200 data uji realistis
├── winners.php                # Halaman showcase pemenang publik
└── README.md                  # Dokumentasi instalasi dan penggunaan
```

---

## 3. Persyaratan Sistem

- **PHP**: Versi 8.2 atau lebih baru (dengan ekstensi `pdo_mysql`, `gd`, `zip` aktif).
- **MySQL**: Versi 8.0+ atau MariaDB 10.4+.
- **Web Server**: Apache melalui XAMPP / Laragon atau server hosting PHP.
- **Composer**: Untuk manajemen dependensi `phpoffice/phpspreadsheet`.

---

## 4. Panduan Instalasi (XAMPP / Laragon)

### Langkah 1: Tempatkan Folder Proyek
Pindahkan atau letakkan folder proyek ini ke dalam direktori web server:
- **XAMPP**: `C:\xampp\htdocs\bni-anniversary`
- **Laragon**: `C:\laragon\www\bni-anniversary`

*(Atau jalankan langsung menggunakan built-in PHP server: `php -S localhost:8000`)*

### Langkah 2: Buat Database & Import `database.sql`
1. Buka browser dan buka **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Buat database baru bernama `bni_anniversary` dengan collation `utf8mb4_unicode_ci`.
3. Klik tab **Import**, pilih file `database.sql` dari folder proyek, lalu klik **Go** / **Kirim**.
4. Seluruh tabel (`participants`, `prizes`, `draw_results`, `admins`, `audit_logs`, `event_settings`) beserta akun default dan daftar hadiah resmi akan langsung terbuat.

### Langkah 3: Konfigurasi Koneksi Database
Buka file `config/database.php` dan sesuaikan kredensial jika berbeda dari standar:
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'bni_anniversary');
define('DB_USER', 'root');
define('DB_PASS', ''); // Password kosong untuk default XAMPP/Laragon
```

### Langkah 4: Instal Dependensi Composer
Buka terminal / PowerShell di folder proyek dan jalankan:
```bash
composer install
```
*(Paket `phpoffice/phpspreadsheet` dan dependensi pendukung akan terpasang di folder `vendor/`)*

### Langkah 5: Buka Aplikasi di Browser
Akses aplikasi melalui browser:
```
http://localhost/bni-anniversary/index.php
```

---

## 5. Akun Pengguna Bawaan (Default Login)

Password standar untuk seluruh akun bawaan adalah: `AdminBNI2024!` (disimpan dengan hash BCRYPT aman).

| Username | Password | Role | Kewenangan |
|---|---|---|---|
| `superadmin` | `AdminBNI2024!` | Super Admin | Akses penuh: manajemen akun, konfigurasi acara, import, undian, dan audit log |
| `admin` | `AdminBNI2024!` | Administrator | Kelola data peserta, import/export, hadiah, riwayat undian, laporan |
| `operator` | `OperatorBNI2024!` | Operator Undian | Akses khusus menjalankan Magic Wheel dan memantau pemenang |
| `panitia` | `PanitiaBNI2024!` | Panitia Registrasi | Akses meja registrasi, monitor check-in, dan cetak QR Code |

---

## 6. Prosedur Pengujian & Kriteria Selesai

### A. Pengujian Database 1.200 Peserta
1. Login sebagai `superadmin` atau `admin`.
2. Buka menu **Import & Export** (`admin/import.php`).
3. Anda dapat:
   - Mengunduh template `.xlsx` atau `.csv` melalui tombol yang disediakan, mengisi data, lalu mengunggahnya untuk melihat validasi baris-per-baris dan deteksi duplikat NIK.
   - **Atau** klik tombol **"Generate 1.200 Data Uji Otomatis"** (`seed-1200.php`) untuk langsung mengisi database dengan 1.200 pegawai BNI realistis (lengkap dengan NIK valid, nomor undian `BNI-0001` s.d `BNI-1200`, dan simulasi 850 peserta sudah check-in).

### B. Pengujian Check-in QR Code & NIK
1. Buka menu **Cetak QR Meja** (`admin/print-qr.php`) untuk melihat QR Code yang mengarah ke halaman `check-in.php`.
2. Buka halaman `check-in.php` di smartphone atau browser.
3. Masukkan NIK salah (misal: kurang dari 16 digit atau tidak terdaftar) -> Sistem menolak dengan pesan jelas.
4. Masukkan salah satu NIK valid peserta yang belum hadir -> Sistem menampilkan identitas dan nomor undian.
5. Klik **"Konfirmasi Kehadiran Saya"** -> Status atomik diubah menjadi `HADIR` dan halaman berpindah ke `check-in-success.php` disertai ledakan confetti.
6. Coba masukkan kembali NIK yang sama -> Sistem menolak dengan pesan *"Anda sudah melakukan check-in sebelumnya"*.

### C. Pengujian Magic Wheel
1. Buka menu **Magic Wheel** (`admin/magic-wheel.php`).
2. Perhatikan roda undian: nama-nama yang tampil adalah **Nama Lengkap asli** peserta dari MySQL (bukan NIK atau nama statis).
3. Pilih kategori hadiah (misal: *Grand Prize: Emas Logam Mulia Antam 50 Gram*).
4. Klik **SPIN NOW** atau klik roda di tengah:
   - Backend PHP memilih pemenang secara acak aman menggunakan `random_int()` dari kumpulan peserta yang sudah `HADIR`.
   - Transaksi database menyimpan hasil undian secara permanen ke tabel `draw_results`.
   - Roda berputar dramatis dengan efek suara ketukan pasak (*tick*) dan berhenti tepat pada nama pemenang yang dipilih backend.
   - Efek suara kemenangan (*fanfare chords*) dan ledakan confetti perayaan muncul bersama modal pengumuman pemenang berukuran besar.
5. Tekan tombol **Mode Presentasi** di pojok kanan atas untuk mengaktifkan tampilan layar penuh proyektor panggung utama.

---

## 7. Keamanan & Kepatuhan Data

- **Perlindungan NIK**: NIK tidak pernah ditampilkan secara utuh di halaman publik, Magic Wheel, tiket digital, maupun log audit. Format NIK disamarkan menjadi `3171********0001`.
- **Integritas Undian**: Frontend tidak menentukan pemenang sendiri. Tidak mungkin memanipulasi pemenang dari inspect element atau console JavaScript karena validasi dan eksekusi sepenuhnya dikendalikan oleh backend PHP dengan penguncian baris database (`FOR UPDATE`).
- **Pencegahan Klik Ganda**: Tombol putar otomatis dinonaktifkan saat pengundian berlangsung, dan server menolak request bersamaan yang melebihi kuota hadiah.

---

*Dikembangkan untuk kemeriahan dan kesuksesan BNI Anniversary Celebration. Celebrating Together, Growing Stronger!*
