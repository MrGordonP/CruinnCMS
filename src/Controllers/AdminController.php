<?php
/**
 * CruinnCMS � Admin Controller
 *
 * Thin shell: renders the admin dashboard.
 * Page, block, media, and user management are in src/Admin/Controllers/.
 * All routes require 'admin' role (enforced by prefix middleware).
 */

namespace Cruinn\Controllers;

use Cruinn\Auth;
use Cruinn\Services\DashboardService;

class AdminController extends BaseController
{
    /**
     * GET /admin / GET /admin/dashboard — Admin dashboard.
     * For admin role: passes live stats for the sidebar; skips widget data.
     * For other roles: builds configured role widgets.
     */
    public function dashboard(): void
    {
        $roleId  = Auth::roleId();
        $role    = Auth::role() ?: 'public';
        $isAdmin = (Auth::role() === 'admin');
        $widgets = [];
        $moduleWidgets = [];

        $dashService = new DashboardService();
        $moduleWidgets = $dashService->buildModuleWidgetsForRole($role);

        if ($roleId && !$isAdmin) {
            $widgets = $dashService->buildDashboard($roleId);
        }

        $this->renderAdmin('dashboard', [
            'title'          => 'Dashboard',
            'dashboardTitle' => 'Dashboard',
            'widgets'        => $widgets,
            'moduleWidgets'  => $moduleWidgets,
        ]);
    }
}
