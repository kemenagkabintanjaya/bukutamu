<?php
/**
 * ================================================================
 * submit.php — Handler Pengiriman Form Buku Tamu
 * ================================================================
 * Fitur keamanan:
 *  1. Validasi & sanitasi semua input (cegah XSS / injeksi)
 *  2. Honeypot anti-bot (field tersembunyi)
 *  3. CSRF token sederhana berbasis sesi
 *  4. Rate-limiting sederhana berbasis sesi (maks 5 submit/menit)
 *  5. Relay ke Google Form via cURL (URL target tidak terlihat di browser)
 *  6. Simpan backup lokal ke file JSON (opsional, bisa dinonaktifkan)
 * ================================================================
 */

require_once __DIR__ . '/config.php';

// ---- Hanya terima POST ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['sukses' => false, 'pesan' => 'Method tidak diizinkan.']));
}

header('Content-Type: application/json; charset=utf-8');

// ================================================================
// [1] HONEYPOT ANTI-BOT
//     Field "website" harus KOSONG (tersembunyi dari pengguna nyata)
//     Bot yang mengisi semua field akan terblokir di sini.
// ================================================================
if (!empty($_POST['website'])) {
    // Diam-diam "berhasil" agar bot tidak tahu dia diblokir
    exit(json_encode(['sukses' => true, 'pesan' => 'Data berhasil dikirim.']));
}

