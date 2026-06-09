# 📋 Panduan Setup — Buku Tamu Digital PHP
**Kemenag Kabupaten Intan Jaya, Papua Pegunungan**

---

## 📁 Struktur File

```
buku-tamu-php/
├── index.php       ← Halaman utama (tampilan identik dengan asli)
├── submit.php      ← Handler pengiriman form (server-side, aman)
├── config.php      ← Konfigurasi URL & field Google Form
├── .htaccess       ← Keamanan server Apache
├── data/           ← (dibuat otomatis) Folder penyimpanan backup lokal
│   ├── .htaccess   ← (dibuat otomatis) Blokir akses publik ke folder data
│   └── tamu.json   ← (dibuat otomatis) Data backup lokal
└── README.md       ← File ini
```

---

## ⚙️ Langkah Setup

### 1. Cari Entry ID Google Form

Entry ID diperlukan agar data dari form PHP bisa dikirim ke Google Form dengan benar.

**Cara mendapatkan Entry ID:**
1. Buka Google Form Anda di browser
2. Klik kanan pada halaman → **Inspect Element** (atau tekan F12)
3. Buka tab **Network** → isi form dengan data dummy → klik Submit
4. Di tab Network, cari request `formResponse` → lihat **Payload**
5. Anda akan melihat data seperti: `entry.1234567890=Nilai`
6. Salin setiap `entry.XXXXXXXXX` ke bagian `FIELD_*` di `config.php`

**Atau cara alternatif (lebih mudah):**
1. Buka Google Form → klik kanan → **View Page Source**
2. Tekan Ctrl+F → cari `entry.`
3. Setiap field input akan memiliki atribut `name="entry.XXXXXXXXX"`

### 2. Edit `config.php`

```php
// Ganti URL ini dengan URL action Google Form Anda:
define('GOOGLE_FORM_ACTION_URL', 'https://docs.google.com/forms/d/e/YOUR_FORM_ID/formResponse');

// Ganti entry ID sesuai hasil langkah 1:
define('FIELD_NAMA',      'entry.1234567890');
define('FIELD_INSTANSI',  'entry.2345678901');
// dst...
```

### 3. Upload ke Server

Upload semua file ke server PHP (hosting/VPS):
- Minimal PHP 7.4+ dengan ekstensi `curl` aktif
- Apache dengan `mod_rewrite` aktif (untuk `.htaccess`)
- Jika menggunakan Nginx, konversi `.htaccess` ke konfigurasi Nginx

### 4. Beri Izin Folder data/

```bash
# Setelah upload, beri izin write ke folder data/
chmod 700 data/
# Atau buat manualnya:
mkdir data && chmod 700 data
```

### 5. Keamanan Tambahan (Sangat Disarankan)

Pindahkan `config.php` ke luar document root:

```
/var/www/               ← document root
    html/
        index.php       ← file yang bisa diakses publik
        submit.php
        .htaccess
/var/www/config/        ← DI LUAR document root (tidak bisa diakses browser)
    config.php          ← aman!
```

Lalu ubah baris `require_once` di `index.php` dan `submit.php`:
```php
require_once '/var/www/config/config.php';
```

---

## 🔒 Fitur Keamanan yang Sudah Diterapkan

| Fitur | Deskripsi |
|-------|-----------|
| **Server-side Relay** | URL Google Form tidak pernah terlihat di browser pengguna |
| **Sanitasi Input** | `strip_tags()` + `htmlspecialchars()` di setiap field |
| **Validasi Input** | Panjang max, format nomor HP, field wajib |
| **Honeypot Anti-Bot** | Field tersembunyi jebak bot spam |
| **CSRF Token** | Token sesi sekali pakai untuk cegah Cross-Site Request Forgery |
| **Rate Limiting** | Maks 5 submit per menit per sesi |
| **HTTP Security Headers** | X-Frame-Options, CSP, X-XSS-Protection via .htaccess |
| **Backup Lokal** | Data disimpan ke JSON lokal yang diproteksi .htaccess |
| **IP Hashing** | IP pengguna di-hash (SHA-256) sebelum disimpan, bukan IP asli |

---

## 🧪 Testing

Setelah setup, uji sistem:

1. **Test normal:** Isi form dengan data lengkap → Submit → Cek Google Sheets
2. **Test honeypot:** Isi field tersembunyi "website" secara manual (via DevTools) → Harus "berhasil" tapi tidak masuk ke Sheets
3. **Test CSRF:** Hapus cookie sesi → Submit → Harus muncul error token
4. **Test validasi:** Kirim nama 1 karakter → Harus muncul pesan error

---

## ❓ Troubleshooting

**"cURL tidak tersedia"**
→ Aktifkan ekstensi cURL di PHP: `extension=curl` di `php.ini`

**"Token keamanan tidak valid"**
→ Pastikan sesi PHP aktif. Cek `session_start()` tidak terblokir oleh konfigurasi server.

**Data tidak masuk ke Google Sheets**
→ Pastikan Entry ID benar (lihat Langkah 1). Cek log error PHP untuk detail.

**Folder data/ tidak bisa ditulis**
→ Jalankan: `chmod 700 data/` dan pastikan user web server memiliki akses tulis.
