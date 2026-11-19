<?php
declare(strict_types=1);
namespace App\Controllers;

use App\{DB, Http, Log, Session, Vault};
use App\Services\{KeyManagementService, SignatureService};

final class AuthController
{
    private static function cfg(): array { return require dirname(__DIR__, 2) . '/config/app.php'; }

    private static function tooManyFailures(): bool
    {
        $c = self::cfg();
        $n = (int)(DB::one("SELECT COUNT(*) c FROM authentication_logs WHERE ip_idx=? AND status_idx=? AND created_at > (NOW() - INTERVAL ? SECOND)",
            [Vault::idx(Http::ip()), Vault::idx('failure'), $c['fail_window']])['c'] ?? 0);
        return $n >= $c['max_failures'];
    }

    public static function challenge(): void
    {
        if (self::tooManyFailures()) Http::json(['error' => 'Terlalu banyak percobaan. Coba lagi nanti.'], 429);
        $ch = base64_encode(random_bytes(32));
        $_SESSION['challenge'] = ['v' => $ch, 'exp' => time() + self::cfg()['challenge_ttl']];
        Http::json(['challenge' => $ch]);
    }

    public static function verify(): void
    {
        Http::csrf();
        if (self::tooManyFailures()) Http::json(['error' => 'Terlalu banyak percobaan. Coba lagi nanti.'], 429);

        $ch = $_SESSION['challenge'] ?? null;
        unset($_SESSION['challenge']);                       // sekali pakai (replay protection)
        if (!$ch || $ch['exp'] < time()) Http::json(['error' => 'Challenge kedaluwarsa. Ulangi.'], 400);

        $in  = Http::body();
        $fp  = strtolower((string)($in['fingerprint'] ?? ''));
        $sig = (string)($in['signature'] ?? '');
        if (!preg_match('/^[0-9a-f]{64}$/', $fp) || !preg_match('#^[A-Za-z0-9+/]{300,1000}={0,2}$#', $sig)) {
            Http::json(['error' => 'Permintaan tidak valid.'], 400);
        }
        $sigRaw = base64_decode($sig, true);
        $msg    = 'SecureVault-Auth-v1:' . base64_decode($ch['v'], true);
        $key    = KeyManagementService::findActiveAuth($fp);
        $ok     = $key && $sigRaw !== false && SignatureService::verifyPss($key['public_key'], $msg, $sigRaw);

        if ($ok) {
            Session::reset(['mode' => 'real', 'user_id' => (int)$key['user_id'], 'key_id' => (int)$key['id']]);
            Log::auth($fp, 'login', 'success');
            Log::sec((int)$key['user_id'], 'login', null, null, 'success');
        } else {
            Session::reset(['mode' => 'decoy']);             // sesi terpisah, tanpa user_id
            Log::auth($fp, 'login', 'failure');
            Log::decoy($fp, 'decoy_session', 'issued');
        }
        // Respons identik untuk kedua kasus: klien tidak diberi tahu mana yang berhasil.
        Http::json(['redirect' => '/dashboard']);
    }

    public static function logout(): void
    {
        Http::csrf();
        $_SESSION = [];
        session_destroy();
        Http::json(['redirect' => '/']);
    }

    public static function setup(): void
    {
        Http::csrf();
        $in = Http::body();
        $tok = \App\Env::get('SETUP_TOKEN');
        if ($tok === '' || !hash_equals($tok, (string)($in['token'] ?? ''))) Http::json(['error' => 'Token setup salah.'], 403);
        try {
            $r = KeyManagementService::registerFirstAdmin((string)($in['auth_public'] ?? ''), (string)($in['enc_public'] ?? ''));
            Http::json(['ok' => true, 'fingerprints' => $r]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Http::json(['error' => $e->getMessage()], 400);
        }
    }
}
