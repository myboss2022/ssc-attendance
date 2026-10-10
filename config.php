<?php
require_once __DIR__.'/database.php';
date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) {
    if (getenv('VERCEL') === '1') {
        require_once __DIR__.'/database_session_handler.php';
        session_set_save_handler(new SupabaseSessionHandler(), true);
        session_set_cookie_params([
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}

function db() {
    static $conn;
    if ($conn) return $conn;
    $host = getenv('SUPABASE_DB_HOST');
    $name = getenv('SUPABASE_DB_NAME');
    $user = getenv('SUPABASE_DB_USER');
    $password = getenv('SUPABASE_DB_PASSWORD');
    $port = getenv('SUPABASE_DB_PORT') ?: '5432';
    $sslmode = getenv('SUPABASE_DB_SSLMODE') ?: 'require';
    if (!$host || !$name || !$user || $password === false) {
        http_response_code(500);
        exit('Database connection error. Configure the Supabase PostgreSQL environment variables.');
    }
    if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
        error_log('The PHP pdo_pgsql extension is not enabled.');
        http_response_code(500);
        exit('Database connection error. Enable the PHP pdo_pgsql extension.');
    }
    try {
        $pdo = new PDO(
            "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("SET TIME ZONE 'Asia/Manila'");
    } catch (PDOException $exception) {
        error_log('Supabase PostgreSQL connection failed: '.$exception->getMessage());
        http_response_code(500);
        exit('Database connection error. Check the Supabase connection settings and schema.');
    }
    $conn = new PgsqlConnection($pdo);
    finalize_expired_events($conn);
    return $conn;
}
function finalize_expired_events($conn) {
    $check = $conn->query("WITH settings AS (
        SELECT COALESCE((SELECT setting_value FROM ssc_settings WHERE setting_key='overnight_events' LIMIT 1), '0') AS overnight_events
    )
    SELECT settings.overnight_events,
        EXISTS (
            SELECT 1 FROM events e
            WHERE e.attendance_required=1 AND e.status IN ('UPCOMING','ACTIVE')
              AND (e.event_date + e.end_time + (
                  CASE WHEN settings.overnight_events='1'
                       THEN INTERVAL '1 day' ELSE INTERVAL '0 day' END
              )) <= CURRENT_TIMESTAMP
        ) AS has_expired
    FROM settings")->fetch_assoc();
    if (!$check || !in_array(strtolower((string)$check['has_expired']), ['1', 't', 'true'], true)) {
        return;
    }

    $overnight = $check['overnight_events'] === '1' ? 1 : 0;
    $expired = "(e.event_date + e.end_time + ($overnight * INTERVAL '1 day')) <= CURRENT_TIMESTAMP";

    $conn->query("INSERT INTO event_attendance
        (student_id,event_id,attendance_status,recorded_date,remarks)
        SELECT es.student_id,e.id,'ABSENT',e.event_date,'Automatically marked absent after the event ended.'
        FROM event_students es
        JOIN events e ON e.id=es.event_id
        WHERE e.attendance_required=1 AND e.status IN ('UPCOMING','ACTIVE') AND $expired
          AND NOT EXISTS (
              SELECT 1 FROM event_attendance a
              WHERE a.event_id=es.event_id AND a.student_id=es.student_id
          )
        ON CONFLICT (event_id,student_id) DO NOTHING");

    $conn->query("INSERT INTO fines(event_id,student_id,amount,reason,status)
        SELECT a.event_id,a.student_id,e.fine_amount,
               CONCAT('Absent from event: ',e.event_name),'UNPAID'
        FROM event_attendance a
        JOIN events e ON e.id=a.event_id
        WHERE a.attendance_status='ABSENT' AND e.fine_amount>0
          AND e.attendance_required=1 AND e.status IN ('UPCOMING','ACTIVE') AND $expired
          AND NOT EXISTS (
              SELECT 1 FROM fines f
              WHERE f.event_id=a.event_id AND f.student_id=a.student_id
          )
        ON CONFLICT (event_id,student_id) DO NOTHING");

    $conn->query("UPDATE events e SET status='COMPLETED'
        WHERE e.status IN ('UPCOMING','ACTIVE') AND e.attendance_required=1 AND $expired");
}
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function student_display_name($student, $lastFirst = true) {
    $first = trim((string)($student['first_name'] ?? ''));
    $last = trim((string)($student['last_name'] ?? ''));
    $middle = trim((string)($student['middle_name'] ?? ''));
    $middleInitial = $middle === '' ? '' : strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $middle), 0, 1)).'.';
    $given = trim($first.' '.$middleInitial);
    return $lastFirst ? trim($last.', '.$given) : trim($given.' '.$last);
}
function is_admin() { return !empty($_SESSION['admin_id']) || !empty($_SESSION['ssc_id']); }
function require_admin_account() {
    require_admin();
    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        exit('Administrator access required.');
    }
}

function require_admin($allowPasswordChange = false) {
    if (!is_admin()) { header('Location: ../login.php'); exit; }
    if (!$allowPasswordChange && !empty($_SESSION['ssc_id']) && !empty($_SESSION['ssc_must_change_password'])) {
        header('Location: ../change_password.php'); exit;
    }
}
function get_ssc_setting($key, $default = '') {
    $conn = db();
    $stmt = $conn->prepare('SELECT setting_value FROM ssc_settings WHERE setting_key=? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['setting_value'] : $default;
}
function overnight_events_enabled() { return get_ssc_setting('overnight_events', '0') === '1'; }
function active_event($conn) {
    $overnight = overnight_events_enabled() ? " OR (event_date=DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND end_time < start_time)" : '';
    $sql = "SELECT * FROM events WHERE status='ACTIVE' AND attendance_required=1 AND (event_date=CURDATE()$overnight) ORDER BY event_date DESC, start_time LIMIT 1";
    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : null;
}
function get_active_announcements($conn = null) {
    if (!$conn) { $conn = db(); }
    $result = $conn->query("SELECT * FROM school_announcements WHERE status='ACTIVE' AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY priority DESC, sort_order ASC, created_at DESC");
    if (!$result) { return []; }
    $items = [];
    while ($row = $result->fetch_assoc()) { $items[] = $row; }
    return $items;
}
function event_window_open($event, $direction) {
    if (!$event) return false;
    $now = new DateTimeImmutable('now');
    $start = $direction === 'IN' ? $event['scan_in_start'] : $event['scan_out_start'];
    $end = $direction === 'IN' ? $event['scan_in_end'] : $event['scan_out_end'];
    $baseDate = new DateTimeImmutable($event['event_date'].' '.$start);
    $endDate = new DateTimeImmutable($event['event_date'].' '.$end);
    if ($end <= $start && overnight_events_enabled()) $endDate = $endDate->modify('+1 day');
    return $now >= $baseDate && $now <= $endDate;
}
function event_label($event) { return $event ? $event['event_name'].' Â· '.date('M j, Y', strtotime($event['event_date'])) : 'No active event'; }
