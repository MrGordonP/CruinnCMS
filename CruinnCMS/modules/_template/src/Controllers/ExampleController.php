<?php

namespace Cruinn\Module\ExampleModule\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;

class ExampleController extends BaseController
{
    public function index(): void
    {
        Auth::requireRole('admin');

        $items = $this->db->fetchAll(
            'SELECT * FROM example_items ORDER BY created_at DESC LIMIT 100'
        );

        $this->renderAdmin('admin/example/index', [
            'title' => 'Example Module',
            'items' => $items,
        ]);
    }

    public function publicIndex(): void
    {
        $items = $this->db->fetchAll(
            "SELECT * FROM example_items WHERE status = 'published' ORDER BY created_at DESC"
        );

        $this->render('public/example/index', [
            'title' => 'Example',
            'items' => $items,
        ]);
    }
}
