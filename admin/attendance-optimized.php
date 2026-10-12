<?php
/**
 * OPTIMIZED Attendance List with Pagination
 * Replaces the old unoptimized query that fetched all records
 */
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/cache.php';
require_once dirname(__DIR__).'/database-optimized.php';

$conn = db();
$cache = new SimpleCache();
$dbOptimized = new OptimizedDatabase($conn, $cache);

// URL parameters
$event_id = (int)($_GET['event_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50; // Records per page

// Validate event exists
$event = null;
if ($event_id > 0) {
    $stmt = $conn->prepare('SELECT * FROM events WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
}

// Build query with filters
$sql = "SELECT 
    a.attendance_id, a.scan_in, a.scan_out, a.attendance_status, a.recorded_date,
    s.id, s.first_name, s.last_name, s.middle_name, s.course, s.year_level, s.section, s.student_no
    FROM event_attendance a 
    JOIN students s ON s.id=a.student_id";

if ($event_id > 0) {
    $sql .= " WHERE a.event_id=" . (int)$event_id;
    if (!empty($status_filter) && in_array(strtoupper($status_filter), ['PRESENT', 'ABSENT', 'LATE', 'EXCUSED', 'INCOMPLETE'])) {
        $sql .= " AND a.attendance_status='" . strtoupper($status_filter) . "'";
    }
} else {
    $sql .= " ORDER BY a.recorded_date DESC";
}
$sql .= " ORDER BY s.last_name, s.first_name";

// Paginate results
$paged = $dbOptimized->paginate($sql, $page, $per_page);
$attendance_records = $paged['data'];
$total_records = $paged['total'];
$total_pages = $paged['pages'];

// Build pagination HTML
function build_pagination_html($current_page, $total_pages, $event_id, $status_filter) {
    $html = '<div class="flex items-center justify-between gap-2 text-sm">';
    $html .= '<span class="text-slate-600">Page <strong>' . $current_page . '</strong> of <strong>' . $total_pages . '</strong></span>';
    $html .= '<div class="flex gap-1">';
    
    if ($current_page > 1) {
        $prev_url = '?event_id=' . $event_id . '&page=' . ($current_page - 1);
        if (!empty($status_filter)) $prev_url .= '&status=' . urlencode($status_filter);
        $html .= '<a href="' . $prev_url . '" class="px-3 py-1 rounded bg-blue-500 text-white hover:bg-blue-600">← Prev</a>';
    }
    
    if ($current_page < $total_pages) {
        $next_url = '?event_id=' . $event_id . '&page=' . ($current_page + 1);
        if (!empty($status_filter)) $next_url .= '&status=' . urlencode($status_filter);
        $html .= '<a href="' . $next_url . '" class="px-3 py-1 rounded bg-blue-500 text-white hover:bg-blue-600">Next →</a>';
    }
    
    $html .= '</div></div>';
    return $html;
}

$pageTitle = 'Attendance Records';
ob_start();
?>
<div class="space-y-6">
    <?php if ($event): ?>
        <div class="rounded-3xl bg-gradient-to-r from-slate-950 via-emerald-950 to-emerald-900 p-5 text-white shadow-xl sm:p-6">
            <div class="text-3xl font-bold"><?php echo h($event['event_name']); ?></div>
            <div class="mt-2 text-sm opacity-90">Date: <?php echo date('M j, Y', strtotime($event['event_date'])); ?></div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="?event_id=<?php echo $event_id; ?>" class="<?php echo empty($status_filter) ? 'bg-emerald-600 text-white' : 'bg-white text-slate-700'; ?> px-4 py-2 rounded-lg">All (<?php echo $total_records; ?>)</a>
            <?php foreach (['PRESENT', 'ABSENT', 'LATE', 'EXCUSED', 'INCOMPLETE'] as $status): ?>
                <a href="?event_id=<?php echo $event_id; ?>&status=<?php echo strtolower($status); ?>" class="<?php echo $status_filter === strtolower($status) ? 'bg-emerald-600 text-white' : 'bg-white text-slate-700'; ?> px-4 py-2 rounded-lg"><?php echo ucfirst($status); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl bg-white p-5 shadow">
        <div class="mb-4 text-lg font-semibold">Records (<?php echo count($attendance_records); ?> of <?php echo $total_records; ?>)</div>
        <?php echo build_pagination_html($page, $total_pages, $event_id, $status_filter); ?>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-slate-200">
                        <th class="px-3 py-2 text-left font-semibold">Student</th>
                        <th class="px-3 py-2 text-left font-semibold">ID</th>
                        <th class="px-3 py-2 text-left font-semibold">Course/Year/Section</th>
                        <th class="px-3 py-2 text-center font-semibold">Scan In</th>
                        <th class="px-3 py-2 text-center font-semibold">Scan Out</th>
                        <th class="px-3 py-2 text-center font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance_records)): ?>
                        <tr><td colspan="6" class="px-3 py-4 text-center text-slate-500">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-2 font-medium"><?php echo h($record['last_name'] . ', ' . $record['first_name']); ?></td>
                                <td class="px-3 py-2"><?php echo h($record['student_no']); ?></td>
                                <td class="px-3 py-2 text-xs"><?php echo h($record['course'] . ' / ' . $record['year_level'] . ' / ' . $record['section']); ?></td>
                                <td class="px-3 py-2 text-center text-xs"><?php echo $record['scan_in'] ? date('M j, g:i A', strtotime($record['scan_in'])) : '—'; ?></td>
                                <td class="px-3 py-2 text-center text-xs"><?php echo $record['scan_out'] ? date('M j, g:i A', strtotime($record['scan_out'])) : '—'; ?></td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold 
                                        <?php 
                                        echo match($record['attendance_status']) {
                                            'PRESENT' => 'bg-emerald-100 text-emerald-700',
                                            'ABSENT' => 'bg-rose-100 text-rose-700',
                                            'LATE' => 'bg-amber-100 text-amber-700',
                                            'EXCUSED' => 'bg-blue-100 text-blue-700',
                                            default => 'bg-slate-100 text-slate-700'
                                        };
                                    ?>">
                                        <?php echo h($record['attendance_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4"><?php echo build_pagination_html($page, $total_pages, $event_id, $status_filter); ?></div>
    </div>
</div>
<?php 
$content = ob_get_clean(); 
include __DIR__.'/_layout.php';
