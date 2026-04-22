<?php

namespace Cruinn\Services;

use Cruinn\Database;

/**
 * QueryBuilderService
 *
 * Builds and executes a safe parameterised SELECT from a structured config array.
 * All table and column names are validated against information_schema before use —
 * no user-supplied strings are ever interpolated unsanitised into SQL.
 *
 * Config shape:
 * {
 *   "source":    "query",
 *   "table":     "users",                          // primary table
 *   "joins":     [                                  // optional
 *     { "type": "LEFT", "table": "positions", "on_left": "users.id", "on_right": "positions.user_id" }
 *   ],
 *   "filters":   [                                  // optional WHERE conditions
 *     { "field": "positions.group_name", "op": "=", "value": "Council" }
 *   ],
 *   "fields":    ["users.display_name", "positions.title"],  // columns to SELECT
 *   "order_by":  "users.display_name",
 *   "order_dir": "ASC",
 *   "limit":     100
 * }
 *
 * Returned rows are keyed by bare column name (alias). On collision, the later
 * field wins — use explicit `table.col` references in card_html tokens.
 */
class QueryBuilderService
{
    private Database $db;

    /** Allowed JOIN types */
    private const JOIN_TYPES = ['INNER', 'LEFT', 'RIGHT', 'LEFT OUTER', 'RIGHT OUTER'];

    /** Allowed filter operators */
    private const FILTER_OPS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL'];

    /** Allowed ORDER directions */
    private const ORDER_DIRS = ['ASC', 'DESC'];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ── Public API ────────────────────────────────────────────────

