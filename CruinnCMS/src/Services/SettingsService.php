<?php
/**
 * CMS — Settings Service
 *
 * Reads and writes the `settings` table. Values stored here override
 * the config-file defaults at runtime. Config-file values act as the
 * fallback when a key is not present in the database.
 *
 * Keys use dot-notation matching the config hierarchy:
 *   site.name, mail.host, gdpr.enabled, etc.
 */

namespace Cruinn\Services;

use Cruinn\Database;
use Cruinn\App;

class SettingsService
{
    private Database $db;
    private ?array $cache = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all settings from the database, keyed by `key`.
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $rows = $this->db->fetchAll("SELECT `key`, `value`, `group` FROM settings ORDER BY `group`, `key`");
            $this->cache = [];
            foreach ($rows as $row) {
                $this->cache[$row['key']] = $row['value'];
            }
        }
        return $this->cache;
    }

    /**
     * Get a single setting value. Falls back to the config-file value
     * if the key is not in the database.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        if (array_key_exists($key, $all) && $all[$key] !== '') {
            return $all[$key];
        }
        // Fall back to config-file value
        return App::config($key, $default);
    }

    /**
     * Get a setting, returning a typed boolean.
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $val = $this->get($key);
        if ($val === null) {
            return $default;
        }
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get a setting, returning a typed integer.
     */
    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->get($key);
        if ($val === null || $val === '') {
            return $default;
        }
        return (int) $val;
    }

    /**
     * Get all settings in a specific group.
     */
    public function getGroup(string $group): array
    {
        $rows = $this->db->fetchAll(
            "SELECT `key`, `value` FROM settings WHERE `group` = ?",
            [$group]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }

    /**
     * Save a single setting (upsert).
     */
    public function set(string $key, ?string $value, string $group = 'general'): void
    {
        $this->db->execute(
            "INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group` = VALUES(`group`)",
            [$key, $value, $group]
        );
        $this->cache = null; // bust cache
    }

    /**
     * Save many settings at once (batch upsert).
     * Accepts an associative array of key => value pairs.
     */
    public function setMany(array $settings, string $group = 'general'): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value, $group);
        }
    }

    /**
     * Delete a setting.
     */
    public function delete(string $key): void
    {
        $this->db->execute("DELETE FROM settings WHERE `key` = ?", [$key]);
        $this->cache = null;
    }

    /**
     * Get all settings merged with config-file defaults for a given
     * config section. Returns an associative array ready for form binding.
     *
     * Example: getSection('mail') returns:
     *   ['mail.host' => '...', 'mail.port' => '...', ...]
     */
    public function getSection(string $section): array
    {
        $configDefaults = App::config($section, []);
        $result = [];

        if (is_array($configDefaults)) {
            foreach ($configDefaults as $key => $defaultValue) {
                if (is_array($defaultValue)) {
                    // Nested (e.g. oauth.google.client_id) — flatten one level
                    foreach ($defaultValue as $subKey => $subDefault) {
                        $fullKey = "$section.$key.$subKey";
                        $result[$fullKey] = $this->get($fullKey, (string) $subDefault);
                    }
                } else {
                    $fullKey = "$section.$key";
                    $result[$fullKey] = $this->get($fullKey, (string) $defaultValue);
                }
            }
        }

        return $result;
    }
}
