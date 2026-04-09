<aside class="page-sidebar">

    <!-- Upcoming Events Widget -->
    <div class="sidebar-widget">
        <h3 class="sidebar-widget-title">Upcoming Events</h3>
        <?php if (!empty($sidebarEvents)): ?>
            <?php foreach ($sidebarEvents as $ev): ?>
            <div class="sidebar-event">
                <div class="sidebar-event-date">
                    <span class="sidebar-event-month"><?= date('M', strtotime($ev['date_start'])) ?></span>
                    <span class="sidebar-event-day"><?= date('j', strtotime($ev['date_start'])) ?></span>
                </div>
                <div class="sidebar-event-info">
                    <a href="/events/<?= e($ev['slug']) ?>"><?= e($ev['title']) ?></a>
                    <?php $t = date('g:ia', strtotime($ev['date_start'])); if ($t !== '12:00am'): ?>
                    <span class="sidebar-event-time"><?= $t ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="sidebar-no-events">No upcoming events.</p>
        <?php endif; ?>
        <a href="/events" class="sidebar-view-all">View Calendar &rarr;</a>
    </div>

    <!-- Social Links Widget -->
    <?php
    $sidebar_fb = \IGA\App::config('social.facebook', '');
    $sidebar_ig = \IGA\App::config('social.instagram', '');
    ?>
    <?php if ($sidebar_fb || $sidebar_ig): ?>
    <div class="sidebar-widget">
        <h3 class="sidebar-widget-title">Follow Us</h3>
        <div class="sidebar-social">
            <?php if ($sidebar_fb): ?>
            <a href="<?= e($sidebar_fb) ?>" class="sidebar-social-btn social-facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.13 8.44 9.88v-6.99H7.9v-2.89h2.54V9.84c0-2.51 1.49-3.9 3.78-3.9 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.89h-2.34v6.99C18.34 21.13 22 16.99 22 12z"/>
                </svg>
            </a>
            <?php endif; ?>
            <?php if ($sidebar_ig): ?>
            <a href="<?= e($sidebar_ig) ?>" class="sidebar-social-btn social-instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                </svg>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</aside>
