<?php $title='Konten Aman — SecureVault'; require __DIR__.'/_head.php'; ?>
<div class="flex min-h-screen"><?php require __DIR__.'/_nav.php'; ?><main class="flex-1 p-6 space-y-6">
<h1 class="font-head text-2xl font-bold">Konten Aman</h1>
<div class="glass p-4 space-y-3"><div class="text-sm text-mut">Pilih file <b>enc_private_key.pem</b> atau tempel teksnya untuk mendekripsi. Kunci hanya disimpan di memori tab ini.</div>
<input id="kf" type="file" accept=".pem,.key,.txt" class="text-sm"><span id="ks" class="text-xs text-mut ml-2">belum dimuat</span>
<textarea id="kt" rows="3" class="inp font-mono text-xs" placeholder="…atau tempel teks private key enkripsi di sini" spellcheck="false" autocomplete="off"></textarea></div>
<form id="fm" class="glass p-4 grid gap-3" onsubmit="return false"><input type="hidden" id="eid">
<select id="ty" class="inp"><option value="secure_note">Catatan Aman</option><option value="document">Dokumen</option><option value="announcement">Pengumuman</option><option value="article">Artikel</option><option value="system_info">Informasi Sistem</option><option value="confidential_record">Rekaman Rahasia</option></select>
<input id="ti" class="inp" placeholder="Judul" maxlength="200"><textarea id="co" class="inp" rows="4" placeholder="Isi" maxlength="100000"></textarea>
<div class="flex gap-2"><button id="sv" class="btn">Enkripsi &amp; Simpan</button><button id="cx" class="glass px-4 hidden">Batal</button></div><p id="er" class="text-sm text-red-400"></p></form>
<div class="flex gap-2"><input id="q" class="inp" placeholder="Cari di konten yang sudah didekripsi…"><select id="fl" class="inp w-56"><option value="">Semua tipe</option><option value="secure_note">Catatan Aman</option><option value="document">Dokumen</option><option value="announcement">Pengumuman</option><option value="article">Artikel</option><option value="system_info">Informasi Sistem</option><option value="confidential_record">Rekaman Rahasia</option></select></div>
<div id="ls" class="space-y-3"></div></main></div>
<script src="/js/sv-crypto.js"></script>
<script>
const $=i=>document.getElementById(i);let kp=null,items=[],plain={};
const esc=s=>s.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function setKey(t){try{SV.pemToDer(t,'PRIVATE KEY');kp=t;$('ks').textContent='kunci dimuat';await decryptAll()}catch{kp=null;$('ks').textContent='kunci tidak valid'}}
$('kf').onchange=async e=>{if(e.target.files[0])setKey(await e.target.files[0].text())};
$('kt').oninput=()=>{if($('kt').value.trim())setKey($('kt').value)};
async function load(){items=(await svFetch('/api/contents?type='+encodeURIComponent($('fl').value))).items;await decryptAll()}
async function decryptAll(){plain={};if(kp)for(const r of items){try{plain[r.id]=await SV.decryptRecord(kp,r);}catch{plain[r.id]={err:true}}}render()}
function render(){const q=$('q').value.toLowerCase();$('ls').innerHTML=items.filter(r=>!q||(plain[r.id]&&!plain[r.id].err&&(plain[r.id].title+plain[r.id].content).toLowerCase().includes(q))).map(r=>{const p=plain[r.id];
 const body=!kp?`<div class="font-mono text-[10px] text-mut break-all">${esc(r.encrypted_content.slice(0,160))}…</div><div class="text-xs text-cy mt-1">TERENKRIPSI — pilih private key untuk membuka</div>`
 :p&&!p.err?`<div class="font-semibold">${esc(p.title)}</div><div class="text-sm text-mut whitespace-pre-wrap mt-1">${esc(p.content)}</div>`:`<div class="text-red-400 text-sm">Gagal dekripsi (kunci salah atau data rusak)</div>`;
 return `<div class="glass p-4"><div class="flex justify-between text-xs text-mut mb-2"><span>${esc(r.content_type)} · #${r.id}</span><span>${esc(r.updated_at)}</span></div>${body}
 <div class="mt-3 flex gap-3 text-xs"><button data-e="${r.id}" class="text-ele">Edit</button><button data-d="${r.id}" class="text-red-400">Hapus</button></div></div>`}).join('')||'<p class="text-mut text-sm">Belum ada data.</p>'}
$('ls').onclick=async e=>{const d=e.target.dataset;if(d.d&&confirm('Hapus data ini?')){await svFetch('/api/contents/'+d.d+'/delete',{method:'POST'});load()}
 if(d.e){const p=plain[d.e];if(!p||p.err)return alert('Muat private key yang benar dulu.');$('eid').value=d.e;$('ti').value=p.title;$('co').value=p.content;$('ty').value=items.find(i=>i.id==d.e).content_type;$('cx').classList.remove('hidden');scrollTo(0,0)}};
$('cx').onclick=()=>{$('eid').value='';$('fm').reset();$('cx').classList.add('hidden')};
$('sv').onclick=async()=>{$('er').textContent='';try{if(!$('ti').value.trim()||!$('co').value.trim())throw new Error('Judul dan isi wajib diisi');
 const k=await svFetch('/api/enc-key');const rec=await SV.encryptRecord(k.public_key,$('ti').value,$('co').value);rec.content_type=$('ty').value;
 const id=$('eid').value;await svFetch(id?`/api/contents/${id}/update`:'/api/contents',{method:'POST',body:JSON.stringify(rec)});$('cx').onclick();load()}catch(e){$('er').textContent=e.message}};
$('q').oninput=render;$('fl').onchange=load;
// laporkan dekripsi (sekali per rekaman per muat) untuk audit
const _d=SV.decryptRecord,seen=new Set();SV.decryptRecord=async(k,r)=>{const o=await _d(k,r);if(!seen.has(r.id)){seen.add(r.id);svFetch('/api/log-decrypt',{method:'POST',body:JSON.stringify({id:r.id})}).catch(()=>{})}return o};
load();
</script></body></html>
