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
     * When a platform editor instance slug is set in the session, connects
     * to the appropriate DB: '__platform__' → platform DB, otherwise the
     * named instance's DB via its config file.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $editorInstance = $_SESSION['_platform_editor_instance'] ?? null;

            if ($editorInstance === '__platform__'
                && class_exists(\Cruinn\Platform\PlatformAuth::class)
            ) {
                $config = \Cruinn\Platform\PlatformAuth::dbConfig();
            } elseif ($editorInstance !== null && $editorInstance !== '') {
                $config = self::loadInstanceDbConfig($editorInstance);
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
     * Switch the singleton to a specific instance's DB by slug.
     * Reads instance/{slug}/config.php for credentials.
     */
    public static function connectToInstance(string $slug): self
    {
        self::$instance = null;
        $config = self::loadInstanceDbConfig($slug);
        self::$instance = new self($config);
        return self::$instance;
    }

    /**
     * Load DB config array from an instance's config file.
     */
    private static function loadInstanceDbConfig(string $slug): array
    {
        $slug    = basename($slug); // prevent path traversal
        $cfgFile = dirname(__DIR__) . '/instance/' . $slug . '/config.php';
        if (!file_exists($cfgFile)) {
            throw new \RuntimeException("Instance config not found: {$slug}");
        }
        $cfg = require $cfgFile;
        $db  = $cfg['db'] ?? null;
        if (!is_array($db) || empty($db['name'])) {
            throw new \RuntimeException("Instance '{$slug}' has no valid db config.");
        }
        return $db;
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
