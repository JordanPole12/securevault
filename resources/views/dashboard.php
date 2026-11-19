<?php use function App\h; $title='Dasbor — SecureVault'; require __DIR__.'/_head.php';
$days=[];foreach($chart as $c){$days[$c['d']][$c['action']]=(int)$c['c'];}
$labels=array_keys($days);$enc=array_map(fn($d)=>$days[$d]['encrypt']??0,$labels);$dec=array_map(fn($d)=>$days[$d]['decrypt']??0,$labels); ?>
<div class="flex min-h-screen"><?php require __DIR__.'/_nav.php'; ?><main class="flex-1 p-6 space-y-6">
<h1 class="font-head text-2xl font-bold">Dasbor</h1>
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
<?php foreach([['Rekaman Terenkripsi',$stats['records']],['Operasi Enkripsi',$stats['enc']],['Operasi Dekripsi*',$stats['dec']],['Login Berhasil',$stats['ok']],['Gagal / Decoy',$stats['fail']]] as [$l,$v]): ?>
<div class="glass p-4"><div class="text-mut text-xs"><?= h($l) ?></div><div class="text-3xl font-head font-bold mt-1"><?= (int)$v ?></div></div><?php endforeach; ?></div>
<div class="grid lg:grid-cols-2 gap-4"><div class="glass p-4"><h2 class="font-semibold mb-2">Aktivitas 7 hari</h2><canvas id="c" height="140"></canvas></div>
<div class="glass p-4"><h2 class="font-semibold mb-2">Kunci</h2><table class="w-full text-xs"><?php foreach($keys as $k): ?><tr class="border-t border-white/10"><td class="py-2"><?= h($k['key_purpose']) ?></td><td class="font-mono text-cy"><?= h(substr($k['key_fingerprint'],0,16)) ?>…</td><td><?= h($k['key_status']) ?></td></tr><?php endforeach; ?></table>
<div class="text-xs text-mut mt-3">Login sukses terakhir: <?= h($last) ?></div></div></div>
<div class="glass p-4"><h2 class="font-semibold mb-2">Aktivitas terbaru</h2><table class="w-full text-xs text-mut"><?php foreach($logs as $l): ?><tr class="border-t border-white/10"><td class="py-1"><?= h($l['created_at']) ?></td><td><?= h($l['action']) ?></td><td><?= h((string)$l['resource_type']) ?></td><td><?= h($l['status']) ?></td></tr><?php endforeach; ?></table></div>
<p class="text-xs text-mut">*Dekripsi terjadi di browser; angka dicatat dari laporan klien.</p></main></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>new Chart(document.getElementById('c'),{type:'bar',data:{labels:<?= json_encode($labels,JSON_HEX_TAG|JSON_HEX_AMP) ?>,datasets:[{label:'Enkripsi',data:<?= json_encode($enc) ?>,backgroundColor:'#3B82F6'},{label:'Dekripsi',data:<?= json_encode($dec) ?>,backgroundColor:'#22D3EE'}]},options:{scales:{y:{beginAtZero:true,ticks:{precision:0}}}}})</script></body></html>
