<?php
require_once dirname(__DIR__).'/config.php';
require_admin_account();
$conn = db();
$pageTitle = 'Announcements';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare('DELETE FROM school_announcements WHERE id=?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = 'Announcement deleted.';
        } else {
            $error = 'Unable to delete announcement.';
        }
    } else {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
        $title = trim($_POST['title'] ?? '');
        $messageText = trim($_POST['message'] ?? '');
        $type = trim($_POST['announcement_type'] ?? 'ANNOUNCEMENT');
        $status = in_array($_POST['status'] ?? 'ACTIVE', ['ACTIVE', 'INACTIVE'], true) ? $_POST['status'] : 'ACTIVE';
        $priority = max(1, min(10, (int)($_POST['priority'] ?? 5)));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $startsAt = trim($_POST['starts_at'] ?? '');
        $endsAt = trim($_POST['ends_at'] ?? '');

        if ($title === '' || $messageText === '') {
            $error = 'Announcement title and message are required.';
        } else {
            $startsAt = $startsAt !== '' ? $startsAt : null;
            $endsAt = $endsAt !== '' ? $endsAt : null;
            if ($id) {
                $stmt = $conn->prepare('UPDATE school_announcements SET title=?, message=?, announcement_type=?, status=?, priority=?, sort_order=?, starts_at=?, ends_at=? WHERE id=?');
                $stmt->bind_param('ssssiissi', $title, $messageText, $type, $status, $priority, $sortOrder, $startsAt, $endsAt, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO school_announcements (title, message, announcement_type, status, priority, sort_order, starts_at, ends_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssiiss', $title, $messageText, $type, $status, $priority, $sortOrder, $startsAt, $endsAt);
            }
            if ($stmt->execute()) {
                $message = $id ? 'Announcement updated.' : 'Announcement created.';
            } else {
                $error = 'Unable to save announcement.';
            }
        }
    }
}

$rows = $conn->query('SELECT * FROM school_announcements ORDER BY priority DESC, sort_order ASC, created_at DESC')->fetch_all();
$rows = $rows ?: [];
$editAnnouncement = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $found = $conn->prepare('SELECT * FROM school_announcements WHERE id=? LIMIT 1');
    $found->bind_param('i', $editId);
    $found->execute();
    $editAnnouncement = $found->get_result()->fetch_assoc();
}

ob_start();
?>
<div class="space-y-6">
  <?php if ($message): ?><div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"><?php echo h($message); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"><?php echo h($error); ?></div><?php endif; ?>

  <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-xl font-bold text-slate-900">Announcement List</h2>
        <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-indigo-700"><?php echo count($rows); ?> total</span>
      </div>
      <div class="space-y-3">
        <?php if (empty($rows)): ?>
          <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No announcements yet. Create the first public notice below.</div>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <div class="rounded-xl border border-slate-200 p-4">
              <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-600"><?php echo h($row['announcement_type']); ?></span>
                    <span class="rounded-full <?php echo $row['status'] === 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'; ?> px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em]">
                      <?php echo h($row['status']); ?>
                    </span>
                  </div>
                  <h3 class="mt-2 text-lg font-semibold text-slate-900"><?php echo h($row['title']); ?></h3>
                </div>
              </div>
              <p class="mt-3 break-words text-sm leading-6 text-slate-600"><?php echo nl2br(h($row['message'])); ?></p>
              <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                <span>Priority: <?php echo (int)$row['priority']; ?></span>
                <span>Sort: <?php echo (int)$row['sort_order']; ?></span>
                <?php if ($row['starts_at']): ?><span>Starts: <?php echo h(date('M d, Y h:i A', strtotime($row['starts_at']))); ?></span><?php endif; ?>
                <?php if ($row['ends_at']): ?><span>Ends: <?php echo h(date('M d, Y h:i A', strtotime($row['ends_at']))); ?></span><?php endif; ?>
              </div>
              <div class="mt-4 flex justify-end gap-2 border-t border-slate-100 pt-3">
                <a href="?edit=<?php echo (int)$row['id']; ?>" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m16.862 4.487 2.651 2.651M4 20l4.5-.9L19.513 8.088a1.875 1.875 0 0 0-2.651-2.651L5.85 16.45 4 20Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Edit
                </a>
                <form method="post" onsubmit="return confirm('Delete this announcement?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                  <button class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-medium text-rose-700 transition hover:border-rose-300 hover:bg-rose-100" type="submit">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Delete
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <h2 class="text-xl font-bold text-slate-900"><?php echo $editAnnouncement ? 'Edit Announcement' : 'Create Announcement'; ?></h2>
      <form method="post" class="mt-4 space-y-4">
        <?php if ($editAnnouncement): ?><input type="hidden" name="id" value="<?php echo (int)$editAnnouncement['id']; ?>"><?php endif; ?>
        <label class="block text-sm font-medium text-slate-700">
          Title
          <input name="title" value="<?php echo h($editAnnouncement['title'] ?? ''); ?>" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
        </label>
        <label class="block text-sm font-medium text-slate-700">
          Message
          <textarea name="message" rows="4" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none"><?php echo h($editAnnouncement['message'] ?? ''); ?></textarea>
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block text-sm font-medium text-slate-700">
            Type
            <select name="announcement_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <?php foreach (['ANNOUNCEMENT', 'EVENT', 'NOTICE', 'REMINDER', 'UPCOMING'] as $typeOption): ?>
                <option value="<?php echo $typeOption; ?>" <?php echo (($editAnnouncement['announcement_type'] ?? 'ANNOUNCEMENT') === $typeOption) ? 'selected' : ''; ?>><?php echo h($typeOption); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700">
            Status
            <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <option value="ACTIVE" <?php echo (($editAnnouncement['status'] ?? 'ACTIVE') === 'ACTIVE') ? 'selected' : ''; ?>>ACTIVE</option>
              <option value="INACTIVE" <?php echo (($editAnnouncement['status'] ?? 'ACTIVE') === 'INACTIVE') ? 'selected' : ''; ?>>INACTIVE</option>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700">
            Priority (1-10)
            <input type="number" min="1" max="10" name="priority" value="<?php echo (int)($editAnnouncement['priority'] ?? 5); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
          <label class="block text-sm font-medium text-slate-700">
            Sort Order
            <input type="number" name="sort_order" value="<?php echo (int)($editAnnouncement['sort_order'] ?? 0); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block text-sm font-medium text-slate-700">
            Start date/time
            <input type="datetime-local" name="starts_at" value="<?php echo $editAnnouncement && $editAnnouncement['starts_at'] ? date('Y-m-d\TH:i', strtotime($editAnnouncement['starts_at'])) : ''; ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
          <label class="block text-sm font-medium text-slate-700">
            End date/time
            <input type="datetime-local" name="ends_at" value="<?php echo $editAnnouncement && $editAnnouncement['ends_at'] ? date('Y-m-d\TH:i', strtotime($editAnnouncement['ends_at'])) : ''; ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <?php if ($editAnnouncement): ?>
            <a href="announcements.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
          <?php endif; ?>
          <button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500"><?php echo $editAnnouncement ? 'Save Changes' : 'Add Announcement'; ?></button>
        </div>
      </form>
    </section>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__.'/_layout.php';
