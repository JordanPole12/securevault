<?php use function App\{h,x}; $title=x('Konten Aman — SecureVault'); require __DIR__.'/_head.php'; ?>
<div class="flex min-h-screen"><?php require __DIR__.'/_nav.php'; ?><main class="flex-1 p-6 space-y-6 font-mono">
<h1 class="font-head text-2xl font-bold break-all"><?= h(x('Konten Aman')) ?></h1>
<div class="glass p-4 space-y-3"><div class="text-sm text-mut break-all"><?= h(x('Pilih file enc_private_key.pem atau tempel teksnya untuk mendekripsi. Kunci hanya disimpan di memori tab ini.')) ?></div>
<div class="inp text-xs text-mut break-all"><?= h(x('…atau tempel teks private key enkripsi di sini')) ?></div></div>
<div class="glass p-4 grid gap-3"><div class="inp break-all"><?= h(x('Catatan Aman')) ?></div><div class="inp break-all"><?= h(x('Judul')) ?></div><div class="inp break-all" style="min-height:6rem"><?= h(x('Isi')) ?></div><div class="btn w-40 text-center break-all"><?= h(x('Enkripsi & Simpan')) ?></div></div>
<div class="space-y-3"><?php foreach($records as $r): ?><div class="glass p-4"><div class="text-xs text-mut mb-2 break-all"><?= h(x($r['type'].' · #'.random_int(1,99).' '.date('Y-m-d H:i:s'))) ?></div>
<div class="font-mono text-xs text-cy break-all"><?= h($r['title']) ?></div><div class="font-mono text-[11px] text-mut break-all mt-1"><?= h($r['cipher']) ?></div></div><?php endforeach; ?></div>
</main></div></body></html>
