# SecureVault — Advanced Cryptographic Information System

PHP 8.2+ Native · MySQL 8 · Web Crypto API · Tailwind (CDN). Login tanpa password: hanya file private key (.pem).

## Instalasi (Windows / Laragon)
1. Letakkan folder di `C:\laragon\www\securevault`, aktifkan Apache + MySQL, pastikan ekstensi `openssl` & `pdo_mysql` aktif.
2. Import database: `mysql -u root < database\schema.sql` (atau lewat phpMyAdmin).
3. Edit `.env` (DB_*, dan **ganti `SETUP_TOKEN`** dengan string acak).
4. Arahkan DocumentRoot ke `public/` (Laragon: Menu → Apache → sites, atau buka `http://securevault.test` bila auto-virtual-host aktif).
   Alternatif cepat: `php -S 127.0.0.1:8000 -t public public/index.php`
5. Buka `/setup`, masukkan SETUP_TOKEN, klik Generate. **Unduh kedua private key** (`auth_private_key.pem`, `enc_private_key.pem`). Tidak ada salinan di server; kehilangan `enc_private_key.pem` = data tidak bisa dipulihkan.
6. Buka `/login`, pilih `auth_private_key.pem`. Kunci lain (valid tetapi tak terdaftar, atau kunci enkripsi) → dashboard decoy.

## Enkripsi database & tampilan terenkripsi (v2)
- **Kolom database terenkripsi** (AES-256-GCM, kunci diturunkan HKDF dari `APP_KEY` di `.env`): tipe konten, seluruh isi `authentication_logs`, `security_logs`, `decoy_logs`, dan `system_settings`. Judul/isi/kunci AES rekaman sudah terenkripsi di browser.
- Kolom `*_idx` = HMAC-SHA256 (blind index) agar filter tipe, pembatasan login per IP, dan statistik dasbor tetap jalan tanpa menyimpan nilai asli.
- Tetap plaintext (sengaja): public key + fingerprint (publik, dipakai mencari kunci login), `users.role/status`, ID, dan timestamp.
- **Key salah → semua ciphertext:** sesi decoy (dasbor, Konten Aman, menu, judul halaman) menampilkan teks acak sepanjang aslinya setiap render; di Konten Aman, rekaman yang kuncinya belum dimuat/salah hanya menampilkan ciphertext tanpa tombol Edit/Hapus.
- **Upgrade dari versi lama:** isi `APP_KEY` di `.env` (`php -r "echo 'base64:'.base64_encode(random_bytes(32));"`), lalu `php scripts/migrate_v2.php`. Instalasi baru: cukup import `schema.sql`. Simpan cadangan `APP_KEY`; tanpa itu tipe konten dan log tidak bisa dibaca.

## Arsitektur kripto
| Fungsi | Algoritma | Lokasi |
|---|---|---|
| Enkripsi konten | AES-256-GCM, kunci acak + nonce 96-bit per rekaman | browser (`public/js/sv-crypto.js`) |
| Pembungkus kunci AES | RSA-OAEP SHA-256, RSA-4096 | browser |
| Login challenge-response | RSA-PSS SHA-256, salt 32 byte, pesan `SecureVault-Auth-v1:`+challenge | browser menandatangani, PHP memverifikasi (`SignatureService`) |
| Fingerprint | SHA-256 atas DER SPKI | browser & PHP (identik) |

Catatan desain: operasi OAEP-SHA256 dilakukan di browser karena `openssl_public_encrypt` PHP hanya OAEP-SHA1; PHP tidak punya RSA-PSS bawaan, sehingga verifikasinya ditulis manual (RFC 8017) di atas operasi RSA mentah OpenSSL. Server tidak pernah melihat plaintext maupun private key; konsekuensinya pencarian isi dilakukan di browser setelah dekripsi.

## Pengujian yang sudah dijalankan (di luar PHP)
- `node tests/crypto_compat.mjs /tmp/svtest` — kunci RSA-4096, tanda tangan PSS, roundtrip hybrid, deteksi manipulasi ciphertext.
- `openssl dgst -sha256 -sigopt rsa_padding_mode:pss -sigopt rsa_pss_saltlen:32 -sigopt rsa_mgf1_md:sha256 -verify /tmp/svtest/auth_pub.pem -signature /tmp/svtest/sig.bin /tmp/svtest/msg.bin`
- `python3 tests/pss_reference.py /tmp/svtest` — port logika `SignatureService` + kecocokan fingerprint.

## BELUM diuji / belum ada
Perubahan v2 (Vault, migrasi, view decoy teracak) **belum dijalankan** (lingkungan pembuatan tanpa PHP/MySQL); hanya sintaks JS dan format enkripsi yang diuji. Perlu uji manual: setup → login benar → login salah (decoy) → CRUD → akses API dari sesi decoy (harus 403) → replay (kirim ulang request verify harus gagal).
Belum diimplementasikan: upload/enkripsi file (`secure_files` sudah ada di skema), rotasi/revokasi kunci via UI, halaman Encryption Center/Signature/Settings/Logs terpisah, penambahan administrator lain, laporan akademik (BAB I–VI), dan aset visual (screenshot/diagram).
Catatan keamanan: CSP memakai `'unsafe-inline'` dan CDN untuk kemudahan pengembangan; ketatkan (hosting lokal + nonce) untuk produksi. Angka "Decryption Ops" dilaporkan oleh klien.
