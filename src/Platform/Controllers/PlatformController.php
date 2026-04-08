<?php
/**
 * CMS Platform â€” Platform Controller
 *
 * Handles the top-level /cms/ area: platform login, logout, and the
 * platform dashboard which shows instance health and management links.
 * In single-instance mode this surfaces stats for the one local instance.
 * In multi-instance mode it loads an instances registry (future).
 */

namespace Cruinn\Platform\Controllers;

use Cruinn\App;
use Cruinn\Database;
use Cruinn\Modules\ModuleRegistry;
use Cruinn\Platform\PlatformAuth;
use Cruinn\Template;

class PlatformController
{
    private Template $view;

    public function __construct()
    {
        $this->view = new Template();
        $this->view->setLayout(null); // Platform has its own standalone HTML â€” do not wrap in instance layout
    }

    // â”€â”€ Login / Logout â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function showLogin(): void
    {
        if (!PlatformAuth::isInitialized()) {
            header('Location: /cms/install');
            exit;
        }

        if (PlatformAuth::check()) {
            header('Location: /cms/dashboard');
            exit;
        }

        echo $this->view->render('platform/login', [
            'title'  => 'Platform Login',
            'error'  => $_SESSION['_platform_login_error'] ?? null,
        ]);
        unset($_SESSION['_platform_login_error']);
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (PlatformAuth::login($username, $password)) {
            header('Location: /cms/dashboard');
            exit;
        }

        // Small delay to deter brute force
        sleep(1);
        $_SESSION['_platform_login_error'] = 'Invalid credentials.';
        header('Location: /cms/login');
        exit;
    }

    public function logout(): void
    {
        PlatformAuth::logout();
        header('Location: /cms/login');
        exit;
    }

    // â”€â”€ Install wizard â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function showInstall(): void
    {
        if (PlatformAuth::isInitialized()) {
            header('Location: /cms/login');
            exit;
        }
        echo $this->view->render('platform/install', [
            'title'  => 'CruinnCMS Setup',
            'errors' => $_SESSION['_install_errors'] ?? [],
        ]);
        unset($_SESSION['_install_errors']);
    }

    public function install(): void
    {
        if (PlatformAuth::isInitialized()) {
            header('Location: /cms/dashboard');
            exit;
        }

        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $passwordC = $_POST['password_confirm'] ?? '';
        $db = [
            'host'     => trim($_POST['db_host'] ?? 'localhost'),
            'port'     => (int) ($_POST['db_port'] ?? 3306),
            'name'     => trim($_POST['db_name'] ?? ''),
            'user'     => trim($_POST['db_user'] ?? ''),
            'password' => $_POST['db_pass'] ?? '',
            'charset'  => 'utf8mb4',
        ];
        $errors = [];
        if (empty($username)) {
            $errors[] = 'Username is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $passwordC) {
            $errors[] = 'Passwords do not match.';
        }
        if (empty($db['name']) || empty($db['user'])) {
            $errors[] = 'Database name and username are required.';
        }

        if (!empty($errors)) {
            $_SESSION['_install_errors'] = $errors;
            header('Location: /cms/install');
            exit;
        }

        // Create DB if possible, then apply migrations
        try {
            try {
                $rootDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']);
                $rootPdo = new \PDO($rootDsn, $db['user'], $db['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $rootPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (\PDOException) {
                // Shared hosting â€” DB must already exist
            }

            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
            $pdo = new \PDO($dsn, $db['user'], $db['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 10,
            ]);

            // Apply the platform schema only (instance_core.sql is applied per-instance at provisioning time)
            $schemaDir = dirname(__DIR__, 3) . '/schema';
            $files = [
                $schemaDir . '/platform.sql',
            ];
            foreach ($files as $file) {
                $sql = preg_replace(['/\/\*.*?\*\//s', '/--[^\n]*/'], '', file_get_contents($file));
                foreach (explode(';', $sql) as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '') continue;
                    try { $pdo->exec($stmt); } catch (\PDOException $e) {
                        $nativeCode = (int)($e->errorInfo[1] ?? 0);
                        if (!in_array($nativeCode, [1060,1061,1050,1054,1091,1062], true)) {
                            throw $e;
                        }
                    }
                }
            }
        } catch (\PDOException $e) {
            $_SESSION['_install_errors'] = ['Database error: ' . $e->getMessage()];
            header('Location: /cms/install');
            exit;
        }

        // Write CruinnCMS.php with initialized = true, real DB, and chosen credentials
        $cfgPath = dirname(__DIR__, 3) . '/config/CruinnCMS.php';
        $existing = file_exists($cfgPath) ? require $cfgPath : [];
        $existing['initialized']   = true;
        $existing['username']      = $username;
        $existing['password_hash'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $existing['db']            = $db;
        unset($existing['site_url']); // site URL belongs to instances, not the platform

        $export  = "<?php\n// CruinnCMS Platform Config â€” installed " . date('Y-m-d H:i:s') . "\nreturn " . var_export($existing, true) . ";\n";
        file_put_contents($cfgPath, $export);

        // Write config.local.php for the platform DB connection (used by App bootstrap)
        $escape  = fn(string $v) => str_replace(["'\\"], ["\\'", "\\\\"], $v);
        $local   = implode("\n", [
            "<?php",
            "// CruinnCMS â€” Local config â€” generated " . date('Y-m-d H:i:s'),
            "return [",
            "    'db' => [",
            "        'host'     => '" . $escape($db['host'])     . "',",
            "        'port'     => " . $db['port']               . ",",
            "        'name'     => '" . $escape($db['name'])     . "',",
            "        'user'     => '" . $escape($db['user'])     . "',",
            "        'password' => '" . $escape($db['password']) . "',",
            "        'charset'  => 'utf8mb4',",
            "    ],",
            "    'site' => ['debug' => false],",
            "    'trusted_proxy' => '127.0.0.1,::1',",
            "    'mail' => ['host'=>'localhost','port'=>587,'username'=>'','password'=>'','encryption'=>'tls','from_email'=>'noreply@example.com','from_name'=>'CruinnCMS'],",
            "];",
        ]) . "\n";
        file_put_contents(dirname(__DIR__, 3) . '/config/config.local.php', $local);

        header('Location: /cms/login');
        exit;
    }

    // â”€â”€ Platform â†’ Instance passthrough â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Single-instance passthrough: if the platform session is active, find
     * the first active admin user, log them in to the instance via Auth::loginById(),
     * and redirect to the requested admin destination.
     *
     * This is safe because platform auth requires the file-based credential
     * (i.e. server access), which is a higher trust level than instance auth.
     * The target URL is validated to only allow /admin/* paths.
     */
    public function passthrough(): void
    {
        // Validate & whitelist destination — only allow /admin/* to prevent redirect abuse
        $to = $_GET['to'] ?? '/admin/dashboard';
        if (!preg_match('#^/admin(/[\w/_\-\.]*)?$#', $to)) {
            $to = '/admin/dashboard';
        }

        // Already logged in to instance admin? Just redirect.
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . $to);
            exit;
        }

        // Platform auth required for auto-login.
        // Not present (e.g. cross-domain call) — send to instance login instead.
        if (!PlatformAuth::check()) {
            header('Location: /login');
            exit;
        }

        $db   = Database::getInstance();
        $user = $db->fetch(
            "SELECT id FROM users WHERE role = 'admin' AND active = 1 ORDER BY id ASC LIMIT 1"
        );

        if (!$user) {
            // No admin user in DB — redirect to CMS with error
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'No active admin user found in the instance database.'];
            header('Location: /cms/dashboard');
            exit;
        }

