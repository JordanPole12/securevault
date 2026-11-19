// Jalankan: node tests/crypto_compat.mjs <outdir>
// Membuat kunci via sv-crypto.js, menandatangani challenge, lalu menulis file untuk diverifikasi OpenSSL & referensi PSS.
import fs from 'node:fs'; import vm from 'node:vm';
const out = process.argv[2] || '/tmp/svtest'; fs.mkdirSync(out, { recursive: true });
vm.runInThisContext(fs.readFileSync(new URL('../public/js/sv-crypto.js', import.meta.url), 'utf8'));
const SV = globalThis.SV;
const auth = await SV.generatePair('auth'), enc = await SV.generatePair('enc');
const challenge = SV.b64(crypto.getRandomValues(new Uint8Array(32)));
const { fingerprint, signature } = await SV.signChallenge(auth.privatePem, challenge);
const msg = Buffer.concat([Buffer.from(SV.AUTH_CONTEXT), Buffer.from(challenge, 'base64')]);
fs.writeFileSync(`${out}/auth_pub.pem`, auth.publicPem); fs.writeFileSync(`${out}/msg.bin`, msg);
fs.writeFileSync(`${out}/sig.bin`, Buffer.from(signature, 'base64')); fs.writeFileSync(`${out}/fp.txt`, fingerprint);
// Hybrid roundtrip
const rec = await SV.encryptRecord(enc.publicPem, 'Judul Rahasia', 'Isi rahasia ✓ 123');
const dec = await SV.decryptRecord(enc.privatePem, rec);
console.log('fingerprint(browser):', fingerprint);
console.log('hybrid roundtrip:', dec.title === 'Judul Rahasia' && dec.content === 'Isi rahasia ✓ 123' ? 'OK' : 'GAGAL');
// Tamper test: ubah 1 byte ciphertext -> harus gagal
const bad = { ...rec, encrypted_content: SV.b64(Uint8Array.from(SV.unb64(rec.encrypted_content), (v, i) => i === 0 ? v ^ 1 : v)) };
try { await SV.decryptRecord(enc.privatePem, bad); console.log('tamper: GAGAL (tidak terdeteksi)'); } catch { console.log('tamper terdeteksi: OK'); }
// Kunci enkripsi (meski bisa diimpor) menghasilkan fingerprint yang tidak terdaftar sebagai auth -> server akan memberi decoy
const efp = await SV.authFingerprint(enc.privatePem);
console.log('fingerprint kunci enkripsi berbeda dari auth:', efp !== fingerprint ? 'OK' : 'GAGAL');
