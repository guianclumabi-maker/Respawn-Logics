<?php

class AnnouncementsController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser();
        $this->tenantId = $this->currentUser['tenant_id'] ?? $_SESSION['tenant_id'] ?? '1';
    }

    public function handleRequest($action)
    {
        // Require logged-in user
        if (!$this->currentUser) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        switch ($action) {
            case 'fetch_posts':
                $this->fetchPosts();
                break;
            case 'create_post':
                $this->createPost();
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                break;
        }
    }

    private function fetchPosts()
    {
        $stmt = $this->pdo->prepare("
            SELECT cp.*, u.full_name, u.job_title, u.profile_image 
            FROM company_posts cp
            JOIN users u ON cp.posted_by = u.email
            WHERE cp.tenant_id = ?
            ORDER BY cp.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$this->tenantId]);
        $posts = $stmt->fetchAll();

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
            $uploadDir = __DIR__ . '/../../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = uniqid('post_') . '_' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $fileName;

            // Verify real MIME type for security
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (in_array($mimeType, $allowedMimes)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imagePath = $fileName;
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
}
