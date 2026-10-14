<?php
require_once __DIR__ . '/includes/bootstrap.php';
$filter = pick((string) ($_GET['filter'] ?? 'all'), ['all', 'unread'], 'all');
$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$openId = (int) ($_GET['id'] ?? 0);
$base = asset_url('admin/messages.php');
$keep = function (array $over = []) use ($filter, $search, $openId) {
    $p = array_filter(array_merge(['filter' => $filter === 'all' ? '' : $filter, 'q' => $search, 'id' => $openId ?: ''], $over), fn($v) => $v !== '' && $v !== null);
    return $p ? '?' . http_build_query($p) : '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $mid = (int) ($_POST['id'] ?? 0);
    if ($action === 'read_all') { $n = $pdo->exec('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0'); set_flash('success', "Marked $n message" . ($n === 1 ? '' : 's') . ' as read.'); redirect($base . $keep(['id' => ''])); }
    if ($action === 'unread' && $mid) { $pdo->prepare('UPDATE contact_messages SET is_read = 0 WHERE id = ?')->execute([$mid]); set_flash('success', 'Marked as unread.'); redirect($base . $keep(['id' => ''])); }
    if ($action === 'delete' && $mid) { $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$mid]); admin_log('message', 'Deleted a contact message'); set_flash('success', 'Message deleted.'); redirect($base . $keep(['id' => ''])); }
    redirect($base);
}

$where = []; $params = [];
if ($filter === 'unread') $where[] = 'is_read = 0';
if ($search !== '') { $l = '%' . addcslashes($search, '%_\\') . '%'; $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)'; array_push($params, $l, $l, $l, $l); }
$q = $pdo->prepare('SELECT * FROM contact_messages ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC LIMIT 200');
$q->execute($params);
$messages = $q->fetchAll();

$open = null;
if ($openId) {
    $s = $pdo->prepare('SELECT * FROM contact_messages WHERE id = ?'); $s->execute([$openId]); $open = $s->fetch();
    if ($open && !$open['is_read']) { $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?')->execute([$openId]); $open['is_read'] = 1; }
}
if (!$open && $messages) $open = null;
$unread = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();

$pageTitle = 'Messages | ' . SITE_NAME;
$activePage = 'messages';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-head">
  <div><h1>Messages</h1><p><?= $unread ? $unread . ' unread' : 'All caught up' ?> · from the store contact form</p></div>
  <?php if ($unread): ?><form method="post" class="page-head__actions"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn--ghost"><?= admin_icon('check') ?>Mark all as read</button></form><?php endif; ?>
</div>

<section class="card card--flush">
  <form class="toolbar" method="get">
    <nav class="seg" aria-label="Filter"><a href="<?= e($base . '?' . http_build_query(['q' => $search])) ?>" class="<?= $filter === 'all' ? 'is-active' : '' ?>">All</a><a href="<?= e($base . '?' . http_build_query(['filter' => 'unread', 'q' => $search])) ?>" class="<?= $filter === 'unread' ? 'is-active' : '' ?>">Unread<?= $unread ? " ($unread)" : '' ?></a></nav>
    <input type="hidden" name="filter" value="<?= e($filter) ?>">
    <label class="search-field toolbar__grow"><?= admin_icon('search') ?><span class="sr-only">Search messages</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email or text"></label>
  </form>
  <div class="inbox">
    <div class="inbox__list">
      <?php if (!$messages): ?><div class="empty"><?= admin_icon('mail') ?><p><?= $search !== '' || $filter === 'unread' ? 'No messages match.' : 'No messages yet. Questions from the contact page land here.' ?></p></div><?php endif; ?>
      <?php foreach ($messages as $m): ?>
        <a class="inbox__item<?= !$m['is_read'] ? ' is-unread' : '' ?><?= $open && (int) $open['id'] === (int) $m['id'] ? ' is-active' : '' ?>" href="<?= e($base . $keep(['id' => (int) $m['id']])) ?>">
          <span class="avatar"><?= e(initials($m['name'])) ?></span>
          <div><div style="display:flex;justify-content:space-between;gap:8px"><strong><?= e($m['name']) ?></strong><small><?= e(time_ago($m['created_at'])) ?></small></div>
            <p style="color:var(--text);font-weight:<?= $m['is_read'] ? 500 : 700 ?>"><?= e($m['subject'] ?: 'No subject') ?></p>
            <p><?= e(mb_substr($m['message'], 0, 90)) ?></p></div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="inbox__read">
      <?php if (!$open): ?>
        <div class="empty" style="padding-top:90px"><?= admin_icon('mail') ?><h3>Select a message</h3><p>Pick a conversation on the left to read it and reply.</p></div>
      <?php else: ?>
        <div class="card__head" style="margin-bottom:0">
          <div class="profile"><span class="avatar avatar--lg"><?= e(initials($open['name'])) ?></span><div><h2><?= e($open['subject'] ?: 'No subject') ?></h2><p class="muted"><?= e($open['name']) ?> · <a href="mailto:<?= e($open['email']) ?>"><?= e($open['email']) ?></a><?= $open['phone'] ? ' · ' . e($open['phone']) : '' ?></p><small class="muted"><?= e(format_date($open['created_at'], 'l, d M Y \a\t g:i a')) ?></small></div></div>
        </div>
        <div class="inbox__msg"><?= e($open['message']) ?></div>
        <div class="form-actions" style="justify-content:flex-start;margin-top:18px">
          <a class="btn btn--primary" href="mailto:<?= e($open['email']) ?>?subject=<?= rawurlencode('Re: ' . ($open['subject'] ?: 'Your message to ' . SITE_NAME)) ?>&amp;body=<?= rawurlencode("Hello " . explode(' ', $open['name'])[0] . ",\n\n\n\n— " . SITE_NAME) ?>"><?= admin_icon('reply') ?>Reply by email</a>
          <?php if ($open['phone']): ?><a class="btn btn--ghost" href="https://wa.me/<?= e(preg_replace('/\D/', '', preg_replace('/^0/', '234', $open['phone']))) ?>" target="_blank" rel="noopener"><?= admin_icon('phone') ?>WhatsApp</a><?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unread"><input type="hidden" name="id" value="<?= (int) $open['id'] ?>"><button class="btn btn--ghost"><?= admin_icon('mail') ?>Mark unread</button></form>
          <form method="post" data-confirm="Delete this message from <?= e($open['name']) ?>?" data-confirm-ok="Delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $open['id'] ?>"><button class="btn btn--ghost" style="color:var(--red)"><?= admin_icon('trash') ?>Delete</button></form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/admin-footer.php';
