/* SecureVault — operasi kriptografi sisi browser (Web Crypto API). Private key tidak pernah dikirim ke server. */
(function (root) {
  const te = new TextEncoder(), td = new TextDecoder();
  const PSS = { name: 'RSA-PSS', hash: 'SHA-256' };
  const OAEP = { name: 'RSA-OAEP', hash: 'SHA-256' };
  const AUTH_CONTEXT = 'SecureVault-Auth-v1:';

  const b64 = (buf) => { const u = new Uint8Array(buf); let s = ''; for (let i = 0; i < u.length; i += 0x8000) s += String.fromCharCode.apply(null, u.subarray(i, i + 0x8000)); return btoa(s); };
  const unb64 = (s) => Uint8Array.from(atob(s), (c) => c.charCodeAt(0));
  const hex = (buf) => [...new Uint8Array(buf)].map((x) => x.toString(16).padStart(2, '0')).join('');

  function pemToDer(pem, label) {
    const m = pem.match(new RegExp('-----BEGIN ' + label + '-----([\\s\\S]+?)-----END ' + label + '-----'));
    if (!m) throw new Error('Bukan file PEM ' + label + ' yang valid (format PKCS#8 diharapkan).');
    return unb64(m[1].replace(/\s+/g, '')).buffer;
  }
  const derToPem = (der, label) => '-----BEGIN ' + label + '-----\n' + b64(der).match(/.{1,64}/g).join('\n') + '\n-----END ' + label + '-----\n';
  const importPriv = (pem, alg, usages) => crypto.subtle.importKey('pkcs8', pemToDer(pem, 'PRIVATE KEY'), alg, true, usages);

  async function spkiOf(privKey, alg, usage) {
    const j = await crypto.subtle.exportKey('jwk', privKey);
    const pub = await crypto.subtle.importKey('jwk', { kty: j.kty, n: j.n, e: j.e, alg: j.alg, ext: true }, alg, true, [usage]);
    return crypto.subtle.exportKey('spki', pub);
  }

  async function generatePair(kind) {
    const alg = kind === 'auth' ? PSS : OAEP;
    const kp = await crypto.subtle.generateKey({ ...alg, modulusLength: 4096, publicExponent: new Uint8Array([1, 0, 1]) }, true,
      kind === 'auth' ? ['sign', 'verify'] : ['encrypt', 'decrypt']);
    return {
      privatePem: derToPem(await crypto.subtle.exportKey('pkcs8', kp.privateKey), 'PRIVATE KEY'),
      publicPem: derToPem(await crypto.subtle.exportKey('spki', kp.publicKey), 'PUBLIC KEY'),
    };
  }

  async function authFingerprint(privPem) {
    const key = await importPriv(privPem, PSS, ['sign']);
    return hex(await crypto.subtle.digest('SHA-256', await spkiOf(key, PSS, 'verify')));
  }

  async function signChallenge(privPem, challengeB64) {
    const key = await importPriv(privPem, PSS, ['sign']);
    const fingerprint = hex(await crypto.subtle.digest('SHA-256', await spkiOf(key, PSS, 'verify')));
    const ch = unb64(challengeB64), ctx = te.encode(AUTH_CONTEXT);
    const msg = new Uint8Array(ctx.length + ch.length); msg.set(ctx); msg.set(ch, ctx.length);
    const sig = await crypto.subtle.sign({ name: 'RSA-PSS', saltLength: 32 }, key, msg);
    return { fingerprint, signature: b64(sig) };
  }

  // ---- Hybrid encryption: AES-256-GCM (konten) + RSA-OAEP-SHA256 (pembungkus kunci AES) ----
  async function encryptRecord(encPubPem, title, content) {
    const aes = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt']);
    const pub = await crypto.subtle.importKey('spki', pemToDer(encPubPem, 'PUBLIC KEY'), OAEP, false, ['encrypt']);
    const wrapped = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, pub, await crypto.subtle.exportKey('raw', aes));
    const seal = async (text) => {
      const iv = crypto.getRandomValues(new Uint8Array(12));
      const ct = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, aes, te.encode(text)));
      return { iv, body: ct.slice(0, -16), tag: ct.slice(-16) };
    };
    const c = await seal(content), t = await seal(title);
    return {
      encrypted_aes_key: b64(wrapped), nonce: b64(c.iv), encrypted_content: b64(c.body),
      authentication_tag: b64(c.tag), encrypted_title: [b64(t.iv), b64(t.body), b64(t.tag)].join('.'),
    };
  }

  async function decryptRecord(encPrivPem, r) {
    const priv = await importPriv(encPrivPem, OAEP, ['decrypt']);
    const raw = await crypto.subtle.decrypt({ name: 'RSA-OAEP' }, priv, unb64(r.encrypted_aes_key));
    const aes = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['decrypt']);
    const open = async (iv, body, tag) => {
      const j = new Uint8Array(body.length + tag.length); j.set(body); j.set(tag, body.length);
      return td.decode(await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, aes, j));
    };
    const [ti, tb, tt] = r.encrypted_title.split('.').map(unb64);
    return { title: await open(ti, tb, tt), content: await open(unb64(r.nonce), unb64(r.encrypted_content), unb64(r.authentication_tag)) };
  }

  root.SV = { b64, unb64, hex, pemToDer, derToPem, generatePair, authFingerprint, signChallenge, encryptRecord, decryptRecord, AUTH_CONTEXT };
})(typeof window !== 'undefined' ? window : globalThis);
