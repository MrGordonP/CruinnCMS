<?php
/**
 * CruinnCMS — Database Layer
 *
 * PDO wrapper with prepared statements throughout.
 * No ORM — direct SQL with parameter binding for safety and clarity.
 * Singleton pattern: Database::getInstance() returns the shared connection.
 */

namespace Cruinn;

use PDO;
use PDOStatement;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    /**
     * Private constructor — use getInstance().
     */
    private function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw $e; // Let callers decide how to handle — do not exit here
        }
    }

    /**
     * Get the singleton database instance.
     * When the platform editor mode session flag is set, connects to the
     * platform DB instead of the active instance DB.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
            if (!empty($_SESSION['_platform_editor_mode'])
                && class_exists(\Cruinn\Platform\PlatformAuth::class)
            ) {
                $config = \Cruinn\Platform\PlatformAuth::dbConfig();
            } elseif (!empty($_SESSION['_platform_editor_instance'])
                && (str_starts_with($requestPath, '/cms/editor') || str_starts_with($requestPath, '/admin/'))
            ) {
                $instanceSlug = basename((string) $_SESSION['_platform_editor_instance']);
                $cfgFile = dirname(__DIR__) . '/instance/' . $instanceSlug . '/config.php';
                if (is_file($cfgFile)) {
                    $cfg = require $cfgFile;
                    $config = $cfg['db'] ?? App::config('db');
                } else {
                    $config = App::config('db');
                }
            } else {
                $config = App::config('db');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Reset the singleton so the next call to getInstance() opens a fresh
     * connection.  Used when switching between instance and platform DB modes.
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Get the raw PDO connection (for transactions, etc.).
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    // ── Query Methods ─────────────────────────────────────────────

    /**
     * Execute a query and return all rows.
     *
     * Usage: $db->fetchAll('SELECT * FROM pages WHERE status = ?', ['published']);
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return a single row.
     *
     * Usage: $db->fetch('SELECT * FROM pages WHERE slug = ?', [$slug]);
     */
    public function fetch(string $sql, array $params = []): array|false
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Execute a query and return a single column value.
     *
     * Usage: $db->fetchColumn('SELECT COUNT(*) FROM members');
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE and return affected row count.
     *
     * Usage: $db->execute('UPDATE pages SET title = ? WHERE id = ?', [$title, $id]);
     */
    public function execute(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Insert a row and return the last insert ID.
     *
     * Usage: $id = $db->insert('pages', ['title' => 'About', 'slug' => 'about', ...]);
     */
    public function insert(string $table, array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->execute($sql, array_values($data));

        return $this->pdo->lastInsertId();
    }

    /**
     * Update rows matching a WHERE clause.
     * Returns the number of affected rows.
     *
     * Usage: $db->update('pages', ['title' => 'New Title'], 'id = ?', [$id]);
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";

        $stmt = $this->execute($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    /**
     * Delete rows matching a WHERE clause.
     * Returns the number of affected rows.
     *
     * Usage: $db->delete('pages', 'id = ?', [$id]);
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount();
    }

    // ── Transaction Helpers ───────────────────────────────────────

    /**
     * Run a callback inside a database transaction.
     * Automatically commits on success, rolls back on exception.
     *
     * Usage: $db->transaction(function() use ($db) { ... });
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
