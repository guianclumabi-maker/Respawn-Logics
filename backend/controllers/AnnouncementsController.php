<?php
require_once __DIR__ . '/../utils/Storage.php';

class AnnouncementsController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : ($_SESSION['tenant_id'] ?? null);
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            return;
        }
        // Require logged-in user
        if (!$this->currentUser) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        try {
            switch ($action) {
                case 'fetch_posts':
                    $this->fetchPosts();
                    break;
                case 'download_attachment':
                    $this->downloadAttachment();
                    break;
                case 'create_post':
                    if (!hasPermission('announcements.manage')) { http_response_code(403); echo json_encode(['success'=>false, 'error'=>'Denied']); return; }
                    $this->createPost();
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
                    break;
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function fetchPosts()
    {
        $stmt = $this->pdo->prepare("
            SELECT cp.id, cp.caption, cp.posted_by, cp.created_at, cp.tenant_id, 
                   (cp.image_path IS NOT NULL AND cp.image_path != '') as has_image,
                   u.full_name, u.job_title, u.profile_image 
            FROM company_posts cp
            JOIN users u ON cp.posted_by = u.email
            WHERE cp.tenant_id = ?
            ORDER BY cp.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$this->tenantId]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as &$post) {
            if ($post['has_image']) {
                $post['image_url'] = '../api/index.php?route=announcements&action=download_attachment&id=' . $post['id'];
            }
            unset($post['has_image']);
        }

        echo json_encode(['success' => true, 'data' => $posts]);
    }

    private function createPost()
    {
        // Only users with announcements.manage can post
        if (!hasPermission('announcements.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            return;
        }

        $caption = $_POST['caption'] ?? '';
        $imagePath = null;

        if (empty(trim($caption)) && empty($_FILES['image']['name'])) {
            echo json_encode(['success' => false, 'error' => 'Post cannot be empty']);
            return;
        }

        // Handle Image Upload
        if (!empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File exceeds 5MB limit']); return;
            }

            // Verify real MIME type for security
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            
            if (array_key_exists($mimeType, $allowedMimes)) {
                $storageBase = \App\Utils\Storage::resolveStorageBase(false, true);
                $storageDir = rtrim($storageBase, '/') . '/tenant_' . $this->tenantId . '/announcements';
                if (!is_dir($storageDir)) {
                    mkdir($storageDir, 0755, true);
                }

                $fileName = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mimeType];
                $targetPath = $storageDir . '/' . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $imagePath = 'tenant_' . $this->tenantId . '/announcements/' . $fileName;
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to upload image']);
                    return;
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid image format. Only JPG, PNG, GIF, and WebP are allowed.']);
                return;
            }
        }

        $this->pdo->beginTransaction();
        try {
            // Insert Post
            $stmt = $this->pdo->prepare("INSERT INTO company_posts (tenant_id, posted_by, caption, image_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$this->tenantId, $this->currentUser['email'], trim($caption), $imagePath]);
            $postId = $this->pdo->lastInsertId();

            // Fetch all active employees to send notifications
            $empStmt = $this->pdo->prepare("SELECT email FROM users WHERE tenant_id = ? AND employment_status = 'Active' AND id != ?");
            $empStmt->execute([$this->tenantId, $this->currentUser['id']]);
            $employees = $empStmt->fetchAll();

            $shortCaption = (strlen($caption) > 40) ? substr($caption, 0, 40) . '...' : $caption;
            $notifMessage = $shortCaption ?: 'A new image was posted.';

            foreach ($employees as $emp) {
                sendNotification(
                    $this->pdo, 
                    $this->tenantId, 
                    $emp['email'], 
                    "New Company Announcement", 
                    "Posted by " . $this->currentUser['full_name'] . ": " . $notifMessage, 
                    "info", 
                    "/pages/announcements.php"
                );
            }

            $this->pdo->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function downloadAttachment() {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo "Missing ID"; return; }

        $stmt = $this->pdo->prepare("SELECT image_path FROM company_posts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $this->tenantId]);
        $post = $stmt->fetch();

        if (!$post || empty($post['image_path'])) {
            http_response_code(404); echo "Attachment not found"; return;
        }

        $path = $post['image_path'];
        $storageBase = \App\Utils\Storage::resolveStorageBase(false, false);

        if (strpos($path, '/') === false) {
            // Legacy file
            $fullPath = __DIR__ . '/../../uploads/' . ltrim($path, '/');
        } else {
            $fullPath = rtrim($storageBase, '/') . '/' . ltrim($path, '/');
        }

        if (!file_exists($fullPath)) {
            http_response_code(404); echo "File not found"; return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}
