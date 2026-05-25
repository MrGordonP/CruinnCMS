<?php $forumView = (string) ($forum_view ?? 'index'); ?>

<?php switch ($forumView):
    case 'index':
        include dirname(__DIR__) . '/index.php';
        break;
    case 'category':
        include dirname(__DIR__) . '/category.php';
        break;
    case 'thread':
        include dirname(__DIR__) . '/thread.php';
        break;
    case 'new':
        include dirname(__DIR__) . '/new.php';
        break;
    case 'edit-thread-title':
        include dirname(__DIR__) . '/edit-thread-title.php';
        break;
    case 'edit-post':
        include dirname(__DIR__) . '/edit-post.php';
        break;
    case 'report-post':
        include dirname(__DIR__) . '/report-post.php';
        break;
    case 'search':
        include dirname(__DIR__) . '/search.php';
        break;
    case 'forbidden':
        ?>
        <section class="container forum-page">
            <header class="forum-header">
                <h1><?= e($title ?? 'Access Denied') ?></h1>
            </header>
            <p>You do not have permission to view this forum page.</p>
        </section>
        <?php
        break;
    default:
        ?>
        <section class="container forum-page">
            <header class="forum-header">
                <h1>Forum</h1>
            </header>
            <p>This forum page could not be rendered.</p>
        </section>
        <?php
        break;
endswitch; ?>
