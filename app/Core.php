<?php
declare(strict_types=1);
namespace App;

final class Env {
  private static array $v = [];
  public static function load(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
      if ($l[0] === '#' || !str_contains($l, '=')) continue;
      [$k, $val] = explode('=', $l, 2);
      self::$v[trim($k)] = trim($val);
    }
  }
  public static function get(string $k, string $d = ''): string { return self::$v[$k] ?? $d; }
}

final class DB {
  private static ?\PDO $pdo = null;
  public static function pdo(): \PDO {
    if (!self::$pdo) {
      $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        Env::get('DB_HOST','127.0.0.1'), Env::get('DB_PORT','3306'), Env::get('DB_NAME','securevault'));
      self::$pdo = new \PDO($dsn, Env::get('DB_USER','root'), Env::get('DB_PASS'), [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
      ]);
    }
    return self::$pdo;
  }
  public static function all(string $sql, array $p = []): array { $s = self::pdo()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
  public static function one(string $sql, array $p = []): ?array { $r = self::all($sql, $p); return $r[0] ?? null; }
  public static function run(string $sql, array $p = []): int { $s = self::pdo()->prepare($sql); $s->execute($p); return (int)self::pdo()->lastInsertId(); }
}

final class Http {
  public static function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
  public static function json(array $d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_SLASHES);
    exit;
  }
  public static function body(): array {
    $r = json_decode((string)file_get_contents('php://input'), true);
    return is_array($r) ? $r : [];
  }
  public static function csrf(): void {
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) self::json(['error' => 'CSRF token tidak valid'], 419);
  }
  public static function real(): int {
    if (($_SESSION['mode'] ?? '') !== 'real' || empty($_SESSION['user_id'])) self::json(['error' => 'Tidak diizinkan'], 403);
    return (int)$_SESSION['user_id'];
  }
  public static function view(string $name, array $data = []): never {
    extract($data, EXTR_SKIP);
    $csrf = $_SESSION['csrf'] ?? '';
    require dirname(__DIR__) . "/resources/views/$name.php";
    exit;
  }
}

final class Session {
  public static function start(): void {
    session_name('sv_sess');
    session_set_cookie_params([
      'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict',
      'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  /** Ganti isi session setelah autentikasi: ID baru + CSRF baru. */
  public static function reset(array $data): void {
    session_regenerate_id(true);
    $_SESSION = $data + ['csrf' => bin2hex(random_bytes(32))];
  }
}

/**
 * Enkripsi kolom database (at-rest) dengan AES-256-GCM. Kunci diturunkan (HKDF) dari APP_KEY di .env.
 * seal()/open() untuk nilai yang dibaca ulang; idx() = blind index HMAC-SHA256 agar kolom terenkripsi tetap bisa dicari/dihitung.
 */
final class Vault {
  private static ?string $enc = null;
  private static ?string $idx = null;
  private static function keys(): void {
    if (self::$enc !== null) return;
    $raw = Env::get('APP_KEY');
    if (str_starts_with($raw, 'base64:')) $raw = substr($raw, 7);
    $k = base64_decode($raw, true);
    if ($k === false || strlen($k) < 32) throw new \RuntimeException('APP_KEY tidak valid: butuh base64 minimal 32 byte.');
    self::$enc = hash_hkdf('sha256', $k, 32, 'sv-field-enc');
    self::$idx = hash_hkdf('sha256', $k, 32, 'sv-blind-idx');
  }
  public static function seal(?string $plain): ?string {
    if ($plain === null) return null;
    self::keys();
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', self::$enc, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ct === false || strlen($tag) !== 16) throw new \RuntimeException('Enkripsi kolom gagal.');
    return base64_encode($iv . $tag . $ct);
  }
  public static function open(?string $sealed): ?string {
    if ($sealed === null) return null;
    self::keys();
    $raw = base64_decode($sealed, true);
    if ($raw === false || strlen($raw) < 28) return null;
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::$enc, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? null : $p;
  }
  public static function idx(string $v): string { self::keys(); return hash_hmac('sha256', $v, self::$idx); }
}

/** Seluruh log disimpan terenkripsi; kolom *_idx hanya berisi HMAC untuk penghitungan/pembatasan. */
final class Log {
  public static function auth(?string $fp, string $event, string $status): void {
    $ip = Http::ip();
    DB::run('INSERT INTO authentication_logs (fp_enc,event_enc,status_enc,status_idx,ip_enc,ip_idx) VALUES (?,?,?,?,?,?)',
      [Vault::seal($fp), Vault::seal($event), Vault::seal($status), Vault::idx($status), Vault::seal($ip), Vault::idx($ip)]);
  }
  public static function sec(?int $uid, string $action, ?string $rtype, ?int $rid, string $status): void {
    DB::run('INSERT INTO security_logs (user_id,action_enc,action_idx,resource_type_enc,resource_id,status_enc) VALUES (?,?,?,?,?,?)',
      [$uid, Vault::seal($action), Vault::idx($action), Vault::seal($rtype), $rid, Vault::seal($status)]);
  }
  public static function decoy(?string $fp, string $event, string $status): void {
    DB::run('INSERT INTO decoy_logs (fp_enc,event_enc,status_enc) VALUES (?,?,?)', [Vault::seal($fp), Vault::seal($event), Vault::seal($status)]);
  }
}

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Teks acak sepanjang $s: dipakai agar sesi decoy tidak menampilkan satu kata pun yang bisa dibaca. */
function x(string $s): string {
  $n = max(1, mb_strlen($s));
  return substr(str_replace('=', '', base64_encode(random_bytes($n))), 0, $n);
}