    /**
     * Execute the query described by $config and return rows.
     * Each row is an associative array keyed by bare column name.
     *
     * @throws \InvalidArgumentException on invalid table/column references
     * @throws \RuntimeException         on DB error
     */
    public function run(array $config): array
    {
        $dbName = $this->instanceDbName();

        $primaryTable = $config['table'] ?? '';
        if ($primaryTable === '') {
            return [];
        }

        // Collect all tables referenced
        $allTables = [$primaryTable];
        $joins     = [];
        foreach ($config['joins'] ?? [] as $j) {
            $jTable = $j['table'] ?? '';
            if ($jTable === '') { continue; }
            $allTables[] = $jTable;
            $joins[]     = $j;
        }
        $allTables = array_unique($allTables);

        // Validate all table names
        $validTables = $this->validTables($dbName, $allTables);
        foreach ($allTables as $t) {
            if (!isset($validTables[$t])) {
                throw new \InvalidArgumentException("Unknown table: " . $t);
            }
        }

        // Collect all columns for validation
        $validCols = $this->validColumns($dbName, $allTables);

        // ── SELECT fields ─────────────────────────────────────────
        $fieldDefs  = $config['fields'] ?? [];
        $selectParts = [];
        $tokenMap    = []; // token name → full qualified col

        if (empty($fieldDefs)) {
            // No explicit fields — select all from primary table
            foreach ($validCols[$primaryTable] ?? [] as $col) {
                $selectParts[] = $this->quoteCol($primaryTable, $col) . ' AS ' . $this->quoteAlias($col);
                $tokenMap[$col] = $col;
            }
        } else {
            foreach ($fieldDefs as $fieldRef) {
                [$t, $c] = $this->parseFieldRef($fieldRef, $primaryTable);
                $this->assertCol($validCols, $t, $c);
                $alias         = $c; // bare column name as token
                $selectParts[] = $this->quoteCol($t, $c) . ' AS ' . $this->quoteAlias($alias);
                $tokenMap[$alias] = $fieldRef;
            }
        }

        if (empty($selectParts)) {
            return [];
        }

        // ── FROM ──────────────────────────────────────────────────
        $sql    = 'SELECT ' . implode(', ', $selectParts);
        $sql   .= ' FROM `' . $primaryTable . '`';

        // ── JOINs ─────────────────────────────────────────────────
        $params = [];
        foreach ($joins as $j) {
            $jTable   = $j['table'];
            $joinType = strtoupper(trim($j['type'] ?? 'LEFT'));
            if (!in_array($joinType, self::JOIN_TYPES, true)) { $joinType = 'LEFT'; }

            [$ltTable, $ltCol] = $this->parseFieldRef($j['on_left']  ?? '', $primaryTable);
            [$rtTable, $rtCol] = $this->parseFieldRef($j['on_right'] ?? '', $jTable);

            $this->assertCol($validCols, $ltTable, $ltCol);
            $this->assertCol($validCols, $rtTable, $rtCol);

            $sql .= ' ' . $joinType . ' JOIN `' . $jTable . '`'
                  . ' ON ' . $this->quoteCol($ltTable, $ltCol)
                  . ' = '  . $this->quoteCol($rtTable, $rtCol);
        }

        // ── WHERE ─────────────────────────────────────────────────
        $whereParts = [];
        foreach ($config['filters'] ?? [] as $f) {
            $fieldRef = $f['field'] ?? '';
            $op       = strtoupper(trim($f['op'] ?? '='));
            $value    = $f['value'] ?? '';

            if ($fieldRef === '' || !in_array($op, self::FILTER_OPS, true)) { continue; }

            [$ft, $fc] = $this->parseFieldRef($fieldRef, $primaryTable);
            $this->assertCol($validCols, $ft, $fc);

            $colExpr = $this->quoteCol($ft, $fc);

            if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                $whereParts[] = $colExpr . ' ' . $op;
            } elseif ($op === 'IN' || $op === 'NOT IN') {
                // value may be comma-separated
                $vals = array_map('trim', explode(',', (string) $value));
                $vals = array_filter($vals, fn($v) => $v !== '');
                if (empty($vals)) { continue; }
                $placeholders = implode(', ', array_fill(0, count($vals), '?'));
                $whereParts[] = $colExpr . ' ' . $op . ' (' . $placeholders . ')';
                foreach ($vals as $v) { $params[] = $v; }
            } else {
                $whereParts[] = $colExpr . ' ' . $op . ' ?';
                $params[]     = $value;
            }
        }
        if (!empty($whereParts)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        // ── ORDER BY ──────────────────────────────────────────────
        $orderField = $config['order_by'] ?? '';
        if ($orderField !== '') {
            [$ot, $oc] = $this->parseFieldRef($orderField, $primaryTable);
            // Only apply order if col is valid — silently skip if not
            if (isset($validCols[$ot][$oc])) {
                $dir   = strtoupper(trim($config['order_dir'] ?? 'ASC'));
                if (!in_array($dir, self::ORDER_DIRS, true)) { $dir = 'ASC'; }
                $sql  .= ' ORDER BY ' . $this->quoteCol($ot, $oc) . ' ' . $dir;
            }
        }

        // ── LIMIT ─────────────────────────────────────────────────
        $limit = (int) ($config['limit'] ?? 100);
        if ($limit < 1)   { $limit = 1; }
        if ($limit > 1000) { $limit = 1000; }
        $sql .= ' LIMIT ' . $limit;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Return all table names visible in the instance DB.
     * @return string[]
     */
    public function getTables(): array
    {
        $dbName = $this->instanceDbName();
        $rows   = $this->db->fetchAll(
            "SELECT TABLE_NAME FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
              ORDER BY TABLE_NAME ASC",
            [$dbName]
        );
        return array_column($rows, 'TABLE_NAME');
    }

    /**
     * Return column names for one or more tables.
     * @param  string[] $tables
     * @return array<string, string[]>  table → [col, ...]
     */
    public function getColumns(array $tables): array
    {
        if (empty($tables)) { return []; }
        $dbName  = $this->instanceDbName();
        $ph      = implode(', ', array_fill(0, count($tables), '?'));
        $rows    = $this->db->fetchAll(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ($ph)
              ORDER BY TABLE_NAME ASC, ORDINAL_POSITION ASC",
            array_merge([$dbName], $tables)
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
        }
        return $result;
    }

    /**
     * Return up to $limit distinct non-null values for a single column.
     * Table and column are validated against information_schema before use.
     */
    public function getPreviewValues(string $table, string $column, int $limit = 8): array
    {
        $dbName = $this->instanceDbName();
        $valid  = $this->validTables($dbName, [$table]);
        if (empty($valid)) { return []; }
        $validCols = $this->validColumns($dbName, [$table]);
        if (!isset($validCols[$table][$column])) { return []; }
        $limit = max(1, min(50, $limit));
        $rows  = $this->db->fetchAll(
            "SELECT DISTINCT `$table`.`$column` AS v
               FROM `$table`
              WHERE `$table`.`$column` IS NOT NULL
              ORDER BY `$table`.`$column` ASC
              LIMIT $limit"
        );
        return array_column($rows, 'v');
    }

    // ── Private helpers ───────────────────────────────────────────

    private function instanceDbName(): string
    {
        $row = $this->db->fetch('SELECT DATABASE() AS db');
        return $row['db'] ?? '';
    }

    /**
     * Returns [table => true] for tables that exist in the instance DB.
     * @param  string[] $tables
     * @return array<string, true>
     */
    private function validTables(string $dbName, array $tables): array
    {
        if (empty($tables)) { return []; }
        $ph   = implode(', ', array_fill(0, count($tables), '?'));
        $rows = $this->db->fetchAll(
            "SELECT TABLE_NAME FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ($ph)",
            array_merge([$dbName], $tables)
        );
        $map = [];
        foreach ($rows as $r) { $map[$r['TABLE_NAME']] = true; }
        return $map;
    }

    /**
     * Returns [table => [col => true]] for all columns of the given tables.
     * @param  string[] $tables
     * @return array<string, array<string, true>>
     */
    private function validColumns(string $dbName, array $tables): array
    {
        $raw = $this->getColumns($tables);
        $map = [];
        foreach ($raw as $tbl => $cols) {
            foreach ($cols as $col) {
                $map[$tbl][$col] = true;
            }
        }
        return $map;
    }

    /**
     * Parse "table.column" or bare "column" into [table, column].
     */
    private function parseFieldRef(string $ref, string $defaultTable): array
    {
        if (str_contains($ref, '.')) {
            [$t, $c] = explode('.', $ref, 2);
            return [trim($t), trim($c)];
        }
        return [$defaultTable, trim($ref)];
    }

    /**
     * @throws \InvalidArgumentException if column not in validated set
     */
    private function assertCol(array $validCols, string $table, string $col): void
    {
        if (!isset($validCols[$table][$col])) {
            throw new \InvalidArgumentException("Unknown column: {$table}.{$col}");
        }
    }

    private function quoteCol(string $table, string $col): string
    {
        return '`' . $table . '`.`' . $col . '`';
    }

    private function quoteAlias(string $alias): string
    {
        return '`' . str_replace('`', '', $alias) . '`';
    }
}
