<?php
/**
 * CruinnCMS — ACP System Controller
 *
 * Core system settings: site, email, auth, security, system info,
 * database utilities, and the modules panel.
 * These panels are engine-level config — instance-neutral.
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\App;
use Cruinn\Auth;
use Cruinn\Database;
use Cruinn\Controllers\BaseController;
use Cruinn\Services\SettingsService;

class AcpSystemController extends BaseController
{
    private SettingsService $settings;

    public function __construct()
    {
        parent::__construct();
        $this->settings = new SettingsService();
    }

    // ── Site Settings ────────────────────────────────────────────

    public function site(): void
    {
        $data = [
            'title'    => 'Site Settings',
            'tab'      => 'site',
            'settings' => $this->getSettingsForPanel([
                'site.name', 'site.tagline', 'site.url', 'site.timezone',
                'site.logo', 'site.banner',
                'footer_text', 'registration_open', 'maintenance_mode',
            ]),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ];
        $this->renderAcp('admin/settings/site', $data);
    }

    public function saveSite(): void
    {
        $this->settings->setMany([
            'site.name'        => $this->input('site_name', ''),
            'site.tagline'     => $this->input('site_tagline', ''),
            'site.url'         => rtrim($this->input('site_url', ''), '/'),
            'site.timezone'    => $this->input('site_timezone', ''),
            'site.logo'        => $this->input('site_logo', ''),
            'site.banner'      => $this->input('site_banner', ''),
        ], 'site');

        $this->settings->setMany([
            'footer_text'       => $this->input('footer_text', ''),
            'registration_open' => $this->input('registration_open', '0'),
            'maintenance_mode'  => $this->input('maintenance_mode', '0'),
        ], 'general');

        Auth::flash('success', 'Site settings updated.');
        $this->redirect('/admin/settings/site');
    }

    // ── Email Settings ───────────────────────────────────────────

    public function email(): void
    {
        $data = [
            'title'    => 'Email Settings',
            'tab'      => 'email',
            'settings' => $this->getSettingsForPanel([
                'mail.host', 'mail.port', 'mail.username', 'mail.password',
                'mail.encryption', 'mail.from_email', 'mail.from_name',
                'imap.host', 'imap.port', 'imap.username', 'imap.password', 'imap.mailbox',
                'roundcube_url',
            ]),
        ];
        $this->renderAcp('admin/settings/email', $data);
    }

    public function saveEmail(): void
    {
        $this->settings->setMany([
            'mail.host'       => $this->input('mail_host', ''),
            'mail.port'       => $this->input('mail_port', ''),
            'mail.username'   => $this->input('mail_username', ''),
            'mail.encryption' => $this->input('mail_encryption', ''),
            'mail.from_email' => $this->input('mail_from_email', ''),
            'mail.from_name'  => $this->input('mail_from_name', ''),
        ], 'mail');

        $mailPass = $this->input('mail_password', '');
        if ($mailPass !== '') {
            $this->settings->set('mail.password', $mailPass, 'mail');
        }

        $this->settings->setMany([
            'imap.host'     => $this->input('imap_host', ''),
            'imap.port'     => $this->input('imap_port', ''),
            'imap.username' => $this->input('imap_username', ''),
            'imap.mailbox'  => $this->input('imap_mailbox', ''),
            'roundcube_url' => $this->input('roundcube_url', ''),
        ], 'mail');

        $imapPass = $this->input('imap_password', '');
        if ($imapPass !== '') {
            $this->settings->set('imap.password', $imapPass, 'mail');
        }

        Auth::flash('success', 'Email settings updated.');
        $this->redirect('/admin/settings/email');
    }

    public function testEmail(): void
    {
        try {
            $mailer = new \Cruinn\Mailer();
            $to = Auth::user()['email'] ?? '';
            if (!$to) {
                Auth::flash('error', 'No email address on your account.');
                $this->redirect('/admin/settings/email');
            }
            $mailer->send($to, 'ACP Test Email', '<p>This is a test email from the ACP. If you received it, your SMTP settings are correct.</p>');
            Auth::flash('success', "Test email sent to {$to}.");
        } catch (\Throwable $e) {
            Auth::flash('error', 'Email failed: ' . $e->getMessage());
        }
        $this->redirect('/admin/settings/email');
    }

    // ── Authentication Settings ──────────────────────────────────

    public function auth(): void
    {
        $data = [
            'title'    => 'Authentication',
            'tab'      => 'auth',
            'settings' => $this->getSettingsForPanel([
                'session.lifetime', 'session.name',
                'auth.password_min_length', 'auth.max_login_attempts',
                'auth.lockout_duration', 'auth.reset_token_expiry',
            ]),
        ];
        $this->renderAcp('admin/settings/auth', $data);
    }

    public function saveAuth(): void
    {
        $this->settings->setMany([
            'session.lifetime'         => $this->input('session_lifetime', '3600'),
            'session.name'             => $this->input('session_name', ''),
            'auth.password_min_length' => $this->input('auth_password_min_length', '8'),
            'auth.max_login_attempts'  => $this->input('auth_max_login_attempts', '5'),
            'auth.lockout_duration'    => $this->input('auth_lockout_duration', '900'),
            'auth.reset_token_expiry'  => $this->input('auth_reset_token_expiry', '3600'),
        ], 'auth');

        Auth::flash('success', 'Authentication settings updated.');
        $this->redirect('/admin/settings/auth');
    }

    // ── Security / Uploads ───────────────────────────────────────

    public function security(): void
    {
        $data = [
            'title'    => 'Security',
            'tab'      => 'security',
            'settings' => $this->getSettingsForPanel([
                'uploads.max_size', 'uploads.allowed', 'uploads.image_types',
            ]),
        ];
        $this->renderAcp('admin/settings/security', $data);
    }

    public function saveSecurity(): void
    {
        $this->settings->setMany([
            'uploads.max_size'    => $this->input('upload_max_size', '10'),
            'uploads.allowed'     => $this->input('upload_allowed_extensions', ''),
            'uploads.image_types' => $this->input('upload_image_types', ''),
        ], 'security');

        Auth::flash('success', 'Security settings updated.');
        $this->redirect('/admin/settings/security');
    }

    // ── System Information ───────────────────────────────────────

    public function system(): void
    {
        $db = Database::getInstance();

        $dbName = App::config('db.name');
        $dbSize = $db->fetch(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
             FROM information_schema.tables WHERE table_schema = ?",
            [$dbName]
        );

        $tableCount = $db->fetch(
            "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = ?",
            [$dbName]
        );

        $uploadsPath = CRUINN_PUBLIC . '/uploads';
        $uploadsSize = 0;
        if (is_dir($uploadsPath)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploadsPath));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $uploadsSize += $file->getSize();
                }
            }
        }

        $data = [
            'title'  => 'System Information',
            'tab'    => 'system',
            'system' => [
                'php_version'      => PHP_VERSION,
                'php_sapi'         => PHP_SAPI,
                'os'               => PHP_OS,
                'server_software'  => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'max_upload'       => ini_get('upload_max_filesize'),
                'max_post'         => ini_get('post_max_size'),
                'memory_limit'     => ini_get('memory_limit'),
                'max_execution'    => ini_get('max_execution_time'),
                'extensions'       => get_loaded_extensions(),
                'db_name'          => $dbName,
                'db_size_mb'       => $dbSize['size_mb'] ?? '0',
                'db_tables'        => $tableCount['cnt'] ?? 0,
                'uploads_path'     => $uploadsPath,
                'uploads_size_mb'  => round($uploadsSize / 1024 / 1024, 2),
                'uploads_writable' => is_writable($uploadsPath),
                'document_root'    => $_SERVER['DOCUMENT_ROOT'] ?? '',
                'config_writable'  => is_writable(dirname(__DIR__, 3) . '/config'),
            ],
        ];
        $this->renderAcp('admin/settings/system', $data);
    }

    // ── Database Utilities ───────────────────────────────────────

    public function database(): void
    {
        $db = Database::getInstance();
        $dbName = App::config('db.name');

        $tables = $db->fetchAll(
            "SELECT table_name, engine, table_rows, table_collation,
                    ROUND(data_length / 1024, 1) AS data_kb,
                    ROUND(index_length / 1024, 1) AS index_kb,
                    ROUND((data_length + index_length) / 1024, 1) AS total_kb
             FROM information_schema.tables
             WHERE table_schema = ?
             ORDER BY table_name",
            [$dbName]
        );

        $data = [
            'title'  => 'Database',
            'tab'    => 'database',
            'tables' => $tables,
            'dbName' => $dbName,
        ];
        $this->renderAcp('admin/settings/database', $data);
    }

    public function optimizeDatabase(): void
    {
        $db = Database::getInstance();
        $dbName = App::config('db.name');

        $tables = $db->fetchAll(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = ?",
            [$dbName]
        );

        foreach ($tables as $t) {
            $tableName = $t['table_name'] ?? $t['TABLE_NAME'];
            $db->execute("OPTIMIZE TABLE `{$tableName}`");
        }

        Auth::flash('success', 'All tables optimised.');
        $this->redirect('/admin/settings/database');
    }

    public function runQueue(): void
    {
        $script = dirname(__DIR__, 3) . '/tools/process-email-queue.php';
        if (!file_exists($script)) {
            Auth::flash('error', 'Queue processor script not found.');
            $this->redirect('/admin/settings/database');
        }

        $php = PHP_BINARY;
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --limit 200 2>&1';
        $output = [];
        exec($cmd, $output, $code);

        $summary = implode('\n', array_filter($output));
        if ($code === 0) {
            Auth::flash('success', 'Queue processed. ' . htmlspecialchars($summary, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        } else {
            Auth::flash('warning', 'Queue processor finished with warnings. ' . htmlspecialchars($summary, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $this->redirect('/admin/settings/database');
    }

    public function exportDatabase(): void
    {
        Auth::requireRole('admin');

        $dbName   = App::config('db.name');
        $filename = $dbName . '_' . date('Y-m-d_His') . '.sql';

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'wb');
        $this->dumpDatabaseSql($out);
        fclose($out);
        exit;
    }

    public function exportInstance(): void
    {
        Auth::requireRole('admin');

        if (!class_exists('ZipArchive')) {
            Auth::flash('error', 'PHP ZipArchive extension is not available on this server.');
            $this->redirect('/admin/settings/database');
        }

        $includeMedia = !empty($_POST['include_media']);

        $dbName  = App::config('db.name');
        $tmpDir  = sys_get_temp_dir();
        $sqlFile = $tmpDir . '/' . $dbName . '_' . date('Y-m-d_His') . '.sql';
        $zipFile = $tmpDir . '/cms-instance-' . date('Y-m-d_His') . '.zip';

        // Pure-PHP dump — no exec/mysqldump required
        $fh = fopen($sqlFile, 'wb');
        if ($fh === false) {
            Auth::flash('error', 'Could not write to temp directory.');
            $this->redirect('/admin/settings/database');
        }
        $this->dumpDatabaseSql($fh);
        fclose($fh);

        if (!file_exists($sqlFile) || filesize($sqlFile) === 0) {
            Auth::flash('error', 'Database dump produced no output.');
            $this->redirect('/admin/settings/database');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            unlink($sqlFile);
            Auth::flash('error', 'Could not create ZIP archive.');
            $this->redirect('/admin/settings/database');
        }

        $zip->addFile($sqlFile, 'database/' . basename($sqlFile));

        if ($includeMedia) {
            $uploadsDir = CRUINN_PUBLIC . '/uploads';
            if (is_dir($uploadsDir)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($uploadsDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $relativePath = 'uploads/' . substr($file->getRealPath(), strlen($uploadsDir) + 1);
                        $zip->addFile($file->getRealPath(), str_replace('\\', '/', $relativePath));
                    }
                }
            }
        }

        $mediaNote = $includeMedia
            ? "  uploads/    — Media files (copy to public/uploads/)\n"
            : "  (media files NOT included — copy public/uploads/ separately if needed)\n";
        $readme = App::config('site.name', 'Portal') . " — Instance Archive\n"
                . "Generated: " . date('Y-m-d H:i:s') . "\n\n"
                . "Contents:\n"
                . "  database/   — SQL dump (restore with: mysql dbname < file.sql)\n"
                . $mediaNote . "\n"
                . "Note: Credentials and secrets are NOT included in this archive.\n"
                . "Re-configure the instance config.php after restoring.\n";
        $zip->addFromString('README.txt', $readme);

        $zip->close();
        unlink($sqlFile);

        $siteName = preg_replace('/[^a-z0-9]+/i', '-', strtolower(App::config('site.name', 'instance')));
        $zipName = $siteName . '-' . date('Y-m-d') . '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipFile));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($zipFile);
        unlink($zipFile);
        exit;
    }

    // ── Database Editor ───────────────────────────────────────────

    /**
     * Pure-PHP MySQL dump. Writes a complete, importable SQL file to $fh.
     * No exec(), no mysqldump binary — works on any shared host.
     *
     * @param resource $fh  Writable stream (php://output or a file handle)
     */
    private function dumpDatabaseSql($fh): void
    {
        $db     = Database::getInstance();
        $pdo    = $db->pdo();
        $dbName = App::config('db.name');

        $write = fn(string $s) => fwrite($fh, $s);

        $write("-- CruinnCMS Database Dump\n");
        $write('-- Generated: ' . date('Y-m-d H:i:s') . "\n");
        $write('-- Database:  ' . $dbName . "\n");
        $write("\nSET NAMES utf8mb4;\n");
        $write("SET FOREIGN_KEY_CHECKS = 0;\n");
        $write("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        // Table list (sorted for stable output)
        $tables = $this->getTableNames($db, $dbName);
        sort($tables);

        foreach ($tables as $table) {
            // DROP + CREATE
            $write("-- ----------------------------\n");
            $write('-- Table: ' . $table . "\n");
            $write("-- ----------------------------\n");
            $write('DROP TABLE IF EXISTS `' . $table . '`;' . "\n");

            $row = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(\PDO::FETCH_NUM);
            $write($row[1] . ";\n\n");

            // Row count
            $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
            if ($count === 0) {
                continue;
            }

            // Fetch columns for header
            $colStmt = $pdo->query('SELECT * FROM `' . $table . '` LIMIT 0');
            $colCount = $colStmt->columnCount();
            $cols = [];
            for ($i = 0; $i < $colCount; $i++) {
                $meta = $colStmt->getColumnMeta($i);
                $cols[] = '`' . $meta['name'] . '`';
            }
            $colList = implode(', ', $cols);

            // Stream rows in batches of 500
            $offset = 0;
            $batch  = 500;
            while ($offset < $count) {
                $stmt = $pdo->query('SELECT * FROM `' . $table . '` LIMIT ' . $batch . ' OFFSET ' . $offset);
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
                if (empty($rows)) {
                    break;
                }

                $values = [];
                foreach ($rows as $r) {
                    $escaped = array_map(function ($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote((string) $v);
                    }, $r);
                    $values[] = '(' . implode(', ', $escaped) . ')';
                }

                $write('INSERT INTO `' . $table . '` (' . $colList . ") VALUES\n");
                $write(implode(",\n", $values) . ";\n");

                $offset += $batch;
            }
            $write("\n");
        }

        $write("SET FOREIGN_KEY_CHECKS = 1;\n");
        $write("-- End of dump\n");
    }

    /** Validate table name against information_schema and return list of valid table names. */
    private function getTableNames(Database $db, string $dbName): array
    {
        $rows = $db->fetchAll(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = ?",
            [$dbName]
        );
        return array_map(fn($r) => $r['table_name'] ?? $r['TABLE_NAME'], $rows);
    }

    /** Get the first PRIMARY KEY column for a table, or null if none. */
    private function getTablePk(Database $db, string $dbName, string $table): ?string
    {
        return $db->fetchColumn(
            "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
             ORDER BY ORDINAL_POSITION LIMIT 1",
            [$dbName, $table]
        ) ?: null;
    }

    /** Get all column names for a table. */
    private function getTableColumns(Database $db, string $dbName, string $table): array
    {
        $rows = $db->fetchAll(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
            [$dbName, $table]
        );
        return array_column($rows, 'COLUMN_NAME');
    }

    public function browseTable(string $table): void
    {
        $db     = Database::getInstance();
        $dbName = App::config('db.name');

        if (!in_array($table, $this->getTableNames($db, $dbName), true)) {
            Auth::flash('error', 'Unknown table.');
            $this->redirect('/admin/settings/database');
        }

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;
        $pkCol   = $this->getTablePk($db, $dbName, $table);

        $total   = (int)$db->fetchColumn("SELECT COUNT(*) FROM `{$table}`");
        $rows    = $db->fetchAll("SELECT * FROM `{$table}` LIMIT {$perPage} OFFSET {$offset}");
        $columns = !empty($rows) ? array_keys($rows[0]) : [];

        $data = [
            'title'   => "Browse: {$table}",
            'tab'     => 'database',
            'table'   => $table,
            'columns' => $columns,
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => (int)ceil($total / $perPage),
            'pkCol'   => $pkCol,
        ];
        $this->renderAcp('admin/settings/database-browse', $data);
    }

    public function editRow(string $table): void
    {
        $db     = Database::getInstance();
        $dbName = App::config('db.name');

        if (!in_array($table, $this->getTableNames($db, $dbName), true)) {
            Auth::flash('error', 'Unknown table.');
            $this->redirect('/admin/settings/database');
        }

        $pkCol = $this->getTablePk($db, $dbName, $table);
        if (!$pkCol) {
            Auth::flash('error', 'Cannot edit table with no primary key.');
            $this->redirect('/admin/settings/database/browse/' . urlencode($table));
        }

        $pkVal = $_GET['pk'] ?? '';
        $row   = $db->fetch("SELECT * FROM `{$table}` WHERE `{$pkCol}` = ? LIMIT 1", [$pkVal]);

        if (!$row) {
            Auth::flash('error', 'Row not found.');
            $this->redirect('/admin/settings/database/browse/' . urlencode($table));
        }

        $this->renderAcp('admin/settings/database-edit', [
            'title'  => "Edit row — {$table}",
            'tab'    => 'database',
            'table'  => $table,
            'pkCol'  => $pkCol,
            'pkVal'  => $pkVal,
            'row'    => $row,
            'page'   => (int)($_GET['page'] ?? 1),
        ]);
    }

    public function saveRow(string $table): void
    {
        $db     = Database::getInstance();
        $dbName = App::config('db.name');

        if (!in_array($table, $this->getTableNames($db, $dbName), true)) {
            Auth::flash('error', 'Unknown table.');
            $this->redirect('/admin/settings/database');
        }

        $pkCol  = $this->getTablePk($db, $dbName, $table);
        $pkVal  = $_POST['_pk'] ?? '';
        $page   = (int)($_POST['_page'] ?? 1);

        if (!$pkCol || $pkVal === '') {
            Auth::flash('error', 'Missing primary key.');
            $this->redirect('/admin/settings/database/browse/' . urlencode($table));
        }

        // Only set columns that actually exist in the table
        $validCols = $this->getTableColumns($db, $dbName, $table);
        $setClauses = [];
        $values     = [];

        foreach ($validCols as $col) {
            if ($col === $pkCol) continue; // never update PK
            if (!array_key_exists($col, $_POST)) continue;
            $setClauses[] = "`{$col}` = ?";
            $values[]     = $_POST[$col] === '' ? null : $_POST[$col];
        }

        if (empty($setClauses)) {
            Auth::flash('error', 'Nothing to update.');
            $this->redirect('/admin/settings/database/browse/' . urlencode($table) . '?page=' . $page);
        }

        $values[] = $pkVal;
        $db->execute(
            "UPDATE `{$table}` SET " . implode(', ', $setClauses) . " WHERE `{$pkCol}` = ?",
            $values
        );

        Auth::flash('success', 'Row updated.');
        $this->redirect('/admin/settings/database/browse/' . urlencode($table) . '?page=' . $page);
    }

    public function deleteRow(string $table): void
    {
        $db     = Database::getInstance();
        $dbName = App::config('db.name');

        if (!in_array($table, $this->getTableNames($db, $dbName), true)) {
            Auth::flash('error', 'Unknown table.');
            $this->redirect('/admin/settings/database');
        }

        $pkCol = $this->getTablePk($db, $dbName, $table);
        $pkVal = $_POST['_pk'] ?? '';
        $page  = (int)($_POST['_page'] ?? 1);

        if (!$pkCol || $pkVal === '') {
            Auth::flash('error', 'Missing primary key.');
            $this->redirect('/admin/settings/database/browse/' . urlencode($table));
        }

        $db->execute("DELETE FROM `{$table}` WHERE `{$pkCol}` = ?", [$pkVal]);

        Auth::flash('success', 'Row deleted.');
        $this->redirect('/admin/settings/database/browse/' . urlencode($table) . '?page=' . $page);
    }

    public function queryPage(): void
    {
        $data = [
            'title'   => 'Query Runner',
            'tab'     => 'database',
            'sql'     => '',
            'results' => null,
            'error'   => null,
            'affected'=> null,
        ];
        $this->renderAcp('admin/settings/database-query', $data);
    }

    public function runQuery(): void
    {
        $sql   = trim($this->input('sql', ''));
        $db    = Database::getInstance();
        $results  = null;
        $affected = null;
        $error    = null;

        try {
            $stmt = $db->pdo()->prepare($sql);
            $stmt->execute();
            if (preg_match('/^\s*SELECT\s/i', $sql)) {
                $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $affected = $stmt->rowCount();
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $data = [
            'title'    => 'Query Runner',
            'tab'      => 'database',
            'sql'      => $sql,
            'results'  => $results,
            'affected' => $affected,
            'error'    => $error,
        ];
        $this->renderAcp('admin/settings/database-query', $data);
    }

    // ── Modules Panel ─────────────────────────────────────────────

    public function modules(): void
    {
        Auth::requireRole('admin');

        $all     = \Cruinn\Modules\ModuleRegistry::all();
        $modules = [];

        try {
            $dbRows = $this->db->fetchAll('SELECT slug, status, settings FROM module_config');
        } catch (\Throwable) {
            $dbRows = [];
        }
        $dbBySlug = [];
        foreach ($dbRows as $row) {
            $dbBySlug[$row['slug']] = $row;
        }

        foreach ($all as $slug => $def) {
            $row      = $dbBySlug[$slug] ?? null;
            $status   = $row['status']   ?? 'discovered';
            $settings = json_decode($row['settings'] ?? '{}', true) ?? [];

            $totalMigrations   = count($def['migrations']);
            $appliedMigrations = 0;
            $pendingMigrations = 0;
            foreach ($def['migrations'] as $path) {
                $filename = basename($path);
                try {
                    $applied = $this->db->fetch(
                        'SELECT id FROM module_migrations WHERE module = ? AND filename = ?',
                        [$slug, $filename]
                    );
                    if ($applied) {
                        $appliedMigrations++;
                    } else {
                        $pendingMigrations++;
                    }
                } catch (\Throwable) {
                    $pendingMigrations++;
                }
            }

            $modules[$slug] = [
                'def'      => $def,
                'status'   => $status,
                'settings' => $settings,
                'migrations' => [
                    'total'   => $totalMigrations,
                    'applied' => $appliedMigrations,
                    'pending' => $pendingMigrations,
                ],
            ];
        }

        $this->renderAcp('admin/settings/modules', [
            'title'   => 'Modules',
            'tab'     => 'modules',
            'modules' => $modules,
        ]);
    }

    public function toggleModule(string $slug): void
    {
        Auth::requireRole('admin');

        $def = \Cruinn\Modules\ModuleRegistry::get($slug);
        if (!$def) {
            Auth::flash('error', "Module '{$slug}' not found.");
            $this->redirect('/admin/settings/modules');
        }

        try {
            $row = $this->db->fetch(
                'SELECT status FROM module_config WHERE slug = ?', [$slug]
            );

            if (!$row) {
                $this->db->execute(
                    'INSERT INTO module_config (slug, status, settings) VALUES (?, ?, ?)',
                    [$slug, 'active', '{}']
                );
                Auth::flash('success', "Module '{$def['name']}' activated.");
            } else {
                $newStatus = ($row['status'] === 'active') ? 'offline' : 'active';
                $this->db->execute(
                    'UPDATE module_config SET status = ?, updated_at = NOW() WHERE slug = ?',
                    [$newStatus, $slug]
                );
                $label = ($newStatus === 'active') ? 'activated' : 'taken offline';
                Auth::flash('success', "Module '{$def['name']}' {$label}.");
            }
        } catch (\Throwable $e) {
            Auth::flash('error', 'Failed to update module status: ' . $e->getMessage());
        }

        $this->redirect('/admin/settings/modules');
    }

    public function saveModuleSettings(string $slug): void
    {
        Auth::requireRole('admin');

        $def = \Cruinn\Modules\ModuleRegistry::get($slug);
        if (!$def) {
            Auth::flash('error', "Module '{$slug}' not found.");
            $this->redirect('/admin/settings/modules');
        }

        $schema = $def['settings_schema'] ?? [];
        $new    = [];
        foreach ($schema as $field) {
            $key  = $field['key']  ?? '';
            $type = $field['type'] ?? 'text';
            if ($key === '') {
                continue;
            }
            if ($type === 'checkbox') {
                $new[$key] = isset($_POST[$key]);
            } else {
                $new[$key] = trim($_POST[$key] ?? '');
            }
        }

        try {
            $row = $this->db->fetch(
                'SELECT id FROM module_config WHERE slug = ?', [$slug]
            );
            if ($row) {
                $this->db->execute(
                    'UPDATE module_config SET settings = ?, updated_at = NOW() WHERE slug = ?',
                    [json_encode($new), $slug]
                );
            } else {
                $this->db->execute(
                    'INSERT INTO module_config (slug, status, settings) VALUES (?, ?, ?)',
                    [$slug, 'discovered', json_encode($new)]
                );
            }
            Auth::flash('success', "Settings for '{$def['name']}' saved.");
        } catch (\Throwable $e) {
            Auth::flash('error', 'Failed to save settings: ' . $e->getMessage());
        }

        $this->redirect('/admin/settings/modules');
    }

    public function applyModuleMigrations(string $slug): void
    {
        Auth::requireRole('admin');

        $def = \Cruinn\Modules\ModuleRegistry::get($slug);
        if (!$def) {
            Auth::flash('error', "Module '{$slug}' not found.");
            $this->redirect('/admin/settings/modules');
        }

        $applied = 0;
        $errors  = [];
        $pdo     = $this->db->pdo();

        foreach ($def['migrations'] as $path) {
            $filename = basename($path);

            try {
                $row = $this->db->fetch(
                    'SELECT id FROM module_migrations WHERE module = ? AND filename = ?',
                    [$slug, $filename]
                );
                if ($row) {
                    continue;
                }
            } catch (\Throwable) {
                // module_migrations doesn't exist yet — proceed
            }

            if (!file_exists($path)) {
                $errors[] = "Missing file: {$filename}";
                continue;
            }

            $sql = file_get_contents($path);
            try {
                $pdo->exec($sql);
                $this->db->execute(
                    'INSERT INTO module_migrations (module, filename, applied_at) VALUES (?, ?, NOW())',
                    [$slug, $filename]
                );
                $applied++;
            } catch (\Throwable $e) {
                $errors[] = "{$filename}: " . $e->getMessage();
                break;
            }
        }

        if ($errors) {
            Auth::flash('error', 'Migration error: ' . implode('; ', $errors));
        } else {
            $msg = $applied > 0
                ? "Applied {$applied} migration(s) for '{$def['name']}'."
                : "All migrations for '{$def['name']}' already applied.";
            Auth::flash('success', $msg);
        }

        $this->redirect('/admin/settings/modules');
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function getSettingsForPanel(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->settings->get($key, '');
        }
        return $result;
    }
}
