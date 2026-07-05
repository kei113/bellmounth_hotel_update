# 🏨 Hotel Bellmounth - Management System

[![Academic Project](https://img.shields.io/badge/Project-Academic-blue.svg)](https://github.com/)
[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1.svg)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3.svg)](https://getbootstrap.com/)

Sistem Informasi Manajemen Hotel Bellmounth (Sumber Jaya) adalah aplikasi berbasis web yang dirancang untuk mendigitalisasi operasional harian hotel, mulai dari manajemen inventori kamar hingga alur reservasi kompleks dengan arsitektur **Master-Detail**.

---

## 🚀 Fitur Utama

### 1. 📊 Dashboard Admin & Statistik

- Monitoring pendapatan bulanan (Real Cash Flow).
- Analisis _Occupancy Rate_ (Tingkat Keterisian Kamar).
- Panel operasional kedatangan (Check-In) dan keberangkatan (Check-Out) hari ini.
- Visualisasi status reservasi terkini.

### 2. 🛏️ Manajemen Kamar (Operasional & Master)

- **Grid View Room Management**: Monitoring status kamar secara visual (Bersih, Kotor, Terisi, Maintenance).
- **Tipe Kamar & Fasilitas**: Pengelolaan kategori kamar dengan validasi kapasitas dan harga.
- **Update Status AJAX**: Perubahan status operasional yang cepat tanpa reload halaman.

### 3. 💳 Sistem Reservasi (Arsitektur Master-Detail)

- **Multi-Room Booking**: Satu reservasi dapat mencakup beberapa kamar sekaligus.
- **Identity Verification**: Fitur unggah foto KTP/SIM untuk verifikasi tamu.
- **Financial Tracking**: Perhitungan otomatis Total Bayar, DP (Down Payment), dan Sisa Bayar.
- **Auto-Numbering**: Penomoran invoice unik berbasis tanggal (`BLMH-YYYYMMDD-XXXX`).

### 4. 🔒 Keamanan & Administrasi

- **RBAC (Role-Based Access Control)**: Pemisahan hak akses antara Admin dan Staff Resepsionis.
- **Closed System Model**: Pendaftaran akun dilakukan secara tertutup untuk menjaga integritas data internal.
- **Audit Trail (Activity Logs)**: Pencatatan setiap aktivitas CRUD untuk transparansi operasional.
- **Database Backup**: Sistem pemeliharaan database mandiri melalui antarmuka admin.

---

## 🛠️ Tech Stack

| Komponen         | Teknologi                                 |
| :--------------- | :---------------------------------------- |
| **Backend**      | Native PHP 8.0+                           |
| **Database**     | MySQL / MariaDB                           |
| **Frontend**     | Bootstrap 5, Vanilla JavaScript           |
| **Library**      | Flatpickr (Date Picker), Bootstrap Icons  |
| **Architecture** | Master-Detail Pattern, Modular Templating |

---

## 💻 Instalasi

1.  **Clone Repository**
    ```bash
    git clone https://github.com/username/sumberjaya.git
    ```
2.  **Konfigurasi XAMPP**
    - Pindahkan folder ke `C:\xampp\htdocs\sumberjaya`.
    - Aktifkan Apache dan MySQL di XAMPP Control Panel.
3.  **Persiapan Database**
    - Buat database baru bernama `bellmounth` di phpMyAdmin.
    - Impor file SQL `database/repair_database_master.sql`.
4.  **Konfigurasi Environment**
    - Sesuaikan kredensial database di file `.env`.
    ```env
    DB_HOST=localhost
    DB_NAME=bellmounth
    DB_USER=root
    DB_PASS=
    BASE_PATH=/sumberjaya
    ```
5.  **Akses Aplikasi**
    - Buka browser dan akses `http://localhost/sumberjaya`.

---

## 📐 Perancangan (Wireframes)

Proyek ini dirancang menggunakan **PlantUML Salt** untuk memastikan pengalaman pengguna (UX) yang logis sebelum tahap implementasi. Anda dapat melihat file `.puml` di dalam direktori proyek untuk detail rancangan wireframe halaman Login, Dashboard, dan Reservasi.

---

## 📝 Dokumentasi Teknis

Dokumentasi lengkap sistem ini tersedia dalam 13 Bab modul yang mencakup penjelasan baris-per-baris kode, diagram alur, dan kamus data database.

---

**Hotel Bellmounth Project**
_Dikembangkan untuk Tugas Akhir Mata Kuliah Pemrograman Web._