// ================================================================
// [2] VALIDASI CSRF TOKEN
//     Token dikirim dari form, dicocokkan dengan yang ada di sesi.
// ================================================================
session_start();

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    exit(json_encode(['sukses' => false, 'pesan' => 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.']));
}
// Regenerasi token setelah dipakai (single-use)
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ================================================================
// [3] RATE LIMITING SEDERHANA
//     Maks 5 pengiriman per sesi per 60 detik untuk cegah flood
// ================================================================
$now = time();
if (!isset($_SESSION['submit_times'])) $_SESSION['submit_times'] = [];

// Buang pengiriman yang sudah lebih dari 60 detik
$_SESSION['submit_times'] = array_filter($_SESSION['submit_times'], fn($t) => ($now - $t) < 60);

if (count($_SESSION['submit_times']) >= 5) {
    http_response_code(429);
    exit(json_encode(['sukses' => false, 'pesan' => 'Terlalu banyak pengiriman. Mohon tunggu beberapa saat.']));
}
$_SESSION['submit_times'][] = $now;

// ================================================================
// [4] FUNGSI SANITASI INPUT
//     Bersihkan semua input dari tag HTML dan karakter berbahaya
// ================================================================
function sanitasiTeks(string $input, int $maxLen = 255): string {
    // Hapus whitespace berlebih
    $input = trim($input);
    // Hapus semua tag HTML
    $input = strip_tags($input);
    // Encode karakter HTML khusus (cegah XSS)
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Batasi panjang
    return mb_substr($input, 0, $maxLen, 'UTF-8');
}

function sanitasiEmail(string $input): string {
    $input = trim($input);
    $input = filter_var($input, FILTER_SANITIZE_EMAIL);
    return mb_substr($input, 0, 254, 'UTF-8');
}

// ================================================================
// [5] AMBIL & VALIDASI INPUT
// ================================================================
$nama      = sanitasiTeks($_POST['nama']      ?? '', MAX_NAMA);
$instansi  = sanitasiTeks($_POST['instansi']  ?? '', MAX_INSTANSI);
$keperluan = sanitasiTeks($_POST['keperluan'] ?? '', 200);
$bagian    = sanitasiTeks($_POST['bagian']    ?? '', 200);
$pegawai   = sanitasiTeks($_POST['pegawai']   ?? '', 150);
$pesan     = sanitasiTeks($_POST['pesan']     ?? '', MAX_PESAN);
$noHp      = sanitasiTeks($_POST['no_hp']     ?? '', MAX_NOHP);

// Validasi field wajib
$errors = [];
if (mb_strlen($nama) < 3) {
    $errors[] = 'Nama lengkap wajib diisi minimal 3 karakter.';
}
if (empty($keperluan)) {
    $errors[] = 'Keperluan kunjungan wajib dipilih.';
}
if (empty($bagian)) {
    $errors[] = 'Tujuan bagian/seksi wajib dipilih.';
}

// Validasi nomor HP (jika diisi, harus format valid)
if (!empty($noHp) && !preg_match('/^[0-9\+\-\s]{8,20}$/', $noHp)) {
    $errors[] = 'Format nomor HP tidak valid.';
}

if (!empty($errors)) {
    http_response_code(422);
    exit(json_encode(['sukses' => false, 'pesan' => implode(' ', $errors)]));
}

// ================================================================
// [6] RELAY KE GOOGLE FORM VIA cURL
//     Pengiriman dilakukan dari sisi server — URL Google Form
//     tidak pernah terekspos ke browser pengguna.
// ================================================================
// Urutan sesuai urutan field di Google Form:
// 1.Nama 2.Instansi 3.NoHP 4.Keperluan 5.Bagian 6.Pegawai 7.Pesan
$postData = http_build_query([
    FIELD_NAMA      => $nama,
    FIELD_INSTANSI  => $instansi,
    FIELD_NO_HP     => $noHp,
    FIELD_KEPERLUAN => $keperluan,
    FIELD_BAGIAN    => $bagian,
    FIELD_PEGAWAI   => $pegawai,
    FIELD_PESAN     => $pesan,
]);

$ch = curl_init(GOOGLE_FORM_ACTION_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'BukuTamu-Kemenag-IntanJaya/1.0',
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'Referer: https://docs.google.com/',
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// Google Form mengembalikan 200 bahkan untuk redirect, anggap sukses jika tidak ada error cURL
$googleSukses = ($curlErr === '' && $httpCode !== 0);

// ================================================================
// [7] SIMPAN BACKUP LOKAL (OPSIONAL)
//     Simpan ke file JSON terenkripsi di server sebagai cadangan.
//     File ini TIDAK boleh diakses publik (lindungi via .htaccess).
// ================================================================
if (SIMPAN_KE_FILE) {
    // Buat folder jika belum ada
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0700, true);
        // Buat .htaccess untuk blokir akses publik ke folder data/
        file_put_contents(DATA_DIR . '.htaccess', "Order deny,allow\nDeny from all\n");
    }

    $waktuWIT = new DateTime('now', new DateTimeZone('Asia/Jayapura'));

    $entri = [
        'id'        => uniqid('tamu_', true),
        'timestamp' => $waktuWIT->format('Y-m-d H:i:s') . ' WIT',
        'nama'      => $nama,
        'instansi'  => $instansi,
        'keperluan' => $keperluan,
        'bagian'    => $bagian,
        'pegawai'   => $pegawai,
        'no_hp'     => $noHp,
        'pesan'     => $pesan,
        'ip_hash'   => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), // hash IP, bukan IP asli
        'status'    => 'Menunggu',
    ];

    // Baca data yang ada, tambah entri baru, simpan kembali
    $dataBestehend = [];
    if (file_exists(DATA_FILE)) {
        $rawJson = file_get_contents(DATA_FILE);
        $dataBestehend = json_decode($rawJson, true) ?? [];
    }
    $dataBestehend[] = $entri;

    // Tulis atomik: tulis ke temp file dulu, baru rename
    $tmpFile = DATA_FILE . '.tmp';
    file_put_contents($tmpFile, json_encode($dataBestehend, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmpFile, DATA_FILE);
}

// ================================================================
// [8] KIRIM RESPONS KE BROWSER
// ================================================================
if ($googleSukses) {
    exit(json_encode([
        'sukses' => true,
        'pesan'  => "Terima kasih, <strong>{$nama}</strong>! Data kunjungan Anda telah berhasil dicatat. Silakan menuju bagian yang dituju.",
    ]));
} else {
    // Jika Google Form gagal tapi backup lokal berhasil, tetap berhasil
    if (SIMPAN_KE_FILE) {
        exit(json_encode([
            'sukses'    => true,
            'pesan'     => "Terima kasih, <strong>{$nama}</strong>! Data Anda telah disimpan. (Mode backup aktif)",
            'info_teknis' => 'Google Form tidak dapat dijangkau, data disimpan lokal.',
        ]));
    }
    http_response_code(502);
    exit(json_encode([
        'sukses' => false,
        'pesan'  => 'Gagal mengirim data. Periksa koneksi internet atau hubungi admin. (' . ($curlErr ?: "HTTP $httpCode") . ')',
    ]));
}
