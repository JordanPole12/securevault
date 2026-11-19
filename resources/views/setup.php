<?php $title='Setup — SecureVault'; require __DIR__.'/_head.php'; ?>
<main class="min-h-screen grid place-items-center p-6"><div class="glass p-8 w-full max-w-lg">
<h1 class="font-head text-2xl font-bold">Key Generation &amp; Setup</h1>
<p class="text-mut text-sm mt-2">Dua pasangan RSA-4096 dibuat di browser: <b>authentication</b> (login) dan <b>encryption</b> (konten). Hanya public key yang dikirim ke server.</p>
<input id="tok" class="inp mt-5" placeholder="SETUP_TOKEN dari file .env" type="password">
<button id="gen" class="btn w-full mt-4">Generate Key Pair &amp; Register</button>
<p id="st" class="text-sm mt-4 text-mut"></p><div id="dl" class="mt-3 hidden space-y-2"></div></div></main>
<script src="/js/sv-crypto.js"></script>
<script>
const st=m=>document.getElementById('st').textContent=m;
function dl(name,text){const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([text],{type:'application/x-pem-file'}));a.download=name;a.className='glass block px-4 py-2 text-center';a.textContent='Download '+name;document.getElementById('dl').appendChild(a)}
document.getElementById('gen').onclick=async e=>{e.target.disabled=true;try{st('Membuat kunci RSA-4096 (bisa memakan beberapa detik)…');
 const a=await SV.generatePair('auth'),k=await SV.generatePair('enc');
 const r=await svFetch('/api/setup',{method:'POST',body:JSON.stringify({token:document.getElementById('tok').value,auth_public:a.publicPem,enc_public:k.publicPem})});
 dl('auth_private_key.pem',a.privatePem);dl('enc_private_key.pem',k.privatePem);document.getElementById('dl').classList.remove('hidden');
 st('Terdaftar. Unduh KEDUA private key sekarang dan simpan aman. Tidak ada salinan di server dan tidak dapat dipulihkan. Lalu buka /login.')}
 catch(x){st('Gagal: '+x.message);e.target.disabled=false}};
</script></body></html>
