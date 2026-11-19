<?php $dc = (($_SESSION['mode'] ?? '') === 'decoy'); $t = fn(string $s): string => $dc ? \App\x($s) : $s; ?>
<aside class="w-56 shrink-0 p-4 hidden md:block"><div class="glass p-4 h-full flex flex-col gap-1">
<div class="font-head text-xl font-bold mb-4"><?= $dc ? \App\h($t('SecureVault')) : 'Secure<span class="grad">Vault</span>' ?></div>
<a class="px-3 py-2 rounded-lg hover:bg-white/5" href="/dashboard"><?= \App\h($t('Dasbor')) ?></a>
<a class="px-3 py-2 rounded-lg hover:bg-white/5" href="/contents"><?= \App\h($t('Konten Aman')) ?></a>
<button onclick="svFetch('/logout',{method:'POST'}).then(r=>location=r.redirect)" class="mt-auto text-left px-3 py-2 rounded-lg hover:bg-white/5 text-mut"><?= \App\h($t('Keluar')) ?></button></div></aside>
