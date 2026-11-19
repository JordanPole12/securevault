<?php
declare(strict_types=1);
namespace App\Controllers;

use App\{DB, Http, Log, Vault};
use App\Services\{KeyManagementService, DecoyService};

final class AdminController
{
    public static function dashboard(): void
    {
        if (($_SESSION['mode'] ?? '') === 'decoy') {
            Log::decoy(null, 'decoy_view', 'served');
            Http::view('decoy', ['records' => DecoyService::records(), 'stats' => DecoyService::stats()]);
        }
        if (($_SESSION['mode'] ?? '') !== 'real') { header('Location: /login'); exit; }
        $uid = (int)$_SESSION['user_id'];
        $q = fn(string $sql, array $p = []) => (int)(DB::one($sql, $p)['c'] ?? 0);
        $stats = [
            'records' => $q('SELECT COUNT(*) c FROM secure_contents WHERE user_id=?', [$uid]),
            'enc'     => $q('SELECT COUNT(*) c FROM security_logs WHERE action_idx=?', [Vault::idx('encrypt')]),
            'dec'     => $q('SELECT COUNT(*) c FROM security_logs WHERE action_idx=?', [Vault::idx('decrypt')]),
            'ok'      => $q('SELECT COUNT(*) c FROM authentication_logs WHERE status_idx=?', [Vault::idx('success')]),
            'fail'    => $q('SELECT COUNT(*) c FROM authentication_logs WHERE status_idx=?', [Vault::idx('failure')]),
        ];
        // Kolom action terenkripsi: kelompokkan lewat blind index, lalu petakan kembali ke nama aksi.
        $ie = Vault::idx('encrypt'); $id = Vault::idx('decrypt');
        $chart = [];
        foreach (DB::all('SELECT DATE(created_at) d, action_idx a, COUNT(*) c FROM security_logs WHERE action_idx IN (?,?) AND created_at > (NOW() - INTERVAL 7 DAY) GROUP BY d, action_idx ORDER BY d', [$ie, $id]) as $r) {
            $chart[] = ['d' => $r['d'], 'action' => $r['a'] === $ie ? 'encrypt' : 'decrypt', 'c' => $r['c']];
        }
        $keys = DB::all('SELECT key_purpose, key_fingerprint, key_status, created_at FROM user_keys WHERE user_id=? ORDER BY id', [$uid]);
        $logs = array_map(fn($r) => [
            'action' => Vault::open($r['action_enc']) ?? '?', 'resource_type' => Vault::open($r['resource_type_enc']) ?? '',
            'status' => Vault::open($r['status_enc']) ?? '?', 'created_at' => $r['created_at'],
        ], DB::all('SELECT action_enc, resource_type_enc, status_enc, created_at FROM security_logs ORDER BY id DESC LIMIT 10'));
        $last = DB::one('SELECT created_at c FROM authentication_logs WHERE status_idx=? ORDER BY id DESC LIMIT 1', [Vault::idx('success')])['c'] ?? '-';
        Http::view('dashboard', compact('stats', 'chart', 'keys', 'logs', 'last'));
    }

    public static function contentsPage(): void
    {
        $mode = $_SESSION['mode'] ?? '';
        if ($mode === 'decoy') {
            Log::decoy(null, 'decoy_contents', 'served');
            Http::view('decoy_contents', ['records' => DecoyService::records(8)]);
        }
        if ($mode !== 'real') { header('Location: /login'); exit; }
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
        $sql  = 'SELECT id, content_type_enc, encrypted_title, encrypted_content, encrypted_aes_key, nonce, authentication_tag, created_at, updated_at FROM secure_contents WHERE user_id=?';
        $p = [$uid];
        if (in_array($type, $cfg['content_types'], true)) { $sql .= ' AND type_idx=?'; $p[] = Vault::idx($type); }
        $items = array_map(function ($r) {
            $r['type_cipher']  = $r['content_type_enc'];                 // bentuk terenkripsi: ditampilkan bila kunci belum/ salah
            $r['content_type'] = Vault::open($r['content_type_enc']) ?? '';
            unset($r['content_type_enc']);
            return $r;
        }, DB::all($sql . ' ORDER BY id DESC', $p));
        Http::json(['items' => $items]);
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
        $id = DB::run('INSERT INTO secure_contents (user_id,key_id,content_type_enc,type_idx,encrypted_title,encrypted_content,encrypted_aes_key,nonce,authentication_tag) VALUES (?,?,?,?,?,?,?,?,?)',
            [$uid, $key['id'], Vault::seal($in['content_type']), Vault::idx($in['content_type']), $in['encrypted_title'], $in['encrypted_content'], $in['encrypted_aes_key'], $in['nonce'], $in['authentication_tag']]);
        Log::sec($uid, 'encrypt', 'content', $id, 'success');
        Http::json(['id' => $id]);
    }

    public static function update(int $id): void
    {
        $uid = Http::real(); Http::csrf();
        $in  = self::payload();
        $key = KeyManagementService::activeEncryptionKey($uid);
        $n = DB::pdo()->prepare('UPDATE secure_contents SET key_id=?, content_type_enc=?, type_idx=?, encrypted_title=?, encrypted_content=?, encrypted_aes_key=?, nonce=?, authentication_tag=? WHERE id=? AND user_id=?');
        $n->execute([$key['id'], Vault::seal($in['content_type']), Vault::idx($in['content_type']), $in['encrypted_title'], $in['encrypted_content'], $in['encrypted_aes_key'], $in['nonce'], $in['authentication_tag'], $id, $uid]);
        if ($n->rowCount() === 0) Http::json(['error' => 'Data tidak ditemukan'], 404);
        Log::sec($uid, 'encrypt', 'content', $id, 'success');
        Http::json(['ok' => true]);
    }

    public static function delete(int $id): void
    {
        $uid = Http::real(); Http::csrf();
        $n = DB::pdo()->prepare('DELETE FROM secure_contents WHERE id=? AND user_id=?');
        $n->execute([$id, $uid]);
        Log::sec($uid, 'delete', 'content', $id, 'success');
        Http::json(['ok' => $n->rowCount() > 0]);
    }

    /** Dekripsi terjadi di browser; klien melaporkan agar tercatat di audit (angka ini bersumber dari klien). */
    public static function logDecrypt(): void
    {
        $uid = Http::real(); Http::csrf();
        $id = (int)(Http::body()['id'] ?? 0);
        Log::sec($uid, 'decrypt', 'content', $id ?: null, 'success');
        Http::json(['ok' => true]);
    }
}
