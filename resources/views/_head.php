<?php use function App\h; ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= h($csrf ?? '') ?>"><title><?= h($title ?? 'SecureVault') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{bg:'#080D18',bg2:'#101827',card:'#151F32',pri:'#2563EB',ele:'#3B82F6',cy:'#22D3EE',pu:'#8B5CF6',mut:'#94A3B8'},fontFamily:{sans:['Inter'],head:['Space Grotesk']}}}}</script>
<style>body{background:radial-gradient(60rem 30rem at 80% -10%,rgba(139,92,246,.18),transparent),radial-gradient(50rem 30rem at 0% 0%,rgba(37,99,235,.22),transparent),#080D18;color:#F8FAFC}
.glass{background:rgba(21,31,50,.65);backdrop-filter:blur(14px);border:1px solid rgba(148,163,184,.14);border-radius:1rem}
.grad{background:linear-gradient(90deg,#3B82F6,#22D3EE);-webkit-background-clip:text;color:transparent}
.btn{background:linear-gradient(90deg,#2563EB,#8B5CF6);border-radius:.75rem;padding:.7rem 1.2rem;font-weight:600;transition:.2s}.btn:hover{transform:translateY(-1px);box-shadow:0 8px 30px rgba(37,99,235,.35)}
.btn:disabled{opacity:.5;cursor:not-allowed}.inp{background:#0B1220;border:1px solid rgba(148,163,184,.2);border-radius:.6rem;padding:.6rem .8rem;width:100%}
.float{animation:f 6s ease-in-out infinite}@keyframes f{50%{transform:translateY(-10px)}}</style>
<script>window.svFetch=async(u,o={})=>{const r=await fetch(u,{...o,headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name=csrf]').content,...(o.headers||{})}});const j=await r.json().catch(()=>({}));if(!r.ok)throw new Error(j.error||('HTTP '+r.status));return j}</script>
</head><body class="font-sans min-h-screen">
