"""Port baris-per-baris dari SignatureService::verifyPss (RFC 8017 EMSA-PSS-VERIFY) untuk memvalidasi LOGIKA-nya
tanpa PHP. Pakai: python3 tests/pss_reference.py <outdir>"""
import sys, hashlib, base64, re
from cryptography.hazmat.primitives.serialization import load_pem_public_key

def mgf1(seed, n):
    out = b''; c = 0
    while len(out) < n: out += hashlib.sha256(seed + c.to_bytes(4, 'big')).digest(); c += 1
    return out[:n]

def verify(pem, msg, sig, tamper=False):
    nums = load_pem_public_key(pem).public_numbers()
    mod_bits = nums.n.bit_length(); k = (mod_bits + 7) // 8
    if len(sig) != k: return False
    em = pow(int.from_bytes(sig, 'big'), nums.e, nums.n).to_bytes(k, 'big')   # = openssl_public_decrypt NO_PADDING
    em_bits = mod_bits - 1; em_len = (em_bits + 7) // 8
    if len(em) > em_len:
        if em[:len(em)-em_len].strip(b'\0'): return False
        em = em[-em_len:]
    if em_len < 66 or em[-1] != 0xBC: return False
    db_len = em_len - 33; masked, H = em[:db_len], em[db_len:db_len+32]
    unused = 8*em_len - em_bits
    if unused and masked[0] & ((0xFF << (8-unused)) & 0xFF): return False
    db = bytearray(a ^ b for a, b in zip(masked, mgf1(H, db_len)))
    if unused: db[0] &= 0xFF >> unused
    ps = em_len - 66
    if bytes(db[:ps]) != b'\0'*ps or db[ps] != 1: return False
    salt = bytes(db[-32:])
    return hashlib.sha256(b'\0'*8 + hashlib.sha256(msg).digest() + salt).digest() == H

d = sys.argv[1]
pem = open(f'{d}/auth_pub.pem','rb').read(); msg = open(f'{d}/msg.bin','rb').read(); sig = open(f'{d}/sig.bin','rb').read()
der = base64.b64decode(re.sub(r'\s+','',re.search(rb'-----BEGIN PUBLIC KEY-----(.+?)-----END',pem,re.S).group(1).decode()))
print('fingerprint dari PEM (cara PHP):', hashlib.sha256(der).hexdigest() == open(f'{d}/fp.txt').read())
print('PSS valid diterima:', verify(pem, msg, sig))
print('pesan diubah ditolak:', not verify(pem, msg + b'x', sig))
print('signature diubah ditolak:', not verify(pem, msg, bytes([sig[0]^1]) + sig[1:]))
