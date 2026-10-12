-- PostgreSQL Database Optimization Indexes
-- Run these commands in your Supabase SQL Editor to speed up queries
-- Copy-paste all at once or one by one

CREATE INDEX IF NOT EXISTS idx_students_student_no ON students(student_no);
CREATE INDEX IF NOT EXISTS idx_students_qr_code ON students(qr_code);
CREATE INDEX IF NOT EXISTS idx_students_account_status ON students(account_status);

CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);
CREATE INDEX IF NOT EXISTS idx_events_status_date ON events(status, event_date);
CREATE INDEX IF NOT EXISTS idx_events_event_date ON events(event_date);

CREATE INDEX IF NOT EXISTS idx_event_students_event_id ON event_students(event_id);
CREATE INDEX IF NOT EXISTS idx_event_students_student_id ON event_students(student_id);

CREATE INDEX IF NOT EXISTS idx_event_attendance_event_id ON event_attendance(event_id);
CREATE INDEX IF NOT EXISTS idx_event_attendance_student_id ON event_attendance(student_id);
CREATE INDEX IF NOT EXISTS idx_event_attendance_status ON event_attendance(attendance_status);
CREATE INDEX IF NOT EXISTS idx_event_attendance_recorded_date ON event_attendance(recorded_date);
CREATE INDEX IF NOT EXISTS idx_event_attendance_scan_in ON event_attendance(scan_in);
CREATE INDEX IF NOT EXISTS idx_event_attendance_scan_out ON event_attendance(scan_out);

CREATE INDEX IF NOT EXISTS idx_fines_student_id ON fines(student_id);
CREATE INDEX IF NOT EXISTS idx_fines_status ON fines(status);

CREATE INDEX IF NOT EXISTS idx_clearance_student_id ON clearance(student_id);
CREATE INDEX IF NOT EXISTS idx_clearance_status ON clearance(status);

CREATE INDEX IF NOT EXISTS idx_ssc_users_username ON ssc_users(username);
CREATE INDEX IF NOT EXISTS idx_ssc_settings_setting_key ON ssc_settings(setting_key);

CREATE INDEX IF NOT EXISTS idx_school_announcements_status ON school_announcements(status);
