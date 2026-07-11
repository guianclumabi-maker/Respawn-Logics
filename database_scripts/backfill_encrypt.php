<?php
/**
 * Phase 5 — One-time idempotent backfill script.
 *
 * Encrypts any existing plaintext PII in employee_statutory, and
 * sets the is_encrypted flag on employee_documents for files already
 * stored on disk (does NOT re-encrypt files — that's a separate step
 * once the file-encryption wiring is deployed).
 *
 * Safe to re-run: already-encrypted values (enc:v1: prefix) are skipped.
 *
 * Usage:
 *   php database_scripts/backfill_encrypt.php [--dry-run]
 *
 * NEVER log plaintext values. Only log counts.
 */

define('MIGRATION_SAFE', true);
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../backend/utils/Crypto.php';

use App\Utils\Crypto;

$isDryRun = in_array('--dry-run', $argv ?? [], true);
if ($isDryRun) {
    echo "[DRY RUN] No changes will be written.\n";
}

echo "Starting AES-256-GCM backfill...\n\n";

// ── employee_statutory ────────────────────────────────────────────────────────

$encFields = ['sss_number', 'philhealth_number', 'pagibig_number', 'tin_number'];

$rows = $pdo->query("SELECT * FROM `employee_statutory`")->fetchAll(PDO::FETCH_ASSOC);
$updatedStatutory = 0;
$skippedStatutory = 0;

foreach ($rows as $row) {
    $needsUpdate = false;
    $updates = [];

    foreach ($encFields as $col) {
        $val = $row[$col] ?? null;
        if ($val !== null && $val !== '' && !Crypto::isEncrypted($val)) {
            $updates[$col] = $isDryRun ? $val : Crypto::encrypt($val);
            $needsUpdate = true;
        }
    }

    // Blind index for TIN
    $tin = $row['tin_number'] ?? null;
    if ($tin !== null && $tin !== '') {
        $plainTin = Crypto::isEncrypted($tin) ? null : $tin; // only compute if still plaintext
        if ($plainTin !== null) {
            $updates['tin_bidx'] = $isDryRun ? '[dry]' : Crypto::blindIndex($plainTin);
            $needsUpdate = true;
        }
    }

    if ($needsUpdate) {
        if (!$isDryRun) {
            $setClauses = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($updates)));
            $stmt = $pdo->prepare("UPDATE `employee_statutory` SET $setClauses WHERE `id` = ?");
            $stmt->execute([...array_values($updates), $row['id']]);
        }
        $updatedStatutory++;
    } else {
        $skippedStatutory++;
    }
}

echo "employee_statutory:\n";
echo "  Updated : $updatedStatutory rows\n";
echo "  Skipped : $skippedStatutory rows (already encrypted or empty)\n\n";

// ── employee_documents ────────────────────────────────────────────────────────
// For now we just confirm the is_encrypted flag is correct.
// File-level encryption (encryptBytes) is wired separately when CoreHRController
// is updated. This script only validates the column exists.

$docCheck = $pdo->query("SHOW COLUMNS FROM `employee_documents` LIKE 'is_encrypted'");
if ($docCheck && $docCheck->rowCount() > 0) {
    echo "employee_documents: is_encrypted column present — file-level backfill\n";
    echo "  runs after CoreHRController file-encryption wiring is deployed.\n\n";
} else {
    echo "[WARNING] employee_documents.is_encrypted missing — run migrate_encryption_columns.php first.\n\n";
}

echo $isDryRun
    ? "[DRY RUN COMPLETE] No rows were modified.\n"
    : "Backfill complete.\n";
