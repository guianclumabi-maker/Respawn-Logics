<?php
/**
 * Run this script from the command line:
 * php backend/migrations/migrate_legacy_uploads.php
 */

require_once __DIR__ . '/../../bootstrap/app.php';
require_once __DIR__ . '/../utils/Storage.php';

$fileStorage = rtrim(\App\Utils\Storage::resolveStorageBase(false, false), '/');
$resumeStorage = rtrim(\App\Utils\Storage::resolveStorageBase(true, false), '/');

echo "=== Respawn-Logics File Migration ===\n";
echo "File Storage Base: $fileStorage\n";
echo "Resume Storage Base: $resumeStorage\n\n";

$legacyDir = realpath(__DIR__ . '/../../uploads');

if (!$legacyDir || !is_dir($legacyDir)) {
    echo "No legacy 'uploads' directory found at " . __DIR__ . "/../../uploads\n";
    // We still want to run DB path cleanup just in case
} else {
    echo "Migrating physical files from $legacyDir...\n";
    
    // Move profile images (avatars)
    $avatarsDir = $fileStorage . '/avatars';
    if (!is_dir($avatarsDir)) @mkdir($avatarsDir, 0755, true);
    
    $stmt = $pdo->query("SELECT id, profile_image FROM users WHERE profile_image IS NOT NULL AND profile_image != ''");
    $users = $stmt->fetchAll();
    foreach ($users as $u) {
        $img = basename($u['profile_image']); // e.g. "avatar.png"
        $oldPath = $legacyDir . '/' . $img;
        $newPath = $avatarsDir . '/' . $img;
        if (file_exists($oldPath)) {
            if (rename($oldPath, $newPath)) {
                echo "Moved avatar: $img\n";
            } else {
                echo "Failed to move avatar: $img\n";
            }
        }
    }

    // Recursively move all other files from legacyDir to fileStorage, EXCEPT if it's resumes which go to resumeStorage
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($legacyDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $path = $item->getPathname();
            // Ignore the README.md
            if ($item->getFilename() === 'README.md') continue;
            
            // e.g. "tenant_1/receipts/file.jpg"
            $relativePath = str_replace($legacyDir . DIRECTORY_SEPARATOR, '', $path);
            $relativePath = str_replace('\\', '/', $relativePath); // normalize
            
            // Check if it's an avatar we already moved
            if (strpos($relativePath, '/') === false) {
                // Root level file in uploads/, might be an avatar or announcement
                // We'll move it to avatars just in case if it's an image, or announcements if it matches post_
                if (strpos($relativePath, 'post_') === 0) {
                    // Legacy announcement image
                    $targetDir = $fileStorage . '/announcements';
                    if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);
                    $targetPath = $targetDir . '/' . $relativePath;
                } else {
                    $targetPath = $avatarsDir . '/' . $relativePath;
                }
            } else {
                if (strpos($relativePath, 'resumes/') !== false) {
                    $targetPath = $resumeStorage . '/' . $relativePath;
                } else {
                    $targetPath = $fileStorage . '/' . $relativePath;
                }
            }

            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }

            if (rename($path, $targetPath)) {
                echo "Moved: $relativePath\n";
            } else {
                echo "Failed to move: $relativePath\n";
            }
        }
    }
}

echo "\nCleaning up database paths...\n";

$queries = [
    // 1. employee_documents
    "UPDATE employee_documents SET file_path = REPLACE(file_path, 'uploads/', '') WHERE file_path LIKE 'uploads/%'",
    "UPDATE employee_documents SET file_path = REPLACE(file_path, '/uploads/', '') WHERE file_path LIKE '/uploads/%'",

    // 2. candidate_profiles
    "UPDATE candidate_profiles SET resume_file_path = REPLACE(resume_file_path, 'uploads/', '') WHERE resume_file_path LIKE 'uploads/%'",
    "UPDATE candidate_profiles SET resume_file_path = REPLACE(resume_file_path, '/uploads/', '') WHERE resume_file_path LIKE '/uploads/%'",
    "UPDATE candidate_profiles SET resume_file_path = REPLACE(resume_file_path, 'storage/', '') WHERE resume_file_path LIKE 'storage/%'",

    // 3. expenses
    "UPDATE expenses SET receipt_path = REPLACE(receipt_path, 'uploads/receipts/', '') WHERE receipt_path LIKE 'uploads/receipts/%'",
    "UPDATE expenses SET receipt_path = REPLACE(receipt_path, '/uploads/receipts/', '') WHERE receipt_path LIKE '/uploads/receipts/%'",

    // 4. ticket_comments (ESM)
    "UPDATE ticket_comments SET attachment_url = REPLACE(attachment_url, 'uploads/tickets/', '') WHERE attachment_url LIKE 'uploads/tickets/%'",
    "UPDATE ticket_comments SET attachment_url = REPLACE(attachment_url, '/uploads/tickets/', '') WHERE attachment_url LIKE '/uploads/tickets/%'",

    // 5. platform_ticket_comments
    "UPDATE platform_ticket_comments SET attachments = REPLACE(attachments, '\"/uploads/', '\"') WHERE attachments LIKE '%\"/uploads/%'",
    "UPDATE platform_ticket_comments SET attachments = REPLACE(attachments, '\"uploads/', '\"') WHERE attachments LIKE '%\"uploads/%'",

    // 6. esm_ticket_comments
    "UPDATE esm_ticket_comments SET attachments = REPLACE(attachments, '\"/uploads/', '\"') WHERE attachments LIKE '%\"/uploads/%'",
    "UPDATE esm_ticket_comments SET attachments = REPLACE(attachments, '\"uploads/', '\"') WHERE attachments LIKE '%\"uploads/%'",

    // 7. company_posts
    "UPDATE company_posts SET image_path = REPLACE(image_path, 'uploads/', '') WHERE image_path LIKE 'uploads/%'",
    "UPDATE company_posts SET image_path = REPLACE(image_path, '/uploads/', '') WHERE image_path LIKE '/uploads/%'",

    // 8. payroll_payslips
    "UPDATE payroll_payslips SET pdf_path = REPLACE(pdf_path, 'uploads/', '') WHERE pdf_path LIKE 'uploads/%'",
    "UPDATE payroll_payslips SET pdf_path = REPLACE(pdf_path, '/uploads/', '') WHERE pdf_path LIKE '/uploads/%'"
];

foreach ($queries as $query) {
    try {
        $pdo->exec($query);
    } catch (PDOException $e) {
        // Skip if column/table doesn't exist
        echo "Skipped query due to schema mismatch (this is normal if a feature isn't installed): " . substr($query, 0, 50) . "...\n";
    }
}

echo "Database paths cleaned.\n";
echo "Migration complete.\n";
