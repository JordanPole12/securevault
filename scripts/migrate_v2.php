<?php
declare(strict_types=1);
/**
 * Migrasi database lama -> kolom terenkripsi (Vault, AES-256-GCM + blind index).
 * Jalankan SEKALI dari folder proyek, setelah APP_KEY diisi di .env:   php scripts/migrate_v2.php
 * Data kunci & konten tetap; tipe konten dan seluruh log dienkripsi, kolom lama dihapus.
 * Aman diulang: langkah yang sudah selesai dilewati.
 */
$root = dirname(__DIR__);
require $root . '/app/Core.php';
\App\Env::load($root . '/.env');
use App\{DB, Vault};

$pdo = DB::pdo();
Vault::idx('cek-APP_KEY');   // melempar error bila APP_KEY kosong/tidak valid

function hasCol(string $t, string $c): bool {
    return (int)(DB::one('SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?', [$t, $c])['c'] ?? 0) > 0;
}

// --- secure_contents.content_type
if (hasCol('secure_contents', 'content_type')) {
    echo "secure_contents ...\n";
    $pdo->exec('ALTER TABLE secure_contents ADD content_type_enc TEXT NULL, ADD type_idx CHAR(64) NULL');
    foreach (DB::all('SELECT id, content_type FROM secure_contents') as $r) {
        DB::run('UPDATE secure_contents SET content_type_enc=?, type_idx=?, updated_at=updated_at WHERE id=?', [Vault::seal($r['content_type']), Vault::idx($r['content_type']), $r['id']]);
    }
    $pdo->exec('ALTER TABLE secure_contents DROP INDEX idx_type');
    $pdo->exec('ALTER TABLE secure_contents DROP COLUMN content_type, MODIFY content_type_enc TEXT NOT NULL, MODIFY type_idx CHAR(64) NOT NULL, ADD KEY idx_type (type_idx)');
}

// --- authentication_logs
if (hasCol('authentication_logs', 'ip_address')) {
    echo "authentication_logs ...\n";
    $pdo->exec('ALTER TABLE authentication_logs ADD fp_enc TEXT NULL, ADD event_enc TEXT NULL, ADD status_enc TEXT NULL, ADD status_idx CHAR(64) NULL, ADD ip_enc TEXT NULL, ADD ip_idx CHAR(64) NULL');
    foreach (DB::all('SELECT id, key_fingerprint, event_type, status, ip_address FROM authentication_logs') as $r) {
        DB::run('UPDATE authentication_logs SET fp_enc=?, event_enc=?, status_enc=?, status_idx=?, ip_enc=?, ip_idx=?, created_at=created_at WHERE id=?',
            [Vault::seal($r['key_fingerprint']), Vault::seal($r['event_type']), Vault::seal($r['status']), Vault::idx($r['status']), Vault::seal($r['ip_address']), Vault::idx($r['ip_address']), $r['id']]);
    }
    $pdo->exec('ALTER TABLE authentication_logs DROP INDEX idx_ip_time');
    $pdo->exec('ALTER TABLE authentication_logs DROP COLUMN key_fingerprint, DROP COLUMN event_type, DROP COLUMN status, DROP COLUMN ip_address, MODIFY event_enc TEXT NOT NULL, MODIFY status_enc TEXT NOT NULL, MODIFY status_idx CHAR(64) NOT NULL, MODIFY ip_enc TEXT NOT NULL, MODIFY ip_idx CHAR(64) NOT NULL, ADD KEY idx_ip_time (ip_idx, status_idx, created_at)');
}

// --- security_logs
if (hasCol('security_logs', 'action')) {
    echo "security_logs ...\n";
    $pdo->exec('ALTER TABLE security_logs ADD action_enc TEXT NULL, ADD action_idx CHAR(64) NULL, ADD resource_type_enc TEXT NULL, ADD status_enc TEXT NULL');
    foreach (DB::all('SELECT id, action, resource_type, status FROM security_logs') as $r) {
        DB::run('UPDATE security_logs SET action_enc=?, action_idx=?, resource_type_enc=?, status_enc=?, created_at=created_at WHERE id=?',
            [Vault::seal($r['action']), Vault::idx($r['action']), Vault::seal($r['resource_type']), Vault::seal($r['status']), $r['id']]);
    }
    $pdo->exec('ALTER TABLE security_logs DROP INDEX idx_action_time');
    $pdo->exec('ALTER TABLE security_logs DROP COLUMN action, DROP COLUMN resource_type, DROP COLUMN status, MODIFY action_enc TEXT NOT NULL, MODIFY action_idx CHAR(64) NOT NULL, MODIFY status_enc TEXT NOT NULL, ADD KEY idx_action_time (action_idx, created_at)');
}

// --- decoy_logs
if (hasCol('decoy_logs', 'event_type')) {
    echo "decoy_logs ...\n";
    $pdo->exec('ALTER TABLE decoy_logs ADD fp_enc TEXT NULL, ADD event_enc TEXT NULL, ADD status_enc TEXT NULL');
    foreach (DB::all('SELECT id, attempted_key_fingerprint, event_type, status FROM decoy_logs') as $r) {
        DB::run('UPDATE decoy_logs SET fp_enc=?, event_enc=?, status_enc=?, created_at=created_at WHERE id=?',
            [Vault::seal($r['attempted_key_fingerprint']), Vault::seal($r['event_type']), Vault::seal($r['status']), $r['id']]);
    }
    $pdo->exec('ALTER TABLE decoy_logs DROP COLUMN attempted_key_fingerprint, DROP COLUMN event_type, DROP COLUMN status, MODIFY event_enc TEXT NOT NULL, MODIFY status_enc TEXT NOT NULL');
}

// --- system_settings: nilai lama berisi nama algoritma biasa; dienkripsi juga
if ((int)(DB::one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='system_settings' AND column_name='setting_value' AND data_type='varchar'")['c'] ?? 0) > 0) {
    echo "system_settings ...\n";
    $pdo->exec('ALTER TABLE system_settings MODIFY setting_value TEXT NOT NULL');
    foreach (DB::all('SELECT id, setting_value FROM system_settings') as $r) {
        DB::run('UPDATE system_settings SET setting_value=?, updated_at=updated_at WHERE id=?', [Vault::seal($r['setting_value']), $r['id']]);
    }
}
echo "Selesai. Kolom sensitif kini terenkripsi.\n";
