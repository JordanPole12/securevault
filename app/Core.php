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

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
