<?php
declare(strict_types=1);
// Jalankan: php -S 127.0.0.1:8000 -t public public/index.php  (atau arahkan DocumentRoot Laragon ke /public)
$root = dirname(__DIR__);
require $root . '/app/Core.php';
\App\Env::load($root . '/.env');
spl_autoload_register(function (string $c) use ($root) {
    if (str_starts_with($c, 'App\\')) { $f = $root . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (is_file($f)) require $f; }
});
use App\{Http, Session};
use App\Controllers\{AuthController as A, AdminController as D};

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server' && is_file($root . '/public' . $path) && $path !== '/') return false;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'");

Session::start();
$m = $_SERVER['REQUEST_METHOD'];
try {
    $r = "$m $path";
    match (true) {
        $r === 'GET /'                      => Http::view('landing'),
        $r === 'GET /login'                 => Http::view('login'),
        $r === 'GET /setup'                 => (int)\App\DB::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0 ? Http::view('setup') : Http::json(['error' => 'Setup sudah selesai'], 404),
        $r === 'POST /api/setup'            => A::setup(),
        $r === 'GET /api/auth/challenge'    => A::challenge(),
        $r === 'POST /api/auth/verify'      => A::verify(),
        $r === 'POST /logout'               => A::logout(),
        $r === 'GET /dashboard'             => D::dashboard(),
        $r === 'GET /contents'              => D::contentsPage(),
        $r === 'GET /api/enc-key'           => D::encKey(),
        $r === 'GET /api/contents'          => D::list(),
        $r === 'POST /api/contents'         => D::create(),
        $r === 'POST /api/log-decrypt'      => D::logDecrypt(),
        (bool)preg_match('#^/api/contents/(\d+)/update$#', $path, $x) && $m === 'POST' => D::update((int)$x[1]),
        (bool)preg_match('#^/api/contents/(\d+)/delete$#', $path, $x) && $m === 'POST' => D::delete((int)$x[1]),
        default                             => Http::json(['error' => 'Tidak ditemukan'], 404),
    };
} catch (\Throwable $e) {
    error_log('[SecureVault] ' . $e->getMessage());   // tanpa data sensitif
    Http::json(['error' => 'Terjadi kesalahan server'], 500);
}
