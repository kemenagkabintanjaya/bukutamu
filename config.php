<?php
/**
 * ================================================================
 * KONFIGURASI SISTEM BUKU TAMU DIGITAL
 * Kemenag Kab. Intan Jaya, Papua Pegunungan
 * ================================================================
 *
 * PETUNJUK SETUP:
 * 1. Isi GOOGLE_FORM_ACTION_URL dengan URL "action" dari Google Form Anda.
 *    Caranya: Buka Google Form → Klik kanan "Kirim" → Inspect Element → 
 *    Cari <form action="https://docs.google.com/forms/d/.../formResponse">
 *    Salin URL tersebut ke konstanta di bawah.
 *
 * 2. Isi FIELD_IDs sesuai dengan atribut "name" setiap field di Google Form.
 *    Caranya: Di halaman form (view page source), cari input dengan name="entry.XXXXXXX"
 *
 * 3. KEAMANAN: Pindahkan file config.php ke luar folder public (di atas document root),
 *    atau tambahkan aturan di .htaccess agar file ini tidak bisa diakses langsung.
 *
 * 4. Untuk penyimpanan lokal sebagai backup (opsional), aktifkan SIMPAN_KE_FILE = true
 *    dan pastikan folder "data/" ada dan writable: chmod 700 data/
 */

// =====================================================
// (A) GOOGLE FORM — Server-side Relay
// =====================================================

// URL target pengiriman Google Form (jangan tampilkan di frontend!)
// Contoh: "https://docs.google.com/forms/d/e/1FAIpQLSe.../formResponse"
define('GOOGLE_FORM_ACTION_URL', 'https://docs.google.com/forms/d/e/1FAIpQLSeWDypFf7xieTOgiX3G1QrlI0eIeb8c2W5c2GtZ4zwab0TEXQ/formResponse');

// Mapping field form → entry ID Google Form
// Untuk menemukan entry ID: Buka form di browser → Klik kanan → View Page Source
// Cari pattern: name="entry.XXXXXXXXX"
define('FIELD_NAMA',      'entry.123456789');   // ← ganti dengan entry ID nama lengkap
define('FIELD_INSTANSI',  'entry.234567890');   // ← ganti dengan entry ID instansi
define('FIELD_KEPERLUAN', 'entry.345678901');   // ← ganti dengan entry ID keperluan
define('FIELD_BAGIAN',    'entry.456789012');   // ← ganti dengan entry ID tujuan bagian
define('FIELD_PEGAWAI',   'entry.567890123');   // ← ganti dengan entry ID pegawai dituju
define('FIELD_PESAN',     'entry.678901234');   // ← ganti dengan entry ID pesan/keterangan
define('FIELD_NO_HP',     'entry.789012345');   // ← ganti dengan entry ID nomor HP (jika ada)

// =====================================================
// (B) PENYIMPANAN LOKAL (BACKUP OPSIONAL)
// =====================================================

// Aktifkan penyimpanan data ke file JSON lokal sebagai backup
define('SIMPAN_KE_FILE', true);

// Path folder penyimpanan data (di luar document root lebih aman)
// Contoh di luar public: '/var/www/data-buku-tamu/' 
// Contoh di dalam project (perlu proteksi .htaccess):
define('DATA_DIR', __DIR__ . '/data/');
define('DATA_FILE', DATA_DIR . 'tamu.json');

// =====================================================
// (C) PENGATURAN KEAMANAN
// =====================================================

// Daftar domain yang diizinkan (CORS — isi dengan domain Anda)
define('ALLOWED_ORIGIN', '*'); // Ganti dengan: 'https://namadomain.go.id'

// Batas ukuran input (karakter)
define('MAX_NAMA',    100);
define('MAX_INSTANSI',150);
define('MAX_PESAN',   1000);
define('MAX_NOHP',    20);

// =====================================================
// (D) GOOGLE SHEETS (untuk baca data statistik & tamu)
// =====================================================
define('SHEET_ID',        '1JWORujkSyuUmfFuckqVBHnO77KUj4PWB4ljDHvoB_PY');
define('SHEET_DATA_TAMU', 'Form Responses 1');
define('SHEET_STATISTIK', 'Statistik');
