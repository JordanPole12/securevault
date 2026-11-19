<?php $title='SecureVault — Sistem Informasi Kriptografi'; require __DIR__.'/_head.php'; ?>
<header class="max-w-6xl mx-auto flex items-center justify-between px-6 py-5">
<a href="/" class="font-head text-xl font-bold">SecureVault</a>
<nav class="hidden md:flex items-center gap-8 text-sm text-mut"><a class="hover:text-white" href="#fitur">Fitur</a><a class="hover:text-white" href="#kriptografi">Kriptografi</a><a class="hover:text-white" href="#faq">Pertanyaan umum</a></nav>
<a href="/login" class="btn text-sm">Masuk</a></header>

<main class="max-w-6xl mx-auto px-6">
<section class="grid lg:grid-cols-[1.05fr_1fr] gap-12 items-center py-16 md:py-24">
 <div>
  <h1 class="font-head text-4xl md:text-5xl font-bold leading-[1.1] tracking-tight">Data Anda terkunci dengan kunci yang hanya Anda pegang.</h1>
  <p class="mt-6 text-mut text-lg leading-relaxed max-w-xl">SecureVault menyimpan catatan, dokumen, dan rekaman rahasia dalam bentuk terenkripsi. Enkripsi dan dekripsi berlangsung di browser Anda, sehingga server tidak pernah melihat isi data maupun private key.</p>
  <div class="mt-8 flex flex-wrap gap-3"><a href="/login" class="btn">Masuk ke SecureVault</a><a href="#kriptografi" class="glass px-5 py-3 text-sm hover:border-ele">Lihat algoritma yang dipakai</a></div>
 </div>
 <div class="glass p-6" aria-live="polite">
  <label for="demo-in" class="text-sm font-semibold">Coba enkripsi langsung</label>
  <p class="text-xs text-mut mt-1">Ketik sesuatu. Teks dienkripsi AES-256-GCM dengan kunci acak yang dibuat di halaman ini dan tidak dikirim ke mana pun.</p>
  <input id="demo-in" class="inp mt-4" value="Nomor rekening: 1234-5678-9012" maxlength="120" autocomplete="off">
  <dl class="mt-4 space-y-3 text-xs">
   <div><dt class="text-mut">Ciphertext (heksadesimal)</dt><dd id="o-ct" class="font-mono text-cy break-all min-h-[2.5rem]"></dd></div>
   <div class="grid grid-cols-2 gap-3"><div><dt class="text-mut">Nonce (96 bit)</dt><dd id="o-iv" class="font-mono break-all"></dd></div><div><dt class="text-mut">Tag autentikasi (128 bit)</dt><dd id="o-tag" class="font-mono break-all"></dd></div></div>
   <div class="flex items-center gap-2 pt-1"><span id="o-ok" class="inline-block w-2 h-2 rounded-full bg-mut"></span><span id="o-st" class="text-mut">Menyiapkan kunci…</span></div>
  </dl>
 </div>
</section>

<section id="fitur" class="py-16 border-t border-white/10">
 <h2 class="font-head text-2xl md:text-3xl font-bold max-w-2xl">Dirancang agar kebocoran satu bagian tidak membuka seluruh data.</h2>
 <dl class="mt-10 divide-y divide-white/10">
  <?php foreach([
   ['Masuk tanpa kata sandi','Anda masuk dengan file private key. Browser menandatangani tantangan acak dari server, dan server memeriksanya memakai public key yang terdaftar. Private key tidak pernah dikirim.'],
   ['Enkripsi simetris untuk isi data','Judul dan isi setiap rekaman dienkripsi dengan AES-256-GCM memakai kunci acak dan nonce unik. Tag autentikasi memastikan data yang diubah terdeteksi saat dibuka.'],
   ['Enkripsi asimetris untuk kunci','Kunci AES tiap rekaman dibungkus dengan RSA-4096 (OAEP SHA-256). Hanya pemilik private key enkripsi yang bisa membukanya.'],
   ['Sesi umpan untuk kunci yang salah','Kunci yang tidak dikenali tidak ditolak mentah-mentah. Sesi diarahkan ke dasbor tiruan yang tidak punya akses ke data asli, dan percobaannya dicatat.'],
  ] as [$t,$d]): ?>
  <div class="grid md:grid-cols-[18rem_1fr] gap-2 md:gap-10 py-6"><dt class="font-semibold"><?= $t ?></dt><dd class="text-mut leading-relaxed"><?= $d ?></dd></div>
  <?php endforeach; ?>
 </dl>
</section>

