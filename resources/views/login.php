<?php $title='Masuk — SecureVault'; require __DIR__.'/_head.php'; ?>
<main class="min-h-screen grid place-items-center p-6"><div class="glass p-8 w-full max-w-md">
<div class="font-head text-2xl font-bold">Secure<span class="grad">Vault</span></div>
<h1 class="mt-4 text-xl font-semibold">Selamat Datang di SecureVault</h1><p class="text-mut text-sm">Autentikasi Kriptografi yang Aman</p>
<div class="mt-6 grid grid-cols-2 gap-2 text-sm" role="tablist">
<button id="tf" class="glass py-2 border-ele" type="button">Pilih File</button><button id="tt" class="glass py-2 opacity-60" type="button">Tempel Teks</button></div>
<div id="pf" class="mt-4"><label id="drop" class="block border-2 border-dashed border-white/15 rounded-xl p-8 text-center cursor-pointer hover:border-ele">
<input id="f" type="file" accept=".pem,.key,.txt" class="hidden"><span class="text-mut text-sm">Pilih file private key (.pem), seret &amp; lepas, atau klik untuk menelusuri</span></label></div>
<div id="pt" class="mt-4 hidden"><textarea id="ta" rows="7" class="inp font-mono text-xs" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----" spellcheck="false" autocomplete="off"></textarea></div>
<div id="info" class="mt-4 text-sm text-mut hidden"><div>Sumber: <span id="fn" class="text-white"></span></div><div class="break-all">Sidik jari kunci: <span id="fp" class="text-cy font-mono text-xs"></span></div></div>
<p id="err" class="mt-3 text-sm text-red-400 hidden"></p>
<button id="go" class="btn w-full mt-6" disabled>Autentikasi dengan Aman</button>
<p class="text-xs text-mut mt-4">Private key hanya dibaca dan dipakai di browser ini; tidak diunggah ke server dan tidak disimpan.</p></div></main>
<script src="/js/sv-crypto.js"></script>
<script>
let raw=null,pem=null,timer=null;const $=i=>document.getElementById(i),note=m=>{$('err').textContent=m;$('err').classList.toggle('hidden',!m)};
function reset(){raw=null;pem=null;$('go').disabled=true;$('info').classList.add('hidden');note('')}
async function accept(text,label){reset();if(!text.trim())return;
 if(text.length>20000)return note('Ukuran terlalu besar untuk sebuah private key.');
 raw=text;$('go').disabled=false;$('fn').textContent=label;
 try{$('fp').textContent=await SV.authFingerprint(text);pem=text;$('info').classList.remove('hidden')}
 catch(e){pem=null;note('Format kunci tidak dikenali sebagai private key RSA (PKCS#8). Anda tetap dapat melanjutkan.')}}
function tab(file){$('pf').classList.toggle('hidden',!file);$('pt').classList.toggle('hidden',file);
 $('tf').classList.toggle('opacity-60',!file);$('tt').classList.toggle('opacity-60',file);reset();$('ta').value='';$('f').value=''}
async function fromFile(f){if(!f)return;if(f.size>20000){reset();return note('Ukuran file terlalu besar untuk sebuah private key.')}accept(await f.text(),f.name)}
$('tf').onclick=()=>tab(true);$('tt').onclick=()=>tab(false);
$('f').onchange=e=>fromFile(e.target.files[0]);
$('drop').ondragover=e=>e.preventDefault();$('drop').ondrop=e=>{e.preventDefault();fromFile(e.dataTransfer.files[0])};
$('ta').oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>accept($('ta').value,'teks yang ditempel'),300)};
$('go').onclick=async()=>{$('go').disabled=true;try{const {challenge}=await svFetch('/api/auth/challenge');let body;
 if(pem){body=await SV.signChallenge(pem,challenge)}
 else{/* input bukan kunci valid: tidak ada yang bisa ditandatangani; kirim bukti palsu agar server menerbitkan sesi decoy */
  const d=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(raw));body={fingerprint:SV.hex(d),signature:SV.b64(crypto.getRandomValues(new Uint8Array(512)))}}
 raw=pem=null;$('ta').value='';const r=await svFetch('/api/auth/verify',{method:'POST',body:JSON.stringify(body)});location=r.redirect}
 catch(e){note(e.message);$('go').disabled=false}};
</script></body></html>
