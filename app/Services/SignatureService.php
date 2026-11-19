<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Verifikasi RSASSA-PSS (SHA-256, MGF1-SHA256, salt 32 byte) sesuai RFC 8017 §9.1.2.
 * openssl_verify() PHP tidak mendukung PSS, jadi operasi RSA mentah dilakukan oleh OpenSSL
 * (openssl_public_decrypt + NO_PADDING) dan encoding EMSA-PSS diverifikasi di sini.
 */
final class SignatureService
{
    private const HLEN = 32;
    private const SLEN = 32;

    public static function verifyPss(string $publicKeyPem, string $message, string $signature): bool
    {
        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) return false;
        $d = openssl_pkey_get_details($key);
        if (!$d || $d['type'] !== OPENSSL_KEYTYPE_RSA) return false;

        $modBits = (int)$d['bits'];
        $k = intdiv($modBits + 7, 8);
        if (strlen($signature) !== $k) return false;

        $em = '';
        if (!@openssl_public_decrypt($signature, $em, $key, OPENSSL_NO_PADDING)) return false;

        $emBits = $modBits - 1;
        $emLen  = intdiv($emBits + 7, 8);
        if (strlen($em) > $emLen) {
            if (trim(substr($em, 0, strlen($em) - $emLen), "\0") !== '') return false;
            $em = substr($em, -$emLen);
        } elseif (strlen($em) < $emLen) {
            $em = str_pad($em, $emLen, "\0", STR_PAD_LEFT);
        }

        if ($emLen < self::HLEN + self::SLEN + 2) return false;
        if (ord($em[$emLen - 1]) !== 0xBC) return false;

        $dbLen    = $emLen - self::HLEN - 1;
        $maskedDB = substr($em, 0, $dbLen);
        $H        = substr($em, $dbLen, self::HLEN);

        $unused = 8 * $emLen - $emBits;                 // bit teratas yang wajib 0
        if ($unused > 0 && (ord($maskedDB[0]) & ((0xFF << (8 - $unused)) & 0xFF)) !== 0) return false;

        $db = $maskedDB ^ self::mgf1($H, $dbLen);
        if ($unused > 0) $db[0] = chr(ord($db[0]) & (0xFF >> $unused));

        $psLen = $emLen - self::HLEN - self::SLEN - 2;
        if (substr($db, 0, $psLen) !== str_repeat("\0", $psLen)) return false;
        if (ord($db[$psLen]) !== 0x01) return false;

        $salt   = substr($db, -self::SLEN);
        $mHash  = hash('sha256', $message, true);
        $hPrime = hash('sha256', str_repeat("\0", 8) . $mHash . $salt, true);
        return hash_equals($H, $hPrime);
    }

    private static function mgf1(string $seed, int $len): string
    {
        $out = '';
        for ($c = 0; strlen($out) < $len; $c++) {
            $out .= hash('sha256', $seed . pack('N', $c), true);
        }
        return substr($out, 0, $len);
    }
}
