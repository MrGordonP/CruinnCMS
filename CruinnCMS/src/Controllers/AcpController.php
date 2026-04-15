<?php
/**
 * CruinnCMS � ACP Shell Controller
 *
 * Thin entry point for the Administration Control Panel.
 * Handles only the landing redirect and layout preference save.
 *
 * Settings panels are split across:
 *   Cruinn\Admin\Controllers\AcpSystemController   � site/email/auth/security/db/modules
 *   Cruinn\Admin\Controllers\AcpInstanceController � gdpr/social/payments/oauth
 *   Cruinn\Admin\Controllers\SiteBuilderController � pages/templates/menus/structure/blocks
 */

namespace Cruinn\Controllers;

use Cruinn\Auth;

class AcpController extends BaseController
{
    public function index(): void
    {
        $this->redirect('/admin/settings/site');
    }

    public function saveLayout(): void
    {
        $layout = $this->input('layout', '1');
        $_SESSION['acp_layout'] = in_array($layout, ['1', '2']) ? $layout : '1';
        $this->json(['ok' => true, 'layout' => $_SESSION['acp_layout']]);
    }
}