<section id="kriptografi" class="py-16 border-t border-white/10">
 <h2 class="font-head text-2xl md:text-3xl font-bold">Algoritma yang digunakan</h2>
 <div class="mt-8 overflow-x-auto"><table class="w-full text-sm text-left min-w-[560px]">
  <thead class="text-mut"><tr class="border-b border-white/15"><th class="py-3 pr-6 font-medium">Fungsi</th><th class="py-3 pr-6 font-medium">Algoritma</th><th class="py-3 font-medium">Dijalankan di</th></tr></thead>
  <tbody class="divide-y divide-white/10">
   <tr><td class="py-3 pr-6">Enkripsi isi data</td><td class="pr-6">AES-256-GCM</td><td>Browser (Web Crypto API)</td></tr>
   <tr><td class="py-3 pr-6">Pembungkus kunci AES</td><td class="pr-6">RSA-4096, OAEP SHA-256</td><td>Browser</td></tr>
   <tr><td class="py-3 pr-6">Autentikasi</td><td class="pr-6">Tanda tangan RSA-PSS SHA-256</td><td>Browser menandatangani, PHP memverifikasi</td></tr>
   <tr><td class="py-3 pr-6">Identitas kunci</td><td class="pr-6">Sidik jari SHA-256</td><td>Browser dan PHP</td></tr>
   <tr><td class="py-3 pr-6">Penyimpanan</td><td class="pr-6">MySQL, hanya ciphertext</td><td>Server</td></tr>
  </tbody></table></div>
 <p class="mt-6 text-sm text-mut max-w-2xl">Jika private key enkripsi hilang, data tidak dapat dipulihkan oleh siapa pun, termasuk administrator server. Simpan cadangan kunci di tempat yang aman.</p>
</section>

<section id="faq" class="py-16 border-t border-white/10 max-w-3xl">
 <h2 class="font-head text-2xl md:text-3xl font-bold">Pertanyaan umum</h2>
 <div class="mt-6 divide-y divide-white/10">
  <?php foreach([
   ['Apakah server bisa membaca data saya?','Tidak. Server hanya menerima dan menyimpan ciphertext. Kunci untuk membukanya ada pada file private key Anda.'],
   ['Apa yang terjadi jika saya memakai kunci yang salah?','Anda dialihkan ke dasbor yang tidak memuat data asli. Dekripsi gagal dan semua endpoint data menolak sesi tersebut.'],
   ['Bisakah masuk hanya dengan public key?','Tidak. Public key hanya dipakai server untuk memeriksa tanda tangan yang dibuat dengan private key.'],
   ['Mengapa ada dua file kunci?','Satu untuk autentikasi (tanda tangan) dan satu untuk enkripsi. Memisahkan keduanya membatasi dampak jika salah satunya bocor.'],
  ] as [$q,$a]): ?>
  <details class="py-4 group"><summary class="cursor-pointer font-medium list-none flex justify-between gap-4"><?= $q ?><span class="text-mut group-open:rotate-45 transition-transform" aria-hidden="true">+</span></summary><p class="mt-2 text-mut leading-relaxed"><?= $a ?></p></details>
  <?php endforeach; ?>
 </div>
</section>
</main>
<footer class="border-t border-white/10"><div class="max-w-6xl mx-auto px-6 py-8 text-sm text-mut flex flex-wrap justify-between gap-2"><span>SecureVault — sistem informasi kriptografi simetris dan asimetris</span><span>Dibangun dengan PHP dan MySQL</span></div></footer>

<script>
(async()=>{
 const $=i=>document.getElementById(i),hex=b=>[...new Uint8Array(b)].map(x=>x.toString(16).padStart(2,'0')).join(' ');
 const key=await crypto.subtle.generateKey({name:'AES-GCM',length:256},false,['encrypt','decrypt']);let t;
 async function run(){const txt=$('demo-in').value;if(!txt){$('o-ct').textContent='';$('o-iv').textContent='';$('o-tag').textContent='';$('o-st').textContent='Menunggu teks';return}
  const iv=crypto.getRandomValues(new Uint8Array(12)),ct=new Uint8Array(await crypto.subtle.encrypt({name:'AES-GCM',iv},key,new TextEncoder().encode(txt)));
  $('o-ct').textContent=hex(ct.slice(0,-16));$('o-iv').textContent=hex(iv);$('o-tag').textContent=hex(ct.slice(-16));
  const back=new TextDecoder().decode(await crypto.subtle.decrypt({name:'AES-GCM',iv},key,ct));
  const ok=back===txt;$('o-ok').className='inline-block w-2 h-2 rounded-full '+(ok?'bg-emerald-400':'bg-red-400');$('o-st').textContent=ok?'Dekripsi dengan kunci yang sama menghasilkan teks semula':'Dekripsi gagal'}
 $('demo-in').oninput=()=>{clearTimeout(t);t=setTimeout(run,120)};run();
})();
</script></body></html>
