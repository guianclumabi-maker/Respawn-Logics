<?php
/**
 * Migrator — minimal versioned migration runner (Phase 2.1).
 *
 * Records every migration script that has run in a `migration_history` table, so each runs
 * exactly once and future NON-idempotent migrations are safe. Existing scripts stay as-is
 * (they're `CREATE TABLE IF NOT EXISTS`-style and idempotent); this just adds tracking +
 * ordered, skip-if-applied execution instead of blindly re-including everything each deploy.
 *
 * Usage:
 *   $migrator = new Migrator($pdo);
 *   $ran = $migrator->run($orderedScriptNames, __DIR__);   // returns names actually run
 */
class Migrator
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Create the history table if missing. */
    public function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `migration_history` (
                `id`         BIGINT PRIMARY KEY AUTO_INCREMENT,
                `migration`  VARCHAR(255) NOT NULL UNIQUE,
                `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        );
    }

    public function isApplied(string $name): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM `migration_history` WHERE `migration` = ? LIMIT 1");
        $stmt->execute([$name]);
        return (bool)$stmt->fetchColumn();
    }

    public function markApplied(string $name): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO `migration_history` (`migration`) VALUES (?)");
        $stmt->execute([$name]);
    }

    /**
     * Run each migration script once, in the given order.
     * Scripts are plain PHP files that use the global $pdo and require MIGRATION_SAFE
     * (unchanged from today). Returns the list of scripts that actually executed this call.
     *
     * @param string[] $scripts ordered script filenames
     * @param string   $dir     directory the scripts live in
     */
    public function run(array $scripts, string $dir): array
    {
        $this->ensureTable();
        $ran = [];
        foreach ($scripts as $script) {
            if ($this->isApplied($script)) {
                continue;
            }
            $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $script;
            if (!file_exists($path)) {
                continue; // tolerate optional/missing scripts, same as the current runner
            }
            global $pdo; // scripts reference the global connection
            require $path;
            $this->markApplied($script);
            $ran[] = $script;
        }
        return $ran;
    }

    /**
     * Force-run scripts ignoring history (used by the test harness, which drops + recreates
     * the DB every run and therefore wants a clean, unconditional apply).
     */
    public function runAlways(array $scripts, string $dir): array
    {
        $ran = [];
        foreach ($scripts as $script) {
            $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $script;
            if (!file_exists($path)) {
                continue;
            }
            global $pdo;
            require $path;
            $ran[] = $script;
        }
        return $ran;
    }
}
