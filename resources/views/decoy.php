<?php use function App\h; $title='Dasbor — SecureVault'; require __DIR__.'/_head.php'; ?>
<div class="flex min-h-screen"><?php require __DIR__.'/_nav.php'; ?><main class="flex-1 p-6 space-y-6">
<div role="alert" class="rounded-xl border border-red-500/40 bg-red-500/10 p-4"><div class="font-semibold text-red-300">Galat 403: private key tidak cocok</div><p class="text-sm text-red-200/80 mt-1">Dekripsi gagal karena private key yang digunakan tidak terdaftar atau tidak valid. Data asli tidak dapat ditampilkan dan sesi ini hanya dapat melihat data demo.</p></div>
<div class="glass p-6 border-red-500/30"><h1 class="font-head text-2xl font-bold text-red-300">AKSES DIBATASI</h1><p class="text-mut">Identitas kriptografi Anda tidak dapat diverifikasi.</p>
<span class="inline-block mt-3 text-xs px-2 py-1 rounded bg-red-500/15 text-red-300">SESI TIDAK TERVERIFIKASI</span> <span class="text-xs px-2 py-1 rounded bg-amber-500/15 text-amber-300">DATA DEMO</span></div>
<div class="grid grid-cols-3 gap-4"><div class="glass p-4"><div class="text-mut text-xs">Rekaman Terenkripsi (DEMO)</div><div class="text-3xl font-head"><?= (int)$stats['records'] ?></div></div>
<div class="glass p-4"><div class="text-mut text-xs">Operasi Enkripsi (DEMO)</div><div class="text-3xl font-head"><?= (int)$stats['enc'] ?></div></div>
<div class="glass p-4"><div class="text-mut text-xs">Dekripsi</div><div class="text-xl font-head text-red-300">DEKRIPSI TIDAK TERSEDIA</div></div></div>
<div class="glass p-4"><h2 class="font-semibold mb-3">Pratinjau Konten Terenkripsi <span class="text-xs text-amber-300">DEMO</span></h2>
<?php foreach($records as $r): ?><div class="border-t border-white/10 py-3"><div class="flex justify-between text-xs"><span><?= h($r['type']) ?></span><span class="text-cy"><?= h($r['status']) ?></span></div>
<div class="font-mono text-xs text-mut break-all"><?= h($r['title']) ?></div><div class="font-mono text-[10px] text-mut/70 break-all"><?= h($r['cipher']) ?></div></div><?php endforeach; ?></div></main></div></body></html>
