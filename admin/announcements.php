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
$editId = isset($_GET['edit']) && is_numeric($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($editId === 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '' && ($action ?? '') !== 'delete' && !empty($_POST['id'])) {
    $editId = (int)$_POST['id'];
}
if ($editId > 0) {
    $found = $conn->prepare('SELECT * FROM school_announcements WHERE id=? LIMIT 1');
    $found->bind_param('i', $editId);
    $found->execute();
    $editAnnouncement = $found->get_result()->fetch_assoc();
}
$editForm = $editAnnouncement ?: [];
if ($editAnnouncement && $_SERVER['REQUEST_METHOD'] === 'POST' && (int)($_POST['id'] ?? 0) === $editId) {
    foreach (['title', 'message', 'announcement_type', 'status', 'priority', 'sort_order', 'starts_at', 'ends_at'] as $field) {
        if (array_key_exists($field, $_POST)) {
            $editForm[$field] = $_POST[$field];
        }
    }
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
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-600"><?php echo h($row['announcement_type']); ?></span>
                    <span class="rounded-full <?php echo $row['status'] === 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'; ?> px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em]">
                      <?php echo h($row['status']); ?>
                    </span>
                  </div>
                  <h3 class="mt-2 text-lg font-semibold text-slate-900"><?php echo h($row['title']); ?></h3>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                  <a href="?edit=<?php echo (int)$row['id']; ?>" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700" aria-label="Edit <?php echo h($row['title']); ?>">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m16.862 4.487 2.651 2.651M4 20l4.5-.9L19.513 8.088a1.875 1.875 0 0 0-2.651-2.651L5.85 16.45 4 20Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="hidden sm:inline">Edit</span>
                  </a>
                  <button type="button" data-delete-announcement="<?php echo (int)$row['id']; ?>" data-announcement-title="<?php echo h($row['title']); ?>" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-medium text-rose-700 transition hover:border-rose-300 hover:bg-rose-100" aria-label="Delete <?php echo h($row['title']); ?>">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="hidden sm:inline">Delete</span>
                  </button>
                </div>
              </div>
              <p class="mt-3 break-words text-sm leading-6 text-slate-600"><?php echo nl2br(h($row['message'])); ?></p>
              <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                <span>Priority: <?php echo (int)$row['priority']; ?></span>
                <span>Sort: <?php echo (int)$row['sort_order']; ?></span>
                <?php if ($row['starts_at']): ?><span>Starts: <?php echo h(date('M d, Y h:i A', strtotime($row['starts_at']))); ?></span><?php endif; ?>
                <?php if ($row['ends_at']): ?><span>Ends: <?php echo h(date('M d, Y h:i A', strtotime($row['ends_at']))); ?></span><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <h2 class="text-xl font-bold text-slate-900">Create Announcement</h2>
      <form method="post" class="mt-4 space-y-4">
        <label class="block text-sm font-medium text-slate-700">
          Title
          <input name="title" value="" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
        </label>
        <label class="block text-sm font-medium text-slate-700">
          Message
          <textarea name="message" rows="4" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none"></textarea>
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block text-sm font-medium text-slate-700">
            Type
            <select name="announcement_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <?php foreach (['ANNOUNCEMENT', 'EVENT', 'NOTICE', 'REMINDER', 'UPCOMING'] as $typeOption): ?>
                <option value="<?php echo $typeOption; ?>" <?php echo $typeOption === 'ANNOUNCEMENT' ? 'selected' : ''; ?>><?php echo h($typeOption); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700">
            Status
            <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <option value="ACTIVE" selected>ACTIVE</option>
              <option value="INACTIVE">INACTIVE</option>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700">
            Priority (1-10)
            <input type="number" min="1" max="10" name="priority" value="5" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
          <label class="block text-sm font-medium text-slate-700">
            Sort Order
            <input type="number" name="sort_order" value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block text-sm font-medium text-slate-700">
            Start date/time
            <input type="datetime-local" name="starts_at" value="" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
          <label class="block text-sm font-medium text-slate-700">
            End date/time
            <input type="datetime-local" name="ends_at" value="" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Add Announcement</button>
        </div>
      </form>
    </section>
  </div>
</div>
<?php if ($editAnnouncement): ?>
<div id="editAnnouncementModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="editAnnouncementTitle">
  <div class="my-auto max-h-[calc(100dvh-1.5rem)] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:max-h-[calc(100dvh-3rem)] sm:p-6">
    <div class="flex items-start justify-between gap-4">
      <div><h2 id="editAnnouncementTitle" class="text-xl font-bold text-slate-900">Edit Announcement</h2><p class="mt-1 text-sm text-slate-500">Update the announcement details below.</p></div>
      <a href="announcements.php" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800" aria-label="Close edit dialog"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></a>
    </div>
    <?php if ($error): ?><div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert"><?php echo h($error); ?></div><?php endif; ?>
    <form method="post" class="mt-4 space-y-4">
      <input type="hidden" name="id" value="<?php echo (int)$editAnnouncement['id']; ?>">
      <label class="block text-sm font-medium text-slate-700">Title<input name="title" value="<?php echo h($editForm['title'] ?? ''); ?>" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none"></label>
      <label class="block text-sm font-medium text-slate-700">Message<textarea name="message" rows="4" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none"><?php echo h($editForm['message'] ?? ''); ?></textarea></label>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">Type<select name="announcement_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><?php foreach (['ANNOUNCEMENT', 'EVENT', 'NOTICE', 'REMINDER', 'UPCOMING'] as $typeOption): ?><option value="<?php echo h($typeOption); ?>" <?php echo (($editForm['announcement_type'] ?? 'ANNOUNCEMENT') === $typeOption) ? 'selected' : ''; ?>><?php echo h($typeOption); ?></option><?php endforeach; ?></select></label>
        <label class="block text-sm font-medium text-slate-700">Status<select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="ACTIVE" <?php echo (($editForm['status'] ?? 'ACTIVE') === 'ACTIVE') ? 'selected' : ''; ?>>ACTIVE</option><option value="INACTIVE" <?php echo (($editForm['status'] ?? 'ACTIVE') === 'INACTIVE') ? 'selected' : ''; ?>>INACTIVE</option></select></label>
        <label class="block text-sm font-medium text-slate-700">Priority (1-10)<input type="number" min="1" max="10" name="priority" value="<?php echo (int)($editForm['priority'] ?? 5); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
        <label class="block text-sm font-medium text-slate-700">Sort Order<input type="number" name="sort_order" value="<?php echo (int)($editForm['sort_order'] ?? 0); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">Start date/time<input type="datetime-local" name="starts_at" value="<?php echo h($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists('starts_at', $_POST) ? $_POST['starts_at'] : ($editAnnouncement['starts_at'] ? date('Y-m-d\TH:i', strtotime($editAnnouncement['starts_at'])) : '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
        <label class="block text-sm font-medium text-slate-700">End date/time<input type="datetime-local" name="ends_at" value="<?php echo h($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists('ends_at', $_POST) ? $_POST['ends_at'] : ($editAnnouncement['ends_at'] ? date('Y-m-d\TH:i', strtotime($editAnnouncement['ends_at'])) : '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
      </div>
      <div class="flex justify-end gap-3 pt-2"><a href="announcements.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a><button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Save Changes</button></div>
    </form>
  </div>
</div>
<script>
document.body.classList.add('overflow-hidden');
document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape') window.location.href = 'announcements.php';
});
document.querySelector('#editAnnouncementModal input[name="title"]').focus();
</script>
<?php endif; ?>
<div id="deleteAnnouncementModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="deleteAnnouncementTitle" aria-describedby="deleteAnnouncementDescription">
  <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-700"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
    <h2 id="deleteAnnouncementTitle" class="mt-4 text-lg font-bold text-slate-900">Delete announcement?</h2>
    <p id="deleteAnnouncementDescription" class="mt-2 text-sm leading-6 text-slate-600">This action cannot be undone.</p>
    <form id="deleteAnnouncementForm" method="post" class="mt-6 flex justify-end gap-3">
      <input type="hidden" name="action" value="delete"><input id="deleteAnnouncementId" type="hidden" name="id" value="">
      <button type="button" data-close-delete-modal class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
      <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500">Delete</button>
    </form>
  </div>
</div>
<script>
(function () {
  const modal = document.getElementById('deleteAnnouncementModal');
  const announcementId = document.getElementById('deleteAnnouncementId');
  const description = document.getElementById('deleteAnnouncementDescription');
  const cancelButton = modal.querySelector('[data-close-delete-modal]');
  let returnFocus = null;
  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
    if (returnFocus) returnFocus.focus();
  }
  document.querySelectorAll('[data-delete-announcement]').forEach(function (button) {
    button.addEventListener('click', function () {
      returnFocus = button;
      announcementId.value = button.dataset.deleteAnnouncement;
      description.textContent = 'Delete "' + button.dataset.announcementTitle + '"? This action cannot be undone.';
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.classList.add('overflow-hidden');
      cancelButton.focus();
    });
  });
  cancelButton.addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__.'/_layout.php';