        \Cruinn\Auth::loginById((int) $user['id']);
        header('Location: ' . $to);
        exit;
    }

    // â”€â”€ Dashboard â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function dashboard(): void
    {
        if ($this->serveEditedPlatformPage('dashboard')) { return; }

        $instances = $this->gatherAllInstances();
        $engine    = $this->gatherEngineData();

        echo $this->view->render('platform/dashboard', [
            'title'     => 'Platform Dashboard',
            'instances' => $instances,
            'engine'    => $engine,
            'multi'     => count($instances) > 1,
            'username'  => PlatformAuth::username(),
        ]);
    }

    public function editorPicker(): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $instance         = isset($_GET['instance'])    ? basename((string) $_GET['instance'])  : null;
        $pageId           = isset($_GET['page'])         ? (int) $_GET['page']                  : null;
        $platformMode     = !empty($_GET['platformMode']);
        $platformPageSlug = isset($_GET['platformPage'])
            ? preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $_GET['platformPage']))
            : null;

        // ── No instance selected — show landing prompt ─────────────────
        if ($instance === null || $instance === '') {
            // Clear any leftover platform editor mode from a previous session
            unset($_SESSION['_platform_editor_mode']);
            \Cruinn\Database::resetInstance();
            echo $this->view->render('platform/editor-picker', [
                'title'       => 'Editor',
                'username'    => PlatformAuth::username(),
                'editorReady' => false,
                'editorError' => null,
            ]);
            return;
        }

        // ── CruinnCMS Platform instance ─────────────────────────────────
        if ($instance === '__platform__') {
            // Switch the DB singleton to the platform DB.
            // resetInstance() must be called unconditionally because
            // App::loadSettingsOverrides() opens a connection to the instance DB
            // before the session is started, and that connection would otherwise
            // persist for the lifetime of this request.
            $_SESSION['_platform_editor_mode'] = true;
            \Cruinn\Database::resetInstance();
            $db = \Cruinn\Database::getInstance();
            // Ensure the block editor tables exist in the platform DB
            // (idempotent — IF NOT EXISTS guards every statement)
            try {
                $schemaDir = dirname(__DIR__, 3) . '/schema';
                $sql = preg_replace(['/\/\*.*?\*\//s', '/--[^\n]*/'], '', file_get_contents($schemaDir . '/platform.sql'));
                foreach (explode(';', $sql) as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '') continue;
                    try { $db->pdo()->exec($stmt); } catch (\PDOException $e) {
                        $n = (int)($e->errorInfo[1] ?? 0);
                        if (!in_array($n, [1060,1061,1050,1054,1091,1062], true)) { throw $e; }
                    }
                }
            } catch (\Throwable $e) {
                error_log('Platform schema bootstrap failed: ' . $e->getMessage());
            }

            // ── ?file= parameter: open a source file in the block editor ─
            // Validate the path, upsert a pages row, redirect to ?page=<id>
            $fileParam = isset($_GET['file'])
                ? ltrim(str_replace(['..', '\\'], ['', '/'], (string) $_GET['file']), '/')
                : null;
            if ($fileParam !== null && $pageId === null) {
                $rcRoot     = dirname(__DIR__, 3);
                $absPath    = realpath($rcRoot . '/' . $fileParam);
                $rcRootReal = realpath($rcRoot);
                $allowedExt = ['php', 'css', 'js', 'html'];
                if ($absPath && $rcRootReal
                    && str_starts_with($absPath, $rcRootReal . DIRECTORY_SEPARATOR)
                    && is_file($absPath)
                    && in_array(strtolower(pathinfo($absPath, PATHINFO_EXTENSION)), $allowedExt, true)
                ) {
                    $slug  = '_cms_src_' . md5($fileParam);
                    $title = basename($fileParam);
                    $db->execute(
                        'INSERT INTO pages (title, slug, render_mode, render_file, status)
                             VALUES (?, ?, "file", ?, "draft")
                             ON DUPLICATE KEY UPDATE title = VALUES(title), render_file = VALUES(render_file)',
                        [$title, $slug, '@cms/' . $fileParam]
                    );
                    $row = $db->fetch('SELECT id FROM pages WHERE slug = ? LIMIT 1', [$slug]);
                    if ($row) {
                        header('Location: /cms/editor?instance=__platform__&page=' . (int) $row['id']);
                        exit;
                    }
                }
                // Invalid/disallowed path — fall through to empty state
            }

            // ── Platform-specific page load ──────────────────────────────
            $page         = null;
            $hasDraft     = false;
            $state        = null;
            $cruinnHtml   = '';
            $cruinnCss    = '';
            $isFileMode   = false;
            $docHtmlBlock = null;
            $docHeadBlock = null;
            $docBodyBlock = null;

            if ($pageId !== null) {
                $pageRow = $db->fetch('SELECT * FROM pages WHERE id = ? LIMIT 1', [$pageId]);
                if ($pageRow) {
                    $page     = $pageRow;
                    $stateRow = $db->fetch('SELECT * FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
                    $hasDraft = !empty($stateRow);
                    $state    = $stateRow ?: null;

                    $flat = $hasDraft
                        ? $db->fetchAll(
                            'SELECT * FROM cruinn_draft_blocks
                              WHERE page_id = ? AND is_active = 1 AND is_deletion = 0
                              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                            [$pageId]
                        )
                        : $db->fetchAll(
                            'SELECT * FROM cruinn_blocks
                              WHERE page_id = ?
                              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                            [$pageId]
                        );

                    // ── Auto-import: parse file content into typed blocks on first open ──
                    $renderMode = $page['render_mode'] ?? 'cruinn';
                    if (in_array($renderMode, ['html', 'file'], true) && empty($flat)) {
                        $importSvc = new \Cruinn\Services\ImportService();
                        $absPath   = $renderMode === 'file'
                            ? $this->resolveRenderFilePath($page['render_file'] ?? '')
                            : null;
                        $importedBlocks = $importSvc->autoImport($page, $pageId, $absPath);
                        if (!empty($importedBlocks)) {
                            try {
                                $importSvc->persistImportedBlocks($importedBlocks, $pageId, $db);
                            } catch (\Throwable $e) {
                                error_log('Platform Import failed: ' . $e->getMessage());
                            }
                            $stateRow = $db->fetch('SELECT * FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
                            $hasDraft = !empty($stateRow);
                            $state    = $stateRow ?: null;
                            $flat     = $db->fetchAll(
                                'SELECT * FROM cruinn_draft_blocks
                                  WHERE page_id = ? AND is_active = 1 AND is_deletion = 0
                                  ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                                [$pageId]
                            );
                        }
                    }

                    // Extract doc-level metadata blocks
                    foreach ($flat as $row) {
                        if ($row['block_type'] === 'doc-html')     { $docHtmlBlock = $row; }
                        elseif ($row['block_type'] === 'doc-head') { $docHeadBlock = $row; }
                        elseif ($row['block_type'] === 'doc-body') { $docBodyBlock = $row; }
                    }

                    $renderMode = $page['render_mode'] ?? 'cruinn';
                    $isFileMode = ($renderMode === 'file');

                    $editorSvc  = new \Cruinn\Services\EditorRenderService();
                    $cruinnHtml = $editorSvc->buildCanvasHtml($flat, $db);
                    $cruinnCss  = $editorSvc->buildCanvasCss($flat);
                }
            }

            // Nav: platform DB pages + source file groups
            $sitePages       = $db->fetchAll("SELECT id, title, slug, render_mode FROM pages ORDER BY title ASC");
            $navSourceGroups = $this->buildSourceFileGroups();

            echo $this->view->render('platform/editor-picker', [
                'title'                => $page ? 'Editor — ' . $page['title'] : 'Editor — CruinnCMS Platform',
                'username'             => PlatformAuth::username(),
                'editorReady'          => true,
                'editorPageBase'       => '/cms/editor?instance=__platform__',
                'isPlatformMode'       => true,
                'apiBase'              => '/cms/editor',
                'platformPages'        => [],
                'editorError'          => null,
                'page'                 => $page,
                'hasDraft'             => $hasDraft,
                'state'                => $state,
                'cruinnHtml'           => $cruinnHtml,
                'cruinnCss'            => $cruinnCss,
                'menus'                => [],
                'isZonePage'           => false,
                'zoneName'             => null,
                'isTemplatePage'       => false,
                'templateSlugName'     => null,
                'templateId'           => null,
                'headerPageId'         => null,
                'footerPageId'         => null,
                'headerPages'          => [],
                'footerPages'          => [],
                'sitePages'            => $sitePages,
                'navSourceGroups'      => $navSourceGroups,
                'navTemplates'         => [],
                'navMenus'             => [],
                'navCssFiles'          => [],
                'navPhpGroups'         => [],
                'headerZoneHtml'       => '',
                'headerZoneCss'        => '',
                'footerZoneHtml'       => '',
                'footerZoneCss'        => '',
                'templateZones'        => [],
                'templateCanvasPageId' => null,
                'templateCanvasHtml'   => '',
                'templateCanvasCss'    => '',
                'startInCodeView'      => false,
                'htmlContent'          => null,
                'isFileMode'           => $isFileMode,
                'docHtmlBlock'         => $docHtmlBlock,
                'docHeadBlock'         => $docHeadBlock,
                'docBodyBlock'         => $docBodyBlock,
            ]);
            return;
        }
            // ── Switching away from platform mode — restore instance DB ─
            if (!empty($_SESSION['_platform_editor_mode'])) {
                unset($_SESSION['_platform_editor_mode']);
                \Cruinn\Database::resetInstance();
            }

            // Only the active instance is wired into Database::getInstance
            $activeDir  = App::instanceDir();
            $activeName = $activeDir ? basename($activeDir) : null;
            $isActive   = ($instance === $activeName);

            if (!$isActive) {
                echo $this->view->render('platform/editor-picker', [
                    'title'       => 'Editor',
                    'username'    => PlatformAuth::username(),
                    'editorReady' => false,
                    'editorError' => 'The "' . htmlspecialchars($instance, ENT_QUOTES, 'UTF-8')
                        . '" instance is not the currently active instance. Only the active instance can be edited from here.',
                ]);
                return;
            }

            $db = \Cruinn\Database::getInstance();

            // Silently log in as the first active admin user so editor AJAX routes pass Auth::requireRole
            $adminUser = $db->fetch(
                "SELECT id FROM users WHERE role = 'admin' AND active = 1 ORDER BY id ASC LIMIT 1"
            );
            if (!$adminUser) {
                echo $this->view->render('platform/editor-picker', [
                    'title'       => 'Editor',
                    'username'    => PlatformAuth::username(),
                    'editorReady' => false,
                    'editorError' => 'No active admin user found in this instance.',
                ]);
                return;
            }
            \Cruinn\Auth::loginById((int) $adminUser['id']);

        // ── Nav queries (same as CruinnController::openEditor) ──────────
        $headerPages = $db->fetchAll(
            "SELECT p.id, p.title, p.slug, pt.name AS template_name
             FROM page_templates pt
             JOIN pages p ON p.id = pt.canvas_page_id
             WHERE JSON_CONTAINS(pt.zones, '\"header\"')
             ORDER BY pt.sort_order, pt.name"
        );
        $hp0 = $db->fetch("SELECT id FROM pages WHERE slug = '_header' LIMIT 1");
        if ($hp0) {
            array_unshift($headerPages, [
                'id' => (int) $hp0['id'], 'title' => 'Header Zone Page',
                'slug' => '_header', 'template_name' => null,
            ]);
        }
        $footerPages = $db->fetchAll(
            "SELECT p.id, p.title, p.slug, pt.name AS template_name
             FROM page_templates pt
             JOIN pages p ON p.id = pt.canvas_page_id
             WHERE JSON_CONTAINS(pt.zones, '\"footer\"')
             ORDER BY pt.sort_order, pt.name"
        );
        $fp0 = $db->fetch("SELECT id FROM pages WHERE slug = '_footer' LIMIT 1");
        if ($fp0) {
            array_unshift($footerPages, [
                'id' => (int) $fp0['id'], 'title' => 'Footer Zone Page',
                'slug' => '_footer', 'template_name' => null,
            ]);
        }
        $sitePages = $db->fetchAll(
            "SELECT id, title, slug, render_mode FROM pages
             WHERE slug NOT LIKE '\\_\\_%'
             ORDER BY title ASC"
        );
        $navTemplates = $db->fetchAll(
            "SELECT pt.id, pt.name, pt.slug, pt.canvas_page_id, p.id AS editor_page_id
             FROM page_templates pt
             LEFT JOIN pages p ON p.id = pt.canvas_page_id
             WHERE pt.slug NOT LIKE '\\_\\_%'
             ORDER BY pt.sort_order, pt.name"
        );
        try {
            $navMenus = $db->fetchAll('SELECT id, name, block_page_id FROM menus ORDER BY name ASC');
        } catch (\Exception $e) {
            $navMenus = $db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC');
        }

        $cssDir   = dirname(__DIR__, 3) . '/public/css';
        $cssFiles = [];
        foreach (glob($cssDir . '/*.css') ?: [] as $f) {
            $cssFiles[] = basename($f);
        }
        sort($cssFiles);

        $tplBase    = dirname(__DIR__, 3) . '/templates';
        $tplExclude = ['/admin/', '/platform/'];
        $tplIter    = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tplBase, \FilesystemIterator::SKIP_DOTS)
        );
        $phpGroups = [];
        foreach ($tplIter as $tplFile) {
            if ($tplFile->getExtension() !== 'php') continue;
            $rel  = str_replace('\\', '/', substr($tplFile->getPathname(), strlen($tplBase) + 1));
            $skip = false;
            foreach ($tplExclude as $ex) {
                if (str_contains('/' . $rel, $ex)) { $skip = true; break; }
            }
            if ($skip) continue;
            $parts = explode('/', $rel);
            $group = count($parts) > 1 ? $parts[0] : 'root';
            $phpGroups[$group][] = $rel;
        }
        ksort($phpGroups);
        foreach ($phpGroups as &$g) { sort($g); }
        unset($g);

        // ── Collect platform template list for platform mode ────────────
        $platformPages  = [];
        $tplDir         = dirname(__DIR__, 3) . '/templates/platform';
        if ($platformMode) {
            foreach (glob($tplDir . '/*.php') ?: [] as $_ptf) {
                $_ptSlug = basename($_ptf, '.php');
                if ($_ptSlug === 'layout' || $_ptSlug === 'editor-picker') { continue; }
                $platformPages[] = [
                    'slug' => $_ptSlug,
                    'name' => ucwords(str_replace(['-', '_'], ' ', $_ptSlug)),
                ];
            }
            usort($platformPages, fn($a, $b) => strcmp($a['name'], $b['name']));
        }

        // ── Platform page: find or create on first open ─────────────────
        if ($platformMode && $platformPageSlug !== null && $pageId === null) {
            $tplFile = $tplDir . '/' . $platformPageSlug . '.php';
            if (file_exists($tplFile)) {
                $ptSlug  = '_cms_platform_' . $platformPageSlug;
                $ptRow   = $db->fetch('SELECT id, body_html FROM pages WHERE slug = ? LIMIT 1', [$ptSlug]);
                if (!$ptRow) {
                    $renderedHtml = $this->renderPlatformTemplate($platformPageSlug);
                    $db->execute(
                        "INSERT INTO pages (title, slug, render_mode, body_html, status, template, created_at, updated_at)
                         VALUES (?, ?, 'html', ?, 'published', 'none', NOW(), NOW())",
                        [ucwords(str_replace(['-', '_'], ' ', $platformPageSlug)), $ptSlug, $renderedHtml]
                    );
                    $pageId = (int) $db->pdo()->lastInsertId();
                } else {
                    $pageId = (int) $ptRow['id'];
                    if (empty($ptRow['body_html'])) {
                        $renderedHtml = $this->renderPlatformTemplate($platformPageSlug);
                        $db->execute('UPDATE pages SET body_html = ? WHERE id = ?', [$renderedHtml, $pageId]);
                    }
                }
            }
        }

        // ── Default (no page) editor state ──────────────────────────────
        $page             = null;
        $hasDraft         = false;
        $state            = null;
        $cruinnHtml       = '';
        $cruinnCss        = '';
        $menus            = $db->fetchAll('SELECT id, name FROM menus ORDER BY name ASC');
        $isZonePage       = false;
        $zoneName         = null;
        $isTemplatePage   = false;
        $templateSlugName = null;
        $templateId       = null;
        $headerPageId     = null;
        $footerPageId     = null;
        $headerZoneHtml   = '';
        $headerZoneCss    = '';
        $footerZoneHtml   = '';
        $footerZoneCss    = '';
        $templateZones         = [];
        $templateCanvasPageId  = null;
        $templateCanvasHtml    = '';
        $templateCanvasCss     = '';
        $startInCodeView       = false;
        $htmlContent           = null;

        // ── Load specific page if requested ─────────────────────────────
        if ($pageId !== null) {
            $pageRow = $db->fetch('SELECT * FROM pages WHERE id = ? LIMIT 1', [$pageId]);
            if ($pageRow) {
                $page     = $pageRow;
                $stateRow = $db->fetch('SELECT * FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
                $hasDraft = !empty($stateRow);
                $state    = $stateRow ?: null;

                $flat = $hasDraft
                    ? $db->fetchAll(
                        'SELECT * FROM cruinn_draft_blocks
                          WHERE page_id = ? AND is_active = 1 AND is_deletion = 0
                          ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                        [$pageId]
                    )
                    : $db->fetchAll(
                        'SELECT * FROM cruinn_blocks
                          WHERE page_id = ?
                          ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                        [$pageId]
                    );

                $editorSvc  = new \Cruinn\Services\EditorRenderService();
                $cruinnHtml = $editorSvc->buildCanvasHtml($flat, $db);
                $cruinnCss  = $editorSvc->buildCanvasCss($flat);

                // ── Auto-import: parse source HTML into typed blocks on first open ──
                $renderMode = $page['render_mode'] ?? 'cruinn';
                if (in_array($renderMode, ['html', 'file'], true) && empty($flat)) {
                    $importSvc      = new \Cruinn\Services\ImportService();
                    $importedBlocks = [];

                    if ($renderMode === 'file') {
                        $filePath = $page['render_file'] ?? '';
                        $absPath  = dirname(__DIR__, 3) . '/public' . $filePath;
                        if ($filePath !== '' && file_exists($absPath)) {
                            $html = file_get_contents($absPath);
                            // Strip PHP tags — PHP rendering handled by php-include blocks
                            if (str_contains($filePath, '.php')) {
                                $html = $this->stripPhpTags($html);
                            }
                            $importedBlocks = $importSvc->parseDocument($html, $pageId);
                        }
                    } else {
                        $srcHtml = $page['body_html'] ?? '';
                        if ($srcHtml !== '') {
                            $importedBlocks = $importSvc->parseFragment($srcHtml, $pageId);
                        }
                    }

                    if (!empty($importedBlocks)) {
                        $pdo = $db->pdo();
                        $pdo->beginTransaction();
                        try {
                            $db->execute(
                                'INSERT INTO cruinn_page_state
                                     (page_id, current_edit_seq, max_edit_seq, last_edited_at)
                                 VALUES (?, 1, 1, NOW())
                                 ON DUPLICATE KEY UPDATE
                                     current_edit_seq = 1, max_edit_seq = 1, last_edited_at = NOW()',
                                [$pageId]
                            );
                            foreach ($importedBlocks as $b) {
                                $db->execute(
                                    'INSERT INTO cruinn_draft_blocks
                                         (page_id, edit_seq, block_id, block_type, inner_html,
                                          css_props, block_config, sort_order, parent_block_id,
                                          is_active, is_deletion)
                                     VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, 1, 0)',
                                    [
                                        $pageId, $b['block_id'], $b['block_type'],
                                        $b['inner_html'], $b['css_props'], $b['block_config'],
                                        $b['sort_order'], $b['parent_block_id'],
                                    ]
                                );
                            }
                            $pdo->commit();
                        } catch (\Throwable $e) {
                            $pdo->rollBack();
                            error_log('Platform Import failed: ' . $e->getMessage());
                        }

                        $stateRow = $db->fetch('SELECT * FROM cruinn_page_state WHERE page_id = ?', [$pageId]);
                        $hasDraft = !empty($stateRow);
                        $state    = $stateRow ?: null;
                        $flat     = $db->fetchAll(
                            'SELECT * FROM cruinn_draft_blocks
                              WHERE page_id = ? AND is_active = 1 AND is_deletion = 0
                              ORDER BY ISNULL(parent_block_id), parent_block_id, sort_order ASC',
                            [$pageId]
                        );

                        $editorSvc  = new \Cruinn\Services\EditorRenderService();
                        $cruinnHtml = $editorSvc->buildCanvasHtml($flat, $db);
                        $cruinnCss  = $editorSvc->buildCanvasCss($flat);
                    }
                }

                // Extract doc-level metadata blocks from the flat list
                $docHtmlBlock     = null;
                $docHeadBlock     = null;
                $docBodyBlock     = null;
                $hasImportedBlocks = false;
                foreach ($flat as $row) {
                    if ($row['block_type'] === 'doc-html')     { $docHtmlBlock  = $row; }
                    elseif ($row['block_type'] === 'doc-head') { $docHeadBlock  = $row; }
                    elseif ($row['block_type'] === 'doc-body') { $docBodyBlock  = $row; }
                    $cfg = json_decode($row['block_config'] ?? '{}', true) ?: [];
                    if (isset($cfg['_tag'])) { $hasImportedBlocks = true; }
                }
                // ── End auto-import ──────────────────────────────────────

                $isZonePage       = str_starts_with($page['slug'] ?? '', '_');
                $zoneName         = $isZonePage ? ltrim($page['slug'], '_') : null;
                $isTemplatePage   = str_starts_with($page['slug'] ?? '', '_tpl_');
                $templateSlugName = $isTemplatePage ? substr($page['slug'], 5) : null;

                if ($isTemplatePage) {
                    $tplRow     = $db->fetch(
                        'SELECT id FROM page_templates WHERE canvas_page_id = ? LIMIT 1', [$pageId]
                    );
                    $templateId = $tplRow ? (int) $tplRow['id'] : null;
                }

                if (!$isZonePage) {
                    $hp = $db->fetch("SELECT id FROM pages WHERE slug = '_header' LIMIT 1");
                    $fp = $db->fetch("SELECT id FROM pages WHERE slug = '_footer' LIMIT 1");
                    $headerPageId = $hp ? (int) $hp['id'] : null;
                    $footerPageId = $fp ? (int) $fp['id'] : null;

                    $cruinnSvc = new \Cruinn\Services\CruinnRenderService();
                    if ($headerPageId && $cruinnSvc->hasPublished($headerPageId)) {
                        $headerZoneHtml = $cruinnSvc->buildHtml($headerPageId);
                        $headerZoneCss  = $cruinnSvc->buildCss($headerPageId);
                    }
                    if ($footerPageId && $cruinnSvc->hasPublished($footerPageId)) {
                        $footerZoneHtml = $cruinnSvc->buildHtml($footerPageId);
                        $footerZoneCss  = $cruinnSvc->buildCss($footerPageId);
                    }

                    if (!$isTemplatePage) {
                        $pageTemplateSlug = $page['template'] ?? 'default';
                        if ($pageTemplateSlug && $pageTemplateSlug !== 'none') {
                            $tplRow = $db->fetch(
                                'SELECT id, canvas_page_id FROM page_templates WHERE slug = ? LIMIT 1',
                                [$pageTemplateSlug]
                            );
                            if ($tplRow && !empty($tplRow['canvas_page_id'])) {
                                $templateCanvasPageId = (int) $tplRow['canvas_page_id'];
                                if ($cruinnSvc->hasPublished($templateCanvasPageId)) {
                                    $templateCanvasHtml = $cruinnSvc->buildHtml($templateCanvasPageId);
                                    $templateCanvasCss  = $cruinnSvc->buildCss($templateCanvasPageId);
                                    $zoneRows = $db->fetchAll(
                                        "SELECT block_config FROM cruinn_blocks
                                          WHERE page_id = ? AND block_type = 'zone' AND parent_block_id IS NULL",
                                        [$templateCanvasPageId]
                                    );
                                    foreach ($zoneRows as $zr) {
                                        $cfg = json_decode($zr['block_config'] ?? '{}', true) ?: [];
                                        $zn  = $cfg['zone_name'] ?? 'main';
                                        if (!in_array($zn, $templateZones, true)) {
                                            $templateZones[] = $zn;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $startInCodeView = !$hasImportedBlocks && $renderMode === 'html';
                $htmlContent     = $startInCodeView ? ($page['body_html'] ?? '') : null;
            }
        }

        echo $this->view->render('platform/editor-picker', [
            'title'                => $page ? 'Editor — ' . $page['title'] : 'Editor',
            'username'             => PlatformAuth::username(),
            'editorReady'          => true,
            'editorPageBase'       => '/cms/editor?instance=' . rawurlencode($instance)
                                      . ($platformMode ? '&platformMode=1' : ''),
            'isPlatformMode'       => $platformMode,
            'platformPages'        => $platformPages,
            'editorError'          => null,
            'page'                 => $page,
            'hasDraft'             => $hasDraft,
            'state'                => $state,
            'cruinnHtml'           => $cruinnHtml,
            'cruinnCss'            => $cruinnCss,
            'menus'                => $menus,
            'isZonePage'           => $isZonePage,
            'zoneName'             => $zoneName,
            'isTemplatePage'       => $isTemplatePage,
            'templateSlugName'     => $templateSlugName,
            'templateId'           => $templateId,
            'headerPageId'         => $headerPageId,
            'footerPageId'         => $footerPageId,
            'headerPages'          => $headerPages,
            'footerPages'          => $footerPages,
            'sitePages'            => $sitePages,
            'navTemplates'         => $navTemplates,
            'navMenus'             => $navMenus,
            'navCssFiles'          => $cssFiles,
            'navPhpGroups'         => $phpGroups,
            'headerZoneHtml'       => $headerZoneHtml,
            'headerZoneCss'        => $headerZoneCss,
            'footerZoneHtml'       => $footerZoneHtml,
            'footerZoneCss'        => $footerZoneCss,
            'templateZones'        => $templateZones,
            'templateCanvasPageId' => $templateCanvasPageId,
            'templateCanvasHtml'   => $templateCanvasHtml,
            'templateCanvasCss'    => $templateCanvasCss,
            'startInCodeView'      => $startInCodeView,
            'htmlContent'          => $htmlContent,
            'isFileMode'           => isset($renderMode) && $renderMode === 'file',
            'docHtmlBlock'         => $docHtmlBlock ?? null,
            'docHeadBlock'         => $docHeadBlock ?? null,
            'docBodyBlock'         => $docBodyBlock ?? null,
        ]);
    }

    public function editorFiles(): void
    {
        if (!PlatformAuth::check()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $instance = $_GET['instance'] ?? '';
        if ($instance === '') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'No instance specified']);
            exit;
        }

        $activeName = App::instanceDir();
        $activeName = $activeName ? basename($activeName) : null;
        $isActive   = ($instance === $activeName);

        try {
            [$pdo] = $this->resolveDbConnection($isActive ? null : $instance);
            $stmt  = $pdo->prepare('SELECT id, title, slug FROM pages ORDER BY title ASC');
            $stmt->execute();
            $files = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $files[] = [
                    'id'    => (int) $row['id'],
                    'title' => $row['title'],
                    'slug'  => $row['slug'],
                    'type'  => 'page',
                ];
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['files' => $files, 'isActive' => $isActive]);
        } catch (\Throwable $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // â”€â”€ Settings (change password) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function showSettings(): void
    {
        if ($this->serveEditedPlatformPage('settings')) { return; }

        echo $this->view->render('platform/settings', [
            'title'    => 'Platform Settings',
            'username' => PlatformAuth::username(),
            'saved'    => !empty($_SESSION['_platform_settings_saved']),
        ]);
        unset($_SESSION['_platform_settings_saved']);
    }

    public function saveSettings(): void
    {
        $newPassword = $_POST['new_password'] ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 12) {
            $_SESSION['_platform_settings_error'] = 'Password must be at least 12 characters.';
            header('Location: /cms/settings');
            exit;
        }

        if ($newPassword !== $confirm) {
            $_SESSION['_platform_settings_error'] = 'Passwords do not match.';
            header('Location: /cms/settings');
            exit;
        }

        $hash    = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $cfgPath = dirname(__DIR__, 3) . '/config/CruinnCMS.php';

        $existing = file_exists($cfgPath) ? require $cfgPath : [];
        $existing['password_hash'] = $hash;

        $export = "<?php\n// CruinnCMS Platform Config \u2014 updated " . date('Y-m-d H:i:s') . "\nreturn " . var_export($existing, true) . ";\n";

        if (file_put_contents($cfgPath, $export) === false) {
            $_SESSION['_platform_settings_error'] = 'Could not write config/CruinnCMS.php. Check file permissions.';
            header('Location: /cms/settings');
            exit;
        }

        $_SESSION['_platform_settings_saved'] = true;
        header('Location: /cms/settings');
        exit;
    }

    // â”€â”€ Instance Switching â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * POST /cms/instances/{name}/toggle
     * Creates instance/{slug}/.active (brings online) or deletes it (takes offline).
     * The .active file is the per-instance online flag; its absence triggers maintenance mode.
     */
    public function toggleInstance(string $name): void
    {
        if (!PlatformAuth::check()) {
            header('Location: /cms/login');
            exit;
        }

        $name    = basename($name); // prevent path traversal
        $rootDir = dirname(__DIR__, 3);
        $instDir = $rootDir . '/instance/' . $name;

        if (!is_dir($instDir)) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => "Instance '{$name}' not found."];
            header('Location: /cms/dashboard');
            exit;
        }

        $onlineFile = $instDir . '/.active';
        if (is_file($onlineFile)) {
            unlink($onlineFile);
            $msg = "Instance '{$name}' taken offline.";
        } else {
            file_put_contents($onlineFile, '1');
            $msg = "Instance '{$name}' brought online.";
        }

        $_SESSION['_platform_flash'] = ['type' => 'success', 'message' => $msg];
        header('Location: /cms/dashboard');
        exit;
    }

    // â”€â”€ Instance Provisioning â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function showProvisionInstance(): void
    {
        if (!PlatformAuth::check()) {
            header('Location: /cms/login');
            exit;
        }

        echo $this->view->render('platform/provision', [
            'title'  => 'Provision Instance',
            'mode'   => $_GET['mode'] ?? 'new',
            'errors' => $_SESSION['_provision_errors'] ?? [],
            'values' => $_SESSION['_provision_values'] ?? [],
        ]);
        unset($_SESSION['_provision_errors'], $_SESSION['_provision_values']);
    }

    public function provisionInstance(): void
    {
        if (!PlatformAuth::check()) {
            header('Location: /cms/login');
            exit;
        }

        $slug     = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug']     ?? '')));
        $name     = trim($_POST['name']     ?? '');
        $siteUrl  = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $db = [
            'host'     => trim($_POST['db_host'] ?? 'localhost'),
            'port'     => (int) ($_POST['db_port'] ?? 3306),
            'name'     => trim($_POST['db_name'] ?? ''),
            'user'     => trim($_POST['db_user'] ?? ''),
            'password' => $_POST['db_pass'] ?? '',
            'charset'  => 'utf8mb4',
        ];
        $adminEmail    = trim($_POST['admin_email']    ?? '');
        $adminPassword = $_POST['admin_password']      ?? '';
        $adminName     = trim($_POST['admin_name']     ?? '');

        $errors = [];
        if (empty($slug))              { $errors[] = 'Instance slug is required (lowercase letters, numbers, hyphens).'; }
        if (empty($name))              { $errors[] = 'Instance name is required.'; }
        if (empty($siteUrl))           { $errors[] = 'Site URL is required.'; }
        if (empty($db['name']))        { $errors[] = 'Database name is required.'; }
        if (empty($db['user']))        { $errors[] = 'Database user is required.'; }
        if (empty($adminEmail))        { $errors[] = 'Admin email is required.'; }
        if (strlen($adminPassword) < 8){ $errors[] = 'Admin password must be at least 8 characters.'; }
        if (empty($adminName))         { $errors[] = 'Admin display name is required.'; }

        $rootDir = dirname(__DIR__, 3);

        if (empty($errors) && is_dir($rootDir . '/instance/' . $slug)) {
            $errors[] = "An instance with slug '{$slug}' already exists.";
        }

        if (!empty($errors)) {
            $_SESSION['_provision_errors'] = $errors;
            $_SESSION['_provision_values'] = $_POST;
            header('Location: /cms/instances/new');
            exit;
        }

        try {
            // Use platform credentials to create the DB and apply schema.
            // Instance credentials are for day-to-day use — the MySQL user may not exist yet.
            $platformCfg = PlatformAuth::dbConfig();
            $rootPdo = null;
            try {
                $rootDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $platformCfg['host'], $platformCfg['port']);
                $rootPdo = new \PDO($rootDsn, $platformCfg['user'], $platformCfg['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $rootPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (\PDOException) {
                // Shared hosting — DB must already exist
            }

            // Connect to the new instance DB using platform credentials
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $platformCfg['host'], $platformCfg['port'], $db['name']);
            $pdo = new \PDO($dsn, $platformCfg['user'], $platformCfg['password'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT            => 10,
            ]);

            // If instance DB user differs from platform user, create it in MySQL and grant access
            if ($rootPdo !== null && $db['user'] !== $platformCfg['user']) {
                try {
                    $quotedUser = $rootPdo->quote($db['user']);
                    $quotedPass = $rootPdo->quote($db['password']);
                    $quotedDb   = '`' . str_replace('`', '', $db['name']) . '`';
                    $rootPdo->exec("CREATE USER IF NOT EXISTS {$quotedUser}@'localhost' IDENTIFIED BY {$quotedPass}");
                    $rootPdo->exec("GRANT ALL PRIVILEGES ON {$quotedDb}.* TO {$quotedUser}@'localhost'");
                    $rootPdo->exec('FLUSH PRIVILEGES');
                } catch (\PDOException) {
                    // Platform user lacks CREATE USER privilege — instance user must be created manually
                }
            }

            // Apply instance core schema
            $schemaFile = $rootDir . '/schema/instance_core.sql';
            $sql = preg_replace(['/\/\*.*?\*\//s', '/--[^\n]*/'], '', file_get_contents($schemaFile));
            foreach (explode(';', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                try { $pdo->exec($stmt); } catch (\PDOException $e) {
                    $nativeCode = (int)($e->errorInfo[1] ?? 0);
                    if (!in_array($nativeCode, [1060,1061,1050,1054,1091,1062], true)) {
                        throw $e;
                    }
                }
            }

            // Create the first admin user
            $hash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare(
                "INSERT INTO users (email, password_hash, display_name, role, active) VALUES (?, ?, ?, 'admin', 1)"
            );
            $stmt->execute([$adminEmail, $hash, $adminName]);

        } catch (\PDOException $e) {
            $_SESSION['_provision_errors'] = ['Database error: ' . $e->getMessage()];
            $_SESSION['_provision_values'] = $_POST;
            header('Location: /cms/instances/new');
            exit;
        }

        // Create instance directory and config
        $instDir = $rootDir . '/instance/' . $slug;
        mkdir($instDir, 0755, true);

        $escape  = fn(string $v) => str_replace(["'", "\\"], ["\\'", "\\\\"], $v);
        $cfg = implode("\n", [
            "<?php",
            "// CruinnCMS Instance Config â€” {$slug} â€” generated " . date('Y-m-d H:i:s'),
            "return [",
            "    'db' => [",
            "        'host'     => '" . $escape($db['host'])     . "',",
            "        'port'     => " . $db['port']               . ",",
            "        'name'     => '" . $escape($db['name'])     . "',",
            "        'user'     => '" . $escape($db['user'])     . "',",
            "        'password' => '" . $escape($db['password']) . "',",
            "        'charset'  => 'utf8mb4',",
            "    ],",
            "    'site' => [",
            "        'name'     => '" . $escape($name)     . "',",
            "        'url'      => '" . $escape($siteUrl)  . "',",
            "        'timezone' => 'UTC',",
            "        'debug'    => false,",
            "    ],",
            "];",
        ]) . "\n";
        file_put_contents($instDir . '/config.php', $cfg);

        // Register in platform instances table
        try {
            $platDb = Database::getInstance();
            $platDb->insert('instances', [
                'slug'        => $slug,
                'name'        => $name,
                'db_host'     => $db['host'],
                'db_port'     => $db['port'],
                'db_name'     => $db['name'],
                'db_user'     => $db['user'],
                'db_password' => $db['password'],
                'site_url'    => $siteUrl,
                'status'      => 'active',
            ]);
        } catch (\Throwable) {
            // Non-fatal â€” instance is functional, registry entry is cosmetic
        }

        // Bring this instance online immediately after provisioning
        file_put_contents($instDir . '/.active', '1');

        $_SESSION['_platform_flash'] = ['type' => 'success', 'message' => "Instance '{$name}' provisioned and brought online."];
        header('Location: /cms/dashboard');
        exit;
    }

    // â”€â”€ Data Providers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Discover all instances in the instance/ directory and gather their data.
     * The currently active instance gets live DB stats; others get config info only.
     */
    private function gatherAllInstances(): array
    {
        $rootDir       = dirname(__DIR__, 3);
        $instancesBase = $rootDir . '/instance/';

        if (!is_dir($instancesBase)) {
            return [];
        }

        $dirs = glob($instancesBase . '*', GLOB_ONLYDIR) ?: [];
        if (empty($dirs)) {
            return [];
        }

        $instances = [];
        foreach ($dirs as $dir) {
            $name = basename($dir);
            if ($name === '') {
                continue;
            }
            $instances[] = $this->gatherInstanceData($dir, $name);
        }

        return $instances;
    }

    /**
     * Gather config and live DB stats for one instance.
     *
     * @param string $instanceDir   Absolute path to the instance directory
     * @param string $instanceName  Folder name (slug)
     */
    private function gatherInstanceData(string $instanceDir, string $instanceName): array
    {
        $cfg     = [];
        $cfgFile = $instanceDir . '/config.php';
        if (file_exists($cfgFile)) {
            $cfg = require $cfgFile;
        }

        $siteCfg = $cfg['site'] ?? [];
        $dbCfg   = $cfg['db']   ?? [];
        $isOnline = is_file($instanceDir . '/.active');

        // Build hostnames list
        $rawHost   = $cfg['hostname'] ?? [];
        $hostnames = is_array($rawHost)
            ? $rawHost
            : (is_string($rawHost) && $rawHost !== '' ? [$rawHost] : []);

        // Build base URL for action links (scheme + hostname, no trailing slash)
        $siteUrl = $siteCfg['url'] ?? '';
        if ($siteUrl === '' && !empty($hostnames)) {
            $siteUrl = 'https://' . $hostnames[0];
        }
        $baseUrl = rtrim($siteUrl, '/');

        $dbConnected = false;
        $stats       = [];

        if (!empty($dbCfg['host']) && !empty($dbCfg['name'])) {
            try {
                $pdo = new \PDO(
                    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                        $dbCfg['host'],
                        $dbCfg['port'] ?? 3306,
                        $dbCfg['name']
                    ),
                    $dbCfg['user'] ?? '',
                    $dbCfg['password'] ?? '',
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 3]
                );
                $dbConnected = true;
                $stats['tables'] = (int) $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
                )->fetchColumn();
                $stats['db_mb'] = (float) $pdo->query(
                    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
                     FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
                )->fetchColumn();
                try { $stats['pages'] = (int) $pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn(); }
                catch (\Throwable) {}
                try { $stats['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); }
                catch (\Throwable) {}
                try { $stats['last_activity'] = $pdo->query('SELECT MAX(created_at) FROM activity_log')->fetchColumn(); }
                catch (\Throwable) {}
            } catch (\Throwable $e) {
                $stats['db_error'] = $e->getMessage();
            }
        }

        return [
            'folder_name'  => $instanceName,
            'name'         => $siteCfg['name'] ?? $instanceName,
            'url'          => $siteCfg['url']  ?? '',
            'base_url'     => $baseUrl,
            'hostnames'    => $hostnames,
            'db_name'      => $dbCfg['name']   ?? '',
            'db_host'      => $dbCfg['host']   ?? 'localhost',
            'db_connected' => $dbConnected,
            'online'       => $isOnline,
            'stats'        => $stats,
            'backups'      => $this->listInstanceBackups($instanceName),
        ];
    }

    private function fetchDbStats(Database $db): array
    {
        $stats = [];
        // info_schema queries work on any DB â€” always safe
        try {
            $stats['tables'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
            );
            $stats['db_mb'] = (float) $db->fetchColumn(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
                 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
            );
        } catch (\Throwable $e) {
            $stats['db_error'] = $e->getMessage();
            return $stats;
        }
        // Instance-specific tables â€” only query if they exist
        try { $stats['pages'] = (int) $db->fetchColumn('SELECT COUNT(*) FROM pages'); }
        catch (\Throwable) { /* not an instance DB */ }
        try { $stats['users'] = (int) $db->fetchColumn('SELECT COUNT(*) FROM users'); }
        catch (\Throwable) { /* not an instance DB */ }
        try { $stats['last_activity'] = $db->fetchColumn('SELECT MAX(created_at) FROM activity_log'); }
        catch (\Throwable) { /* not an instance DB */ }
        return $stats;
    }

    private function gatherEngineData(): array
    {
        $modules = ModuleRegistry::all();
        $activeModules  = array_filter($modules, fn($slug) => ModuleRegistry::isActive($slug), ARRAY_FILTER_USE_KEY);

        // Disk usage: check both storage/ (new) and uploads/ (legacy)
        $rootPublic = dirname(__DIR__, 3) . '/public';
        $storageDirs = [
            $rootPublic . '/storage',
            $rootPublic . '/uploads',
        ];
        $storageBytes = 0;
        foreach ($storageDirs as $dir) {
            if (!is_dir($dir)) continue;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) $storageBytes += $file->getSize();
            }
        }
        $storageMb = round($storageBytes / 1024 / 1024, 2);

        // Writability checks
        $paths = [
            'storage/'   => $rootPublic . '/storage',
            'uploads/'   => $rootPublic . '/uploads',
            'config/'    => dirname(__DIR__, 3) . '/config',
        ];
        $writable = [];
        foreach ($paths as $label => $path) {
            if (!is_dir($path)) continue;
            $writable[$label] = is_writable($path);
        }

        return [
            'php_version'     => PHP_VERSION,
            'php_sapi'        => PHP_SAPI,
            'modules_total'   => count($modules),
            'modules_active'  => count($activeModules),
            'module_slugs'    => array_column(array_values($activeModules), 'slug'),
            'uploads_mb'      => $storageMb,
            'writable'        => $writable,
        ];
    }

    // â”€â”€ Database Editor â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Resolve a PDO connection for the given instance folder name (or active instance).
     *
     * Special values:
     *   null / ''         → active instance DB (via Database::getInstance())
     *   '__platform__'    → platform DB (via PlatformAuth::dbConfig() credentials)
     *   '{folder}'        → named inactive instance DB (reads instance/{folder}/config.php)
     *
     * Returns [PDO, dbName, instanceLabel] or throws on failure.
     */

    /**
     * Render a platform PHP template with dummy data and return the full HTML string.
     * Used to capture the initial visual state of a platform page for block import.
     */
    private function renderPlatformTemplate(string $slug): string
    {
        $dummyData = [
            'title'     => ucwords(str_replace(['-', '_'], ' ', $slug)),
            'username'  => PlatformAuth::username() ?: 'platform',
            'instances' => [],
            'engine'    => ['version' => '1.0-rc', 'php_version' => PHP_VERSION, 'environment' => 'RC'],
            'multi'     => false,
            'settings'  => [],
            'saved'     => false,
            'error'     => null,
            'message'   => null,
            'content'   => '',
        ];
        set_error_handler(static fn() => null);
        try {
            $html = $this->view->render('platform/' . $slug, $dummyData);
        } catch (\Throwable $e) {
            $html = '<!DOCTYPE html><html><body><p>Render failed: '
                  . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p></body></html>';
        } finally {
            restore_error_handler();
        }
        return $html;
    }

    /**
     * Check if a platform page has a published block-edited version in the active instance DB.
     * If so, output the reconstructed HTML and return true. Returns false if not found or no instance.
     */
    private function serveEditedPlatformPage(string $slug): bool
    {
        if (App::instanceDir() === null) { return false; }
        try {
            $db  = Database::getInstance();
            $row = $db->fetch('SELECT id FROM pages WHERE slug = ? LIMIT 1', ['_cms_platform_' . $slug]);
            if (!$row) { return false; }
            $blocks = $db->fetchAll(
                'SELECT * FROM cruinn_blocks WHERE page_id = ? ORDER BY sort_order',
                [(int) $row['id']]
            );
            if (empty($blocks)) { return false; }
            $svc = new \Cruinn\Services\ImportService();
            echo $svc->reconstructDocument($blocks);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
    private function resolveDbConnection(?string $instanceFolder): array
    {
        $rootDir = dirname(__DIR__, 3);

        // Sentinel: explicit request for the platform (engine) DB
        if ($instanceFolder === '__platform__') {
            $pCfg = PlatformAuth::dbConfig();
            $pdo  = new \PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $pCfg['host']     ?? 'localhost',
                    (int)($pCfg['port'] ?? 3306),
                    $pCfg['name']     ?? ''
                ),
                $pCfg['user']     ?? '',
                $pCfg['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
            );
            return [$pdo, $pCfg['name'] ?? '', '(platform)'];
        }

        if ($instanceFolder === null || $instanceFolder === '') {
            return [Database::getInstance()->pdo(), App::config('db.name'), '(active)'];
        }

        $instanceFolder = basename($instanceFolder); // prevent path traversal
        $cfgFile = $rootDir . '/instance/' . $instanceFolder . '/config.php';
        if (!file_exists($cfgFile)) {
            throw new \RuntimeException("Instance config not found: {$instanceFolder}");
        }

        $cfg   = require $cfgFile;
        $dbCfg = $cfg['db'] ?? [];

        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $dbCfg['host']     ?? 'localhost',
                $dbCfg['port']     ?? 3306,
                $dbCfg['name']     ?? ''
            ),
            $dbCfg['user']     ?? '',
            $dbCfg['password'] ?? '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
        );

        return [$pdo, $dbCfg['name'] ?? '', $instanceFolder];
    }

    private function pdoGetTableNames(\PDO $pdo, string $dbName): array
    {
        $stmt = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = ?");
        $stmt->execute([$dbName]);
        return array_column($stmt->fetchAll(), 'table_name');
    }

    private function pdoGetTablePk(\PDO $pdo, string $dbName, string $table): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
             ORDER BY ORDINAL_POSITION LIMIT 1"
        );
        $stmt->execute([$dbName, $table]);
        return $stmt->fetchColumn() ?: null;
    }

    private function pdoGetTableColumns(\PDO $pdo, string $dbName, string $table): array
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute([$dbName, $table]);
        return array_column($stmt->fetchAll(), 'COLUMN_NAME');
    }

    public function dbBrowse(): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $instanceFolder = $_GET['instance'] ?? null;
        $error = null;
        $tables = [];
        $dbName = '';
        $instanceLabel = '(active)';

        try {
            [$pdo, $dbName, $instanceLabel] = $this->resolveDbConnection($instanceFolder);
            $stmt = $pdo->prepare(
                "SELECT table_name, engine, table_rows, table_collation,
                        ROUND((data_length + index_length) / 1024, 1) AS total_kb
                 FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name"
            );
            $stmt->execute([$dbName]);
            $tables = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        echo $this->view->render('platform/database-browse', [
            'title'          => 'Database',
            'username'       => PlatformAuth::username(),
            'tables'         => $tables,
            'dbName'         => $dbName,
            'instanceFolder' => $instanceFolder ?? '',
            'instanceLabel'  => $instanceLabel,
            'error'          => $error,
        ]);
    }

    public function dbBrowseTable(string $table): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $instanceFolder = $_GET['instance'] ?? null;
        $error = null;
        $rows = [];
        $columns = [];
        $total = 0;
        $pages = 1;
        $dbName = '';
        $instanceLabel = '(active)';
        $pkCol = null;

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        try {
            [$pdo, $dbName, $instanceLabel] = $this->resolveDbConnection($instanceFolder);

            if (!in_array($table, $this->pdoGetTableNames($pdo, $dbName), true)) {
                throw new \RuntimeException("Unknown table: {$table}");
            }

            $pkCol  = $this->pdoGetTablePk($pdo, $dbName, $table);
            $total  = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            $pages  = (int)ceil($total / $perPage) ?: 1;
            $stmt   = $pdo->query("SELECT * FROM `{$table}` LIMIT {$perPage} OFFSET {$offset}");
            $rows   = $stmt->fetchAll();
            $columns = !empty($rows) ? array_keys($rows[0]) : [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        echo $this->view->render('platform/database-table', [
            'title'          => "Browse: {$table}",
            'username'       => PlatformAuth::username(),
            'table'          => $table,
            'columns'        => $columns,
            'rows'           => $rows,
            'total'          => $total,
            'page'           => $page,
            'perPage'        => $perPage,
            'pages'          => $pages,
            'pkCol'          => $pkCol,
            'dbName'         => $dbName,
            'instanceFolder' => $instanceFolder ?? '',
            'instanceLabel'  => $instanceLabel,
            'error'          => $error,
        ]);
    }

    public function dbEditRow(string $table): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $instanceFolder = $_GET['instance'] ?? null;
        $pkVal = $_GET['pk'] ?? '';

        try {
            [$pdo, $dbName, $instanceLabel] = $this->resolveDbConnection($instanceFolder);

            if (!in_array($table, $this->pdoGetTableNames($pdo, $dbName), true)) {
                throw new \RuntimeException("Unknown table: {$table}");
            }

            $pkCol = $this->pdoGetTablePk($pdo, $dbName, $table);
            if (!$pkCol) throw new \RuntimeException('Cannot edit table with no primary key.');

            $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$pkCol}` = ? LIMIT 1");
            $stmt->execute([$pkVal]);
            $row = $stmt->fetch();
            if (!$row) throw new \RuntimeException('Row not found.');
        } catch (\Throwable $e) {
            // redirect back with error via session
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            $back = '/cms/database/browse/' . urlencode($table) . ($instanceFolder ? '?instance=' . urlencode($instanceFolder) : '');
            header('Location: ' . $back); exit;
        }

        echo $this->view->render('platform/database-edit', [
            'title'          => "Edit â€” {$table}",
            'username'       => PlatformAuth::username(),
            'table'          => $table,
            'pkCol'          => $pkCol,
            'pkVal'          => $pkVal,
            'row'            => $row,
            'instanceFolder' => $instanceFolder ?? '',
            'page'           => (int)($_GET['page'] ?? 1),
        ]);
    }

    public function dbSaveRow(string $table): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $instanceFolder = $_POST['_instance'] ?? null;
        $pkVal = $_POST['_pk'] ?? '';
        $page  = (int)($_POST['_page'] ?? 1);
        $instParam = $instanceFolder ? '?instance=' . urlencode($instanceFolder) : '';

        try {
            [$pdo, $dbName] = $this->resolveDbConnection($instanceFolder ?: null);

            if (!in_array($table, $this->pdoGetTableNames($pdo, $dbName), true)) {
                throw new \RuntimeException("Unknown table: {$table}");
            }

            $pkCol     = $this->pdoGetTablePk($pdo, $dbName, $table);
            $validCols = $this->pdoGetTableColumns($pdo, $dbName, $table);

            $setClauses = [];
            $values     = [];
            foreach ($validCols as $col) {
                if ($col === $pkCol) continue;
                if (!array_key_exists($col, $_POST)) continue;
                $setClauses[] = "`{$col}` = ?";
                $values[]     = $_POST[$col] === '' ? null : $_POST[$col];
            }

            if (empty($setClauses)) throw new \RuntimeException('Nothing to update.');

            $values[] = $pkVal;
            $stmt = $pdo->prepare("UPDATE `{$table}` SET " . implode(', ', $setClauses) . " WHERE `{$pkCol}` = ?");
            $stmt->execute($values);
        } catch (\Throwable $e) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
        }

        $back = '/cms/database/browse/' . urlencode($table) . '?page=' . $page . ($instanceFolder ? '&instance=' . urlencode($instanceFolder) : '');
        header('Location: ' . $back); exit;
    }

    public function dbDeleteRow(string $table): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $instanceFolder = $_POST['_instance'] ?? null;
        $pkVal = $_POST['_pk'] ?? '';
        $page  = (int)($_POST['_page'] ?? 1);

        try {
            [$pdo, $dbName] = $this->resolveDbConnection($instanceFolder ?: null);

            if (!in_array($table, $this->pdoGetTableNames($pdo, $dbName), true)) {
                throw new \RuntimeException("Unknown table: {$table}");
            }

            $pkCol = $this->pdoGetTablePk($pdo, $dbName, $table);
            if (!$pkCol || $pkVal === '') throw new \RuntimeException('Missing primary key.');

            $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$pkCol}` = ?");
            $stmt->execute([$pkVal]);
            $_SESSION['_platform_flash'] = ['type' => 'success', 'message' => 'Row deleted.'];
        } catch (\Throwable $e) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
        }

        $back = '/cms/database/browse/' . urlencode($table) . '?page=' . $page . ($instanceFolder ? '&instance=' . urlencode($instanceFolder) : '');
        header('Location: ' . $back); exit;
    }

    public function dbQueryPage(): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $instanceFolder = $_GET['instance'] ?? null;
        echo $this->view->render('platform/database-query', [
            'title'          => 'Query Runner',
            'username'       => PlatformAuth::username(),
            'instanceFolder' => $instanceFolder ?? '',
            'sql'            => '',
            'results'        => null,
            'affected'       => null,
            'error'          => null,
        ]);
    }

    public function dbRunQuery(): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $instanceFolder = $_POST['instance'] ?? null;
        $sql      = trim($_POST['sql'] ?? '');
        $results  = null;
        $affected = null;
        $error    = null;

        try {
            [$pdo] = $this->resolveDbConnection($instanceFolder ?: null);
            $stmt  = $pdo->prepare($sql);
            $stmt->execute();
            if (preg_match('/^\s*SELECT\s/i', $sql)) {
                $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $affected = $stmt->rowCount();
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        echo $this->view->render('platform/database-query', [
            'title'          => 'Query Runner',
            'username'       => PlatformAuth::username(),
            'instanceFolder' => $instanceFolder ?? '',
            'sql'            => $sql,
            'results'        => $results,
            'affected'       => $affected,
            'error'          => $error,
        ]);
    }

    // ── Source Editor (/cms/source) ───────────────────────────────────────

    /**
     * GET /cms/source
     *
     * Platform source file browser + code editor.
     * Shows the actual CruinnCMS source files (templates, CSS, JS, PHP) in a
     * two-pane layout: file tree on the left, editable textarea on the right.
     * No instance database is involved.
     */
    /**
     * Build the categorised source file tree used by both the source editor
     * and the platform block editor sidebar.
     * Returns [ 'Group Label' => [ 'rel/path/to/file.php' => 'display name', ... ], ... ]
     * Only files appropriate for block/text editing are included (php, css, js, html).
     */
    private function buildSourceFileGroups(): array
    {
        $rcRoot = dirname(__DIR__, 3);
        $groups = [];

        // 1. Platform chrome
        $platformChrome = ['layout.php', 'editor-picker.php'];
        foreach ($platformChrome as $base) {
            $f = $rcRoot . '/templates/platform/' . $base;
            if (file_exists($f)) {
                $rel = 'templates/platform/' . $base;
                $groups['Platform Chrome'][$rel] = pathinfo($base, PATHINFO_FILENAME);
            }
        }

        // 2. Platform templates (standalone pages)
        foreach (glob($rcRoot . '/templates/platform/*.php') ?: [] as $f) {
            $base = basename($f);
            if (in_array($base, $platformChrome, true)) { continue; }
            $rel = 'templates/platform/' . $base;
            $groups['Platform Templates'][$rel] = pathinfo($base, PATHINFO_FILENAME);
        }

        // 3. Admin templates (recursive)
        $adminBase = $rcRoot . '/templates/admin';
        if (is_dir($adminBase)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($adminBase, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($file->getExtension() !== 'php') { continue; }
                $sub = str_replace('\\', '/', $iter->getSubPathname());
                $groups['Admin Templates']['templates/admin/' . $sub] = $sub;
            }
            if (!empty($groups['Admin Templates'])) { ksort($groups['Admin Templates']); }
        }

        // 4. Component + public + error templates
        foreach (['components' => 'Components', 'public' => 'Public Templates', 'errors' => 'Error Templates'] as $dir => $label) {
            $base = $rcRoot . '/templates/' . $dir;
            if (!is_dir($base)) { continue; }
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($file->getExtension() !== 'php') { continue; }
                $sub = str_replace('\\', '/', $iter->getSubPathname());
                $groups[$label]['templates/' . $dir . '/' . $sub] = $sub;
            }
            if (!empty($groups[$label])) { ksort($groups[$label]); }
        }

        // 5. CSS
        foreach (glob($rcRoot . '/public/css/*.css') ?: [] as $f) {
            $base = basename($f);
            $groups['CSS']['public/css/' . $base] = $base;
        }

        // 6. JS — core
        foreach (glob($rcRoot . '/public/js/*.js') ?: [] as $f) {
            $base = basename($f);
            $groups['JS — Core']['public/js/' . $base] = $base;
        }

        // 7. JS — admin (recursive)
        $jsAdminBase = $rcRoot . '/public/js/admin';
        if (is_dir($jsAdminBase)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($jsAdminBase, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($file->getExtension() !== 'js') { continue; }
                $sub = str_replace('\\', '/', $iter->getSubPathname());
                $groups['JS — Admin']['public/js/admin/' . $sub] = $sub;
            }
            if (!empty($groups['JS — Admin'])) { ksort($groups['JS — Admin']); }
        }

        // 8. PHP — src (recursive)
        $srcBase = $rcRoot . '/src';
        if (is_dir($srcBase)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($srcBase, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($file->getExtension() !== 'php') { continue; }
                $sub = str_replace('\\', '/', $iter->getSubPathname());
                $groups['PHP — src']['src/' . $sub] = $sub;
            }
            if (!empty($groups['PHP — src'])) { ksort($groups['PHP — src']); }
        }

        return array_filter($groups);
    }

    /**
     * Resolve a render_file path to an absolute filesystem path.
     *
     * Two conventions are supported:
     *   @cms/<rel>   — relative to the CruinnCMS root (RCEnvironment/).
     *                  Used for platform source files (templates/, src/, etc.)
     *   /path        — relative to public/. Legacy instance convention.
     *
     * Returns null if the path is empty, outside the root, or not a file.
     */
    private function resolveRenderFilePath(string $renderFile): ?string
    {
        if ($renderFile === '') { return null; }
        $rcRoot = dirname(__DIR__, 3);
        if (str_starts_with($renderFile, '@cms/')) {
            $abs = realpath($rcRoot . '/' . substr($renderFile, 5));
            return ($abs && str_starts_with($abs, realpath($rcRoot))) ? $abs : null;
        }
        // Legacy public/ prefix
        $abs = $rcRoot . '/public' . $renderFile;
        return file_exists($abs) ? $abs : null;
    }

    public function platformSource(): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $rcRoot = dirname(__DIR__, 3);

        // ── Build categorised file tree ──────────────────────────────────
        $allowedExt = ['php', 'css', 'js', 'html', 'json', 'md', 'sql', 'txt'];
        $groups     = $this->buildSourceFileGroups();

        // ── Resolve requested file ───────────────────────────────────────
        $reqFile     = isset($_GET['file']) ? ltrim(str_replace(['..', '\\'], ['', '/'], (string) $_GET['file']), '/') : null;
        $fileContent = null;
        $activeFile  = null;
        $fileError   = null;
        $activeGroup = null;

        if ($reqFile !== null && $reqFile !== '') {
            $absPath    = realpath($rcRoot . '/' . $reqFile);
            $rcRootReal = realpath($rcRoot);
            if ($absPath && $rcRootReal
                && str_starts_with($absPath, $rcRootReal . DIRECTORY_SEPARATOR)
                && is_file($absPath)
                && in_array(strtolower(pathinfo($absPath, PATHINFO_EXTENSION)), $allowedExt, true)
            ) {
                $fileContent = file_get_contents($absPath);
                $activeFile  = $reqFile;
                foreach ($groups as $groupName => $files) {
                    if (isset($files[$activeFile])) { $activeGroup = $groupName; break; }
                }
            } else {
                $fileError = 'File not found or not editable.';
            }
        }

        $savedFlash = $_SESSION['_source_flash'] ?? null;
        unset($_SESSION['_source_flash']);

        echo $this->view->render('platform/source-editor', [
            'title'       => $activeFile ? 'Source — ' . basename($activeFile) : 'Source Editor',
            'username'    => PlatformAuth::username(),
            'groups'      => $groups,
            'activeFile'  => $activeFile,
            'activeGroup' => $activeGroup,
            'fileContent' => $fileContent,
            'fileError'   => $fileError,
            'savedFlash'  => $savedFlash,
            'csrfToken'   => \Cruinn\CSRF::getToken(),
        ]);
    }

    /**
     * POST /cms/source/save
     *
     * Write edited file content back to disk.
     * Path is validated to stay within RCEnvironment/ and extension is whitelisted.
     * CSRF is validated automatically by the global middleware.
     */
    public function platformSourceSave(): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $reqFile    = ltrim(str_replace(['..', '\\'], ['', '/'], (string) ($_POST['file'] ?? '')), '/');
        $content    = $_POST['content'] ?? '';
        $rcRoot     = dirname(__DIR__, 3);
        $rcRootReal = realpath($rcRoot);
        $allowedExt = ['php', 'css', 'js', 'html', 'json', 'md', 'sql', 'txt'];

        $absPath = realpath($rcRoot . '/' . $reqFile);

        if (!$absPath || !$rcRootReal
            || !str_starts_with($absPath, $rcRootReal . DIRECTORY_SEPARATOR)
            || !is_file($absPath)
            || !in_array(strtolower(pathinfo($absPath, PATHINFO_EXTENSION)), $allowedExt, true)
        ) {
            $_SESSION['_source_flash'] = ['type' => 'error', 'message' => 'Invalid or disallowed file path.'];
            header('Location: /cms/source' . ($reqFile ? '?file=' . rawurlencode($reqFile) : ''));
            exit;
        }

        if (file_put_contents($absPath, $content) === false) {
            $_SESSION['_source_flash'] = ['type' => 'error', 'message' => 'Write failed — check file permissions.'];
        } else {
            $_SESSION['_source_flash'] = ['type' => 'success', 'message' => 'Saved.'];
        }

        header('Location: /cms/source?file=' . rawurlencode($reqFile));
        exit;
    }

    /**
     * GET /cms/source/preview
     *
     * Renders a source file with dummy variables and returns the HTML for display
     * in the source editor preview pane.  Only PHP and HTML files are rendered;
     * all others are rejected with a 415 response.
     *
     * The dummy variable strategy: scan the source for all $variable tokens, then
     * inject each as a string equal to its own name (e.g. $title → "title").
     * This avoids undefined-variable noise and produces a structurally representative
     * rendering without requiring a real data source.
     *
     * Security: path is validated to stay inside RCEnvironment/ and must be an
     * existing file.  Admin and platform templates are permitted here (they are
     * CruinnCMS source files).  Execution runs inside an isolated scope; no global
     * state bleeds in.
     */
    public function platformSourcePreview(): void
    {
        if (!PlatformAuth::check()) {
            http_response_code(403);
            echo 'Unauthorized';
            exit;
        }

        $reqFile    = ltrim(str_replace(['..', '\\'], ['', '/'], (string) ($_GET['file'] ?? '')), '/');
        $rcRoot     = dirname(__DIR__, 3);
        $rcRootReal = realpath($rcRoot);

        if ($reqFile === '') {
            http_response_code(400);
            echo 'No file specified.';
            exit;
        }

        $absPath = realpath($rcRoot . '/' . $reqFile);

        if (!$absPath || !$rcRootReal
            || !str_starts_with($absPath, $rcRootReal . DIRECTORY_SEPARATOR)
            || !is_file($absPath)
        ) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        if ($ext === 'html') {
            // Plain HTML — serve directly.
            header('Content-Type: text/html; charset=utf-8');
            readfile($absPath);
            exit;
        }

        if ($ext !== 'php') {
            http_response_code(415);
            echo 'Preview not available for this file type.';
            exit;
        }

        // ── PHP rendering with dummy variables ──────────────────────────
        $source = file_get_contents($absPath);

        // Extract all $varName tokens from the source, excluding superglobals
        // and common loop/internal variables to keep the dummy list clean.
        $skipVars = ['this', 'GLOBALS', '_SERVER', '_GET', '_POST', '_COOKIE',
                     '_FILES', '_ENV', '_REQUEST', '_SESSION', 'php_errormsg'];
        $varNames = [];
        if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $matches)) {
            foreach (array_unique($matches[1]) as $v) {
                if (!in_array($v, $skipVars, true)) {
                    $varNames[] = $v;
                }
            }
        }

        // Build dummy data: string value = variable name, so labels/text read
        // naturally in the preview ("title", "username", etc.)
        $dummyVars = [];
        foreach ($varNames as $v) {
            $dummyVars[$v] = $v;
        }
        // A few overrides so structural things render properly
        $dummyVars['errors']    = [];
        $dummyVars['instances'] = [];
        $dummyVars['groups']    = [];
        $dummyVars['files']     = [];
        $dummyVars['rows']      = [];
        $dummyVars['columns']   = [];
        $dummyVars['modules']   = [];
        $dummyVars['multi']     = false;
        $dummyVars['saved']     = false;
        $dummyVars['hasDraft']  = false;
        $dummyVars['editorReady'] = false;

        // Execute the template in an isolated scope; capture output.
        $render = static function (string $_tplPath, array $_vars): string {
            extract($_vars, EXTR_SKIP);
            ob_start();
            set_error_handler(static fn() => true);
            try {
                include $_tplPath;
            } catch (\Throwable $e) {
                echo '<!-- render error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' -->';
            } finally {
                restore_error_handler();
            }
            return (string) ob_get_clean();
        };

        $html = $render($absPath, $dummyVars);

        // If the output already contains a full HTML document, serve it directly.
        // Otherwise wrap it in a minimal frame so it displays properly in the iframe.
        $isFullDoc = stripos($html, '<html') !== false || stripos($html, '<!DOCTYPE') !== false;
        if (!$isFullDoc) {
            $html = '<!DOCTYPE html><html lang="en"><head>'
                  . '<meta charset="utf-8">'
                  . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                  . '<base href="/">'
                  . '</head><body style="margin:0;padding:1rem;font-family:sans-serif">'
                  . $html
                  . '</body></html>';
        }

        header('Content-Type: text/html; charset=utf-8');
        // Prevent the preview from setting cookies or running scripts that affect
        // the parent window.
        header('Content-Security-Policy: sandbox allow-same-origin');
        echo $html;
        exit;
    }

    /**
     * Strip all PHP tags from source, keeping only inline HTML.
     */
    private function stripPhpTags(string $src): string
    {
        if (!str_contains($src, '<?')) {
            return $src;
        }
        $tokens = @token_get_all($src);
        $out    = '';
        foreach ($tokens as $tok) {
            if (is_array($tok) && $tok[0] === T_INLINE_HTML) {
                $out .= $tok[1];
            }
        }
        return $out;
    }

    // ── Instance Backup / Restore / Delete ───────────────────────────────────

    /**
     * POST /cms/instances/{name}/backup
     * Creates a ZIP backup of an instance's DB and optionally uploads/secrets.
     * Saved to CRUINN_ROOT/backups/{slug}/.
     */
    public function backupInstance(string $name): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $name    = basename($name);
        $rootDir = dirname(__DIR__, 3);
        $instDir = $rootDir . '/instance/' . $name;

        if (!is_dir($instDir) || !is_file($instDir . '/config.php')) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => "Instance '{$name}' not found."];
            header('Location: /cms/dashboard'); exit;
        }

        $includeUploads = !empty($_POST['include_uploads']);
        $includeSecrets = !empty($_POST['include_secrets']);

        $cfg   = require $instDir . '/config.php';
        $dbCfg = $cfg['db'] ?? [];

        if (empty($dbCfg['host']) || empty($dbCfg['name'])) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'Instance DB config is incomplete.'];
            header('Location: /cms/dashboard'); exit;
        }

        // Connect
        try {
            $pdo = new \PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $dbCfg['host'], $dbCfg['port'] ?? 3306, $dbCfg['name']),
                $dbCfg['user'] ?? '', $dbCfg['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 10]
            );
        } catch (\PDOException $e) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'DB connection failed: ' . $e->getMessage()];
            header('Location: /cms/dashboard'); exit;
        }

        if (!class_exists('ZipArchive')) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'PHP ZipArchive extension is not available.'];
            header('Location: /cms/dashboard'); exit;
        }

        // Ensure backup dir exists
        $backupDir = $rootDir . '/backups/' . $name;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp  = date('Y-m-d_His');
        $zipName    = "cruinn-backup-{$name}-{$timestamp}.zip";
        $zipPath    = $backupDir . '/' . $zipName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'Could not create backup archive.'];
            header('Location: /cms/dashboard'); exit;
        }

        // Manifest
        $manifest = json_encode([
            'cruinn_version' => '1.0.0-beta.4',
            'slug'           => $name,
            'name'           => $cfg['site']['name'] ?? $name,
            'site_url'       => $cfg['site']['url']  ?? '',
            'db_name'        => $dbCfg['name'],
            'db_host'        => $dbCfg['host'],
            'created_at'     => date('Y-m-d H:i:s'),
            'includes_secrets' => $includeSecrets,
            'includes_uploads' => $includeUploads,
        ], JSON_PRETTY_PRINT);
        $zip->addFromString('manifest.json', $manifest);

        // DB dump
        $sqlDump = $this->dumpInstanceDbViaPdo($pdo, $dbCfg['name']);
        $zip->addFromString('database.sql', $sqlDump);

        // Secrets (instance config.php)
        if ($includeSecrets) {
            $zip->addFile($instDir . '/config.php', 'config.php');
        }

        // Uploads
        if ($includeUploads) {
            $uploadsDir = $rootDir . '/public/uploads';
            if (is_dir($uploadsDir)) {
                $iter = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($uploadsDir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($iter as $file) {
                    if ($file->isFile()) {
                        $rel = 'uploads/' . ltrim(substr($file->getRealPath(), strlen($uploadsDir)), '/\\');
                        $zip->addFile($file->getRealPath(), str_replace('\\', '/', $rel));
                    }
                }
            }
        }

        $zip->close();

        $_SESSION['_platform_flash'] = ['type' => 'success', 'message' => "Backup created: {$zipName}"];
        header('Location: /cms/dashboard'); exit;
    }

    /**
     * GET /cms/instances/{name}/backup/download?file={filename}
     * Streams a backup ZIP to the browser for download.
     */
    public function downloadBackup(string $name): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $name     = basename($name);
        $file     = basename($_GET['file'] ?? '');
        $rootDir  = dirname(__DIR__, 3);
        $zipPath  = $rootDir . '/backups/' . $name . '/' . $file;

        if ($file === '' || !str_ends_with($file, '.zip') || !is_file($zipPath)) {
            http_response_code(404);
            echo 'Backup file not found.';
            exit;
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        exit;
    }

    /**
     * POST /cms/instances/{name}/backup/delete
     * Deletes a single backup ZIP file.
     */
    public function deleteBackupFile(string $name): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $name    = basename($name);
        $file    = basename($_POST['file'] ?? '');
        $rootDir = dirname(__DIR__, 3);
        $zipPath = $rootDir . '/backups/' . $name . '/' . $file;

        if ($file !== '' && str_ends_with($file, '.zip') && is_file($zipPath)) {
            unlink($zipPath);
            $_SESSION['_platform_flash'] = ['type' => 'success', 'message' => "Backup '{$file}' deleted."];
        } else {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'Backup file not found.'];
        }

        header('Location: /cms/dashboard'); exit;
    }

    /**
     * POST /cms/instances/{name}/restore
     * Restores an instance DB (and optionally uploads) from a selected backup ZIP.
     * Instance must be offline.
     */
    public function restoreInstance(string $name): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $name    = basename($name);
        $file    = basename($_POST['file'] ?? '');
        $rootDir = dirname(__DIR__, 3);
        $instDir = $rootDir . '/instance/' . $name;
        $zipPath = $rootDir . '/backups/' . $name . '/' . $file;

        if (!is_dir($instDir) || !is_file($instDir . '/config.php')) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => "Instance '{$name}' not found."];
            header('Location: /cms/dashboard'); exit;
        }
        if (is_file($instDir . '/.active')) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'Take the instance offline before restoring.'];
            header('Location: /cms/dashboard'); exit;
        }
        if ($file === '' || !str_ends_with($file, '.zip') || !is_file($zipPath)) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'Backup file not found.'];
            header('Location: /cms/dashboard'); exit;
        }
        if (!class_exists('ZipArchive')) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'PHP ZipArchive extension is not available.'];
            header('Location: /cms/dashboard'); exit;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'Could not open backup archive.'];
            header('Location: /cms/dashboard'); exit;
        }

        // Read manifest
        $manifest = json_decode($zip->getFromName('manifest.json') ?: '{}', true) ?? [];

        // Read SQL
        $sql = $zip->getFromName('database.sql');
        if ($sql === false) {
            $zip->close();
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'Backup archive contains no database.sql.'];
            header('Location: /cms/dashboard'); exit;
        }

        // Connect using current instance config (credentials must still be valid)
        $cfg   = require $instDir . '/config.php';
        $dbCfg = $cfg['db'] ?? [];

        try {
            $pdo = new \PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $dbCfg['host'], $dbCfg['port'] ?? 3306, $dbCfg['name']),
                $dbCfg['user'] ?? '', $dbCfg['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 10]
            );
        } catch (\PDOException $e) {
            $zip->close();
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'DB connection failed: ' . $e->getMessage()];
            header('Location: /cms/dashboard'); exit;
        }

        // Apply SQL dump
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $statements = preg_split('/;\s*[\r\n]+/', $sql);
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) continue;
                $pdo->exec($stmt);
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (\PDOException $e) {
            $zip->close();
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'Restore failed during SQL execution: ' . $e->getMessage()];
            header('Location: /cms/dashboard'); exit;
        }

        // Restore uploads if present in archive
        if (!empty($manifest['includes_uploads'])) {
            $uploadsDir = $rootDir . '/public/uploads';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (!str_starts_with($entry, 'uploads/') || str_ends_with($entry, '/')) continue;
                $dest = $uploadsDir . '/' . substr($entry, strlen('uploads/'));
                $destParent = dirname($dest);
                if (!is_dir($destParent)) mkdir($destParent, 0755, true);
                file_put_contents($dest, $zip->getFromIndex($i));
            }
        }

        $zip->close();

        $_SESSION['_platform_flash'] = ['type' => 'success', 'message' => "Instance '{$name}' restored from backup '{$file}'. Bring it online when ready."];
        header('Location: /cms/dashboard'); exit;
    }

    /**
     * POST /cms/instances/{name}/delete
     * Removes instance directory and platform registry row.
     * Instance must be offline. The instance DB is NOT dropped.
     */
    public function deleteInstance(string $name): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $name    = basename($name);
        $rootDir = dirname(__DIR__, 3);
        $instDir = $rootDir . '/instance/' . $name;

        if (!is_dir($instDir)) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => "Instance '{$name}' not found."];
            header('Location: /cms/dashboard'); exit;
        }
        if (is_file($instDir . '/.active')) {
            $_SESSION['_platform_flash'] = ['type' => 'error', 'message' => 'Take the instance offline before deleting it.'];
            header('Location: /cms/dashboard'); exit;
        }

        // Recursively delete instance directory
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($instDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $entry) {
            $entry->isDir() ? rmdir($entry->getRealPath()) : unlink($entry->getRealPath());
        }
        rmdir($instDir);

        // Remove from platform instances table
        try {
            $platDb = Database::getInstance();
            $platDb->delete('instances', 'slug = ?', [$name]);
        } catch (\Throwable) {
            // Non-fatal
        }

        $_SESSION['_platform_flash'] = [
            'type'    => 'success',
            'message' => "Instance '{$name}' deleted. Note: the instance database was NOT dropped — remove it manually via your host's control panel.",
        ];
        header('Location: /cms/dashboard'); exit;
    }

    /**
     * POST /cms/instances/from-archive
     * Provision a new instance by restoring from a backup ZIP upload.
     */
    public function provisionFromArchive(): void
    {
        if (!PlatformAuth::check()) { header('Location: /cms/login'); exit; }

        $errors  = [];
        $rootDir = dirname(__DIR__, 3);

        // Handle upload
        $upload = $_FILES['archive'] ?? null;
        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            $errors[] = 'Please upload a valid .zip backup archive.';
        }

        $slug    = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug']     ?? '')));
        $name    = trim($_POST['name']     ?? '');
        $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $db = [
            'host'     => trim($_POST['db_host'] ?? 'localhost'),
            'port'     => (int) ($_POST['db_port'] ?? 3306),
            'name'     => trim($_POST['db_name'] ?? ''),
            'user'     => trim($_POST['db_user'] ?? ''),
            'password' => $_POST['db_pass'] ?? '',
            'charset'  => 'utf8mb4',
        ];

        if (empty($slug))     { $errors[] = 'Instance slug is required.'; }
        if (empty($name))     { $errors[] = 'Instance name is required.'; }
        if (empty($siteUrl))  { $errors[] = 'Site URL is required.'; }
        if (empty($db['name'])){ $errors[] = 'Database name is required.'; }
        if (empty($db['user'])){ $errors[] = 'Database user is required.'; }

        if (empty($errors) && is_dir($rootDir . '/instance/' . $slug)) {
            $errors[] = "An instance with slug '{$slug}' already exists.";
        }

        if (!class_exists('ZipArchive') && empty($errors)) {
            $errors[] = 'PHP ZipArchive extension is not available on this server.';
        }

        if (!empty($errors)) {
            $_SESSION['_provision_errors'] = $errors;
            $_SESSION['_provision_values'] = $_POST;
            header('Location: /cms/instances/new?mode=archive'); exit;
        }

        // Open archive
        $zip = new \ZipArchive();
        if ($zip->open($upload['tmp_name']) !== true) {
            $_SESSION['_provision_errors'] = ['Could not open uploaded archive. Is it a valid ZIP?'];
            $_SESSION['_provision_values'] = $_POST;
            header('Location: /cms/instances/new?mode=archive'); exit;
        }

        $sql = $zip->getFromName('database.sql');
        if ($sql === false) {
            $zip->close();
            $_SESSION['_provision_errors'] = ['Archive does not contain a database.sql file.'];
            $_SESSION['_provision_values'] = $_POST;
            header('Location: /cms/instances/new?mode=archive'); exit;
        }

        $manifest = json_decode($zip->getFromName('manifest.json') ?: '{}', true) ?? [];

        // Connect and restore DB
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
            $pdo = new \PDO($dsn, $db['user'], $db['password'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT            => 10,
            ]);

            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $statements = preg_split('/;\s*[\r\n]+/', $sql);
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) continue;
                $pdo->exec($stmt);
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (\PDOException $e) {
            $zip->close();
            $_SESSION['_provision_errors'] = ['Database error: ' . $e->getMessage()];
            $_SESSION['_provision_values'] = $_POST;
            header('Location: /cms/instances/new?mode=archive'); exit;
        }

        // Restore uploads if present
        if (!empty($manifest['includes_uploads'])) {
            $uploadsDir = $rootDir . '/public/uploads';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (!str_starts_with($entry, 'uploads/') || str_ends_with($entry, '/')) continue;
                $dest = $uploadsDir . '/' . substr($entry, strlen('uploads/'));
                $destParent = dirname($dest);
                if (!is_dir($destParent)) mkdir($destParent, 0755, true);
                file_put_contents($dest, $zip->getFromIndex($i));
            }
        }

        $zip->close();

        // Write instance directory and config
        $instDir = $rootDir . '/instance/' . $slug;
        mkdir($instDir, 0755, true);

        $escape = fn(string $v) => str_replace(["'", "\\"], ["\\'", "\\\\"], $v);
        $cfgContent = implode("\n", [
            "<?php",
            "// CruinnCMS Instance Config — {$slug} — restored from archive " . date('Y-m-d H:i:s'),
            "return [",
            "    'db' => [",
            "        'host'     => '" . $escape($db['host'])     . "',",
            "        'port'     => " . $db['port']               . ",",
            "        'name'     => '" . $escape($db['name'])     . "',",
            "        'user'     => '" . $escape($db['user'])     . "',",
            "        'password' => '" . $escape($db['password']) . "',",
            "        'charset'  => 'utf8mb4',",
            "    ],",
            "    'site' => [",
            "        'name'     => '" . $escape($name)    . "',",
            "        'url'      => '" . $escape($siteUrl) . "',",
            "        'timezone' => 'UTC',",
            "        'debug'    => false,",
            "    ],",
            "];",
        ]) . "\n";
        file_put_contents($instDir . '/config.php', $cfgContent);

        // Register in platform instances table
        try {
            $platDb = Database::getInstance();
            $platDb->insert('instances', [
                'slug'        => $slug,
                'name'        => $name,
                'db_host'     => $db['host'],
                'db_port'     => $db['port'],
                'db_name'     => $db['name'],
                'db_user'     => $db['user'],
                'db_password' => $db['password'],
                'site_url'    => $siteUrl,
                'status'      => 'active',
            ]);
        } catch (\Throwable) {}

        // Bring online
        file_put_contents($instDir . '/.active', '1');

        $_SESSION['_platform_flash'] = ['type' => 'success', 'message' => "Instance '{$name}' provisioned from archive and brought online."];
        header('Location: /cms/dashboard'); exit;
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Generate a full SQL dump of an instance DB using PDO (no exec/mysqldump).
     * Works on shared hosting without shell access.
     */
    private function dumpInstanceDbViaPdo(\PDO $pdo, string $dbName): string
    {
        $out  = "-- CruinnCMS instance backup\n";
        $out .= "-- Database: {$dbName}\n";
        $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $out .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $createRow = $pdo->query("SHOW CREATE TABLE `" . str_replace('`', '', $table) . "`")->fetch(\PDO::FETCH_ASSOC);
            $createSql = $createRow['Create Table'] ?? '';

            $out .= "-- Table: `{$table}`\n";
            $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $out .= $createSql . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `" . str_replace('`', '', $table) . "`")->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $cols  = '`' . implode('`, `', array_keys($rows[0])) . '`';
                $chunks = array_chunk($rows, 200);
                foreach ($chunks as $chunk) {
                    $vals = [];
                    foreach ($chunk as $row) {
                        $quoted = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row);
                        $vals[] = '(' . implode(', ', $quoted) . ')';
                    }
                    $out .= "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $vals) . ";\n";
                }
                $out .= "\n";
            }
        }

        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
    }

    /**
     * List available backup ZIPs for an instance, newest first.
     * Returns array of ['file', 'size_mb', 'created_at', 'manifest'].
     */
    private function listInstanceBackups(string $slug): array
    {
        $rootDir   = dirname(__DIR__, 3);
        $backupDir = $rootDir . '/backups/' . $slug;
        if (!is_dir($backupDir)) return [];

        $files = glob($backupDir . '/cruinn-backup-*.zip') ?: [];
        rsort($files); // newest first (timestamp in filename)

        $backups = [];
        foreach ($files as $path) {
            $filename = basename($path);
            $sizeMb   = round(filesize($path) / 1024 / 1024, 2);
            $manifest = [];

            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($path) === true) {
                    $raw = $zip->getFromName('manifest.json');
                    if ($raw !== false) {
                        $manifest = json_decode($raw, true) ?? [];
                    }
                    $zip->close();
                }
            }

            $backups[] = [
                'file'       => $filename,
                'size_mb'    => $sizeMb,
                'created_at' => $manifest['created_at'] ?? '',
                'manifest'   => $manifest,
            ];
        }
        return $backups;
    }
}
