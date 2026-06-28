<?php

/**
 * MySQL-backed session handler.
 *
 * Stores PHP sessions in the `php_sessions` MySQL table so that all
 * Railway container replicas share the same session data. Without this,
 * file-based sessions (stored in each container's local /tmp) are
 * invisible to other replicas, causing users to be kicked to #/login.
 */
class MySQLSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $lifetime;

    public function __construct(PDO $pdo, int $lifetime = 3600)
    {
        $this->pdo      = $pdo;
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT data FROM php_sessions WHERE id = ? AND expires_at > NOW() LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    public function write(string $id, string $data): bool
    {
        // Compute expiry with MySQL's OWN clock (NOW()) instead of PHP's date()/time().
        // read() checks `expires_at > NOW()`, also using MySQL's clock. If write used
        // PHP's clock and PHP/MySQL are in different timezones, every session can look
        // instantly expired on read -> sessions silently come back empty -> users get
        // bounced to login. Using NOW() on both sides makes them always agree.
        $stmt = $this->pdo->prepare(
            "INSERT INTO php_sessions (id, data, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)"
        );
        return $stmt->execute([$id, $data, $this->lifetime]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM php_sessions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare("DELETE FROM php_sessions WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
