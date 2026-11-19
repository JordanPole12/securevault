<?php
declare(strict_types=1);
namespace App\Services;

/** Seluruh data decoy dibuat acak saat render; tidak pernah membaca tabel data asli. */
final class DecoyService
{
    /** String acak berpola Base64 sepanjang $n karakter: tidak ada kata yang dapat dibaca. */
    public static function blob(int $n): string
    {
        return substr(strtr(base64_encode(random_bytes((int)ceil($n * 3 / 4) + 3)), '=', 'x'), 0, $n);
    }

    /** Heksadesimal acak huruf besar sepanjang $n karakter. */
    public static function hex(int $n): string
    {
        return strtoupper(substr(bin2hex(random_bytes((int)ceil($n / 2))), 0, $n));
    }

    public static function records(int $n = 6): array
    {
        $types = ['Secure Note','Document','Announcement','Article','System Information','Confidential Record'];
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = [
                'type'   => $types[$i % count($types)],
                'title'  => strtoupper(bin2hex(random_bytes(12))),
                'cipher' => strtoupper(chunk_split(bin2hex(random_bytes(48)), 2, ' ')),
                'status' => 'ENCRYPTED',
            ];
        }
        return $out;
    }

    public static function stats(): array
    {
        return ['records' => random_int(20, 90), 'enc' => random_int(100, 400), 'dec' => 'N/A'];
    }
}
