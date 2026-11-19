<?php
declare(strict_types=1);
namespace App\Services;

use App\DB;

final class KeyManagementService
{
    /** Fingerprint = SHA-256 dari DER SubjectPublicKeyInfo (sama dengan hitungan di browser). */
    public static function fingerprint(string $pem): ?string
    {
        if (!preg_match('/-----BEGIN PUBLIC KEY-----(.+?)-----END PUBLIC KEY-----/s', $pem, $m)) return null;
        $der = base64_decode(preg_replace('/\s+/', '', $m[1]), true);
        return $der === false ? null : hash('sha256', $der);
    }

    /** Validasi: RSA >= 4096 bit. Mengembalikan PEM ternormalisasi atau null. */
    public static function normalize(string $pem): ?string
    {
        $k = openssl_pkey_get_public($pem);
        if ($k === false) return null;
        $d = openssl_pkey_get_details($k);
        if (!$d || $d['type'] !== OPENSSL_KEYTYPE_RSA || $d['bits'] < 4096) return null;
        return $d['key'];
    }

    /** Registrasi pasangan pertama (authentication + encryption) untuk admin baru. */
    public static function registerFirstAdmin(string $authPem, string $encPem): array
    {
        $a = self::normalize($authPem);
        $e = self::normalize($encPem);
        if (!$a || !$e) throw new \InvalidArgumentException('Public key harus RSA minimal 4096 bit.');
        $fa = self::fingerprint($a);
        $fe = self::fingerprint($e);
        if (!$fa || !$fe || $fa === $fe) throw new \InvalidArgumentException('Kunci authentication dan encryption harus berbeda.');

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $n = (int)$pdo->query('SELECT COUNT(*) FROM users FOR UPDATE')->fetchColumn();
            if ($n > 0) throw new \RuntimeException('Setup sudah dilakukan.');
            $uid = DB::run("INSERT INTO users (role,status) VALUES ('admin','active')");
            DB::run("INSERT INTO user_keys (user_id,public_key,key_fingerprint,key_purpose) VALUES (?,?,?, 'authentication')", [$uid, $a, $fa]);
            DB::run("INSERT INTO user_keys (user_id,public_key,key_fingerprint,key_purpose) VALUES (?,?,?, 'encryption')", [$uid, $e, $fe]);
            $pdo->commit();
        } catch (\Throwable $t) { $pdo->rollBack(); throw $t; }
        return ['auth' => $fa, 'enc' => $fe];
    }

    public static function findActiveAuth(string $fp): ?array
    {
        return DB::one("SELECT * FROM user_keys WHERE key_fingerprint=? AND key_purpose='authentication' AND key_status='active'", [$fp]);
    }

    public static function activeEncryptionKey(int $userId): ?array
    {
        return DB::one("SELECT id, public_key, key_fingerprint FROM user_keys WHERE user_id=? AND key_purpose='encryption' AND key_status='active' ORDER BY id DESC LIMIT 1", [$userId]);
    }
}
