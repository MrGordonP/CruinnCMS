<?php

namespace Cruinn\Module\ExampleModule\Services;

use Cruinn\Database;

class ExampleService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function countPublished(): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM example_items WHERE status = 'published'"
        );
    }
}
