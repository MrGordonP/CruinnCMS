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
        $isAdmin = (Auth::role() === 'admin');
        $widgets = [];

        if ($roleId && !$isAdmin) {
            $dashService = new DashboardService();
            $widgets = $dashService->buildDashboard($roleId);
        }

        // Persist view preference in session
        if (isset($_GET['view']) && in_array($_GET['view'], ['groups', 'modules'], true)) {
            $_SESSION['dashboard_view'] = $_GET['view'];
        }
        $dashboardView = $_SESSION['dashboard_view'] ?? 'groups';

        $this->renderAdmin('dashboard', [
            'title'          => 'Dashboard',
            'dashboardTitle' => 'Dashboard',
            'widgets'        => $widgets,
            'dashboardView'  => $dashboardView,
        ]);
    }
}
