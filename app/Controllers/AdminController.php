<?php
declare(strict_types=1);
namespace App\Controllers;

use App\{DB, Http};
use App\Services\{KeyManagementService, DecoyService};

final class AdminController
{
    public static function dashboard(): void
    {
        if (($_SESSION['mode'] ?? '') === 'decoy') {
            DB::run("INSERT INTO decoy_logs (attempted_key_fingerprint,event_type,status) VALUES (NULL,'decoy_view','served')");
            Http::view('decoy', ['records' => DecoyService::records(), 'stats' => DecoyService::stats()]);
        }
        if (($_SESSION['mode'] ?? '') !== 'real') { header('Location: /login'); exit; }
        $uid = (int)$_SESSION['user_id'];
        $q = fn(string $sql, array $p = []) => (int)(DB::one($sql, $p)['c'] ?? 0);
        $stats = [
            'records' => $q('SELECT COUNT(*) c FROM secure_contents WHERE user_id=?', [$uid]),
            'enc'     => $q("SELECT COUNT(*) c FROM security_logs WHERE action='encrypt'"),
            'dec'     => $q("SELECT COUNT(*) c FROM security_logs WHERE action='decrypt'"),
            'ok'      => $q("SELECT COUNT(*) c FROM authentication_logs WHERE status='success'"),
            'fail'    => $q("SELECT COUNT(*) c FROM authentication_logs WHERE status='failure'"),
        ];
        $chart = DB::all("SELECT DATE(created_at) d, action, COUNT(*) c FROM security_logs WHERE action IN ('encrypt','decrypt') AND created_at > (NOW() - INTERVAL 7 DAY) GROUP BY d, action ORDER BY d");
        $keys  = DB::all('SELECT key_purpose, key_fingerprint, key_status, created_at FROM user_keys WHERE user_id=? ORDER BY id', [$uid]);
        $logs  = DB::all('SELECT action, resource_type, status, created_at FROM security_logs ORDER BY id DESC LIMIT 10');
        $last  = DB::one("SELECT created_at c FROM authentication_logs WHERE status='success' ORDER BY id DESC LIMIT 1")['c'] ?? '-';
        Http::view('dashboard', compact('stats', 'chart', 'keys', 'logs', 'last'));
    }

    public static function contentsPage(): void
    {
        if (($_SESSION['mode'] ?? '') !== 'real') { header('Location: /dashboard'); exit; }
        Http::view('contents');
    }

    public static function encKey(): void
    {
        $k = KeyManagementService::activeEncryptionKey(Http::real());
        $k ? Http::json(['id' => (int)$k['id'], 'public_key' => $k['public_key']]) : Http::json(['error' => 'Tidak ada kunci enkripsi aktif'], 404);
    }

    public static function list(): void
    {
        $uid  = Http::real();
        $type = $_GET['type'] ?? '';
        $cfg  = require dirname(__DIR__, 2) . '/config/app.php';
        $sql  = 'SELECT id, content_type, encrypted_title, encrypted_content, encrypted_aes_key, nonce, authentication_tag, created_at, updated_at FROM secure_contents WHERE user_id=?';
        $p = [$uid];
        if (in_array($type, $cfg['content_types'], true)) { $sql .= ' AND content_type=?'; $p[] = $type; }
        Http::json(['items' => DB::all($sql . ' ORDER BY id DESC', $p)]);
    }

    private static function payload(): array
    {
        $in  = Http::body();
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        $b64 = '#^[A-Za-z0-9+/]+={0,2}$#';
        if (!in_array($in['content_type'] ?? '', $cfg['content_types'], true)) Http::json(['error' => 'Tipe konten tidak valid'], 400);
        foreach (['encrypted_aes_key', 'nonce', 'authentication_tag', 'encrypted_content'] as $f) {
            if (!is_string($in[$f] ?? null) || !preg_match($b64, $in[$f]) || strlen($in[$f]) > 4_000_000) Http::json(['error' => "Field $f tidak valid"], 400);
        }
        if (!is_string($in['encrypted_title'] ?? null) || !preg_match('#^[A-Za-z0-9+/=]+\.[A-Za-z0-9+/=]+\.[A-Za-z0-9+/=]+$#', $in['encrypted_title'])) Http::json(['error' => 'Judul tidak valid'], 400);
        return $in;
    }

    public static function create(): void
    {
        $uid = Http::real(); Http::csrf();
        $in  = self::payload();
        $key = KeyManagementService::activeEncryptionKey($uid);
        if (!$key) Http::json(['error' => 'Tidak ada kunci enkripsi aktif'], 400);
        $id = DB::run('INSERT INTO secure_contents (user_id,key_id,content_type,encrypted_title,encrypted_content,encrypted_aes_key,nonce,authentication_tag) VALUES (?,?,?,?,?,?,?,?)',
            [$uid, $key['id'], $in['content_type'], $in['encrypted_title'], $in['encrypted_content'], $in['encrypted_aes_key'], $in['nonce'], $in['authentication_tag']]);
        DB::run("INSERT INTO security_logs (user_id,action,resource_type,resource_id,status) VALUES (?, 'encrypt','content',?, 'success')", [$uid, $id]);
        Http::json(['id' => $id]);
    }

    public static function update(int $id): void
    {
        $uid = Http::real(); Http::csrf();
        $in  = self::payload();
        $key = KeyManagementService::activeEncryptionKey($uid);
        $n = DB::pdo()->prepare('UPDATE secure_contents SET key_id=?, content_type=?, encrypted_title=?, encrypted_content=?, encrypted_aes_key=?, nonce=?, authentication_tag=? WHERE id=? AND user_id=?');
        $n->execute([$key['id'], $in['content_type'], $in['encrypted_title'], $in['encrypted_content'], $in['encrypted_aes_key'], $in['nonce'], $in['authentication_tag'], $id, $uid]);
        if ($n->rowCount() === 0) Http::json(['error' => 'Data tidak ditemukan'], 404);
        DB::run("INSERT INTO security_logs (user_id,action,resource_type,resource_id,status) VALUES (?, 'encrypt','content',?, 'success')", [$uid, $id]);
        Http::json(['ok' => true]);
    }

    public static function delete(int $id): void
    {
        $uid = Http::real(); Http::csrf();
        $n = DB::pdo()->prepare('DELETE FROM secure_contents WHERE id=? AND user_id=?');
        $n->execute([$id, $uid]);
        DB::run("INSERT INTO security_logs (user_id,action,resource_type,resource_id,status) VALUES (?, 'delete','content',?, 'success')", [$uid, $id]);
        Http::json(['ok' => $n->rowCount() > 0]);
    }

    /** Dekripsi terjadi di browser; klien melaporkan agar tercatat di audit (angka ini bersumber dari klien). */
    public static function logDecrypt(): void
    {
        $uid = Http::real(); Http::csrf();
        $id = (int)(Http::body()['id'] ?? 0);
        DB::run("INSERT INTO security_logs (user_id,action,resource_type,resource_id,status) VALUES (?, 'decrypt','content',?, 'success')", [$uid, $id ?: null]);
        Http::json(['ok' => true]);
    }
}
