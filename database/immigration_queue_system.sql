-- Immigration Queue Management System
-- Database: immigration_queue_system (local XAMPP)
-- For cPanel import use: database/cpanel_import.sql

CREATE DATABASE IF NOT EXISTS immigration_queue_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE immigration_queue_system;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS application_remarks;
DROP TABLE IF EXISTS application_documents;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS queue_history;
DROP TABLE IF EXISTS queue_tickets;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS appointment_slots;
DROP TABLE IF EXISTS staff_service_permissions;
DROP TABLE IF EXISTS staff_counter_assignments;
DROP TABLE IF EXISTS counter_services;
DROP TABLE IF EXISTS counters;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS system_settings;

SET FOREIGN_KEY_CHECKS = 1;

-- ===================== USERS =====================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    ic_passport VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff','public') NOT NULL DEFAULT 'public',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_email (email)
) ENGINE=InnoDB;

-- ===================== STAFF =====================
CREATE TABLE staff (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    staff_code VARCHAR(30) NOT NULL UNIQUE,
    department VARCHAR(100) DEFAULT 'Immigration Counter',
    working_status ENUM('online','offline','break') NOT NULL DEFAULT 'offline',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== SERVICES =====================
CREATE TABLE services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    estimated_duration INT NOT NULL DEFAULT 15 COMMENT 'minutes',
    queue_prefix CHAR(1) NOT NULL DEFAULT 'A',
    icon VARCHAR(50) DEFAULT 'fa-passport',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===================== COUNTERS =====================
CREATE TABLE counters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    counter_number VARCHAR(20) NOT NULL UNIQUE,
    counter_name VARCHAR(100) NOT NULL,
    status ENUM('available','serving','offline') NOT NULL DEFAULT 'offline',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE counter_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    counter_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_counter_service (counter_id, service_id),
    CONSTRAINT fk_cs_counter FOREIGN KEY (counter_id) REFERENCES counters(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE staff_counter_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    counter_id INT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_sca_current (staff_id, is_current),
    CONSTRAINT fk_sca_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    CONSTRAINT fk_sca_counter FOREIGN KEY (counter_id) REFERENCES counters(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE staff_service_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_staff_service (staff_id, service_id),
    CONSTRAINT fk_ssp_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    CONSTRAINT fk_ssp_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== APPOINTMENT SLOTS =====================
CREATE TABLE appointment_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id INT UNSIGNED NOT NULL,
    slot_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    max_capacity INT NOT NULL DEFAULT 5,
    booked_count INT NOT NULL DEFAULT 0,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slot (service_id, slot_date, start_time),
    INDEX idx_slot_date (slot_date),
    CONSTRAINT fk_slot_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(30) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    slot_id INT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    remarks TEXT,
    reminder_option ENUM('none','email','sms','both') NOT NULL DEFAULT 'email',
    status ENUM('pending','confirmed','checked_in','completed','cancelled','no_show') NOT NULL DEFAULT 'confirmed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_appt_user (user_id),
    INDEX idx_appt_date (appointment_date),
    INDEX idx_appt_status (status),
    CONSTRAINT fk_appt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_appt_service FOREIGN KEY (service_id) REFERENCES services(id),
    CONSTRAINT fk_appt_slot FOREIGN KEY (slot_id) REFERENCES appointment_slots(id)
) ENGINE=InnoDB;

-- ===================== QUEUE TICKETS =====================
CREATE TABLE queue_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_ref VARCHAR(30) NOT NULL UNIQUE,
    queue_number VARCHAR(10) NOT NULL,
    queue_date DATE NOT NULL,
    visitor_name VARCHAR(150) NOT NULL,
    visitor_ic VARCHAR(50) DEFAULT NULL,
    visitor_phone VARCHAR(30) DEFAULT NULL,
    visitor_email VARCHAR(150) DEFAULT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    service_id INT UNSIGNED NOT NULL,
    counter_id INT UNSIGNED DEFAULT NULL,
    staff_id INT UNSIGNED DEFAULT NULL,
    appointment_id INT UNSIGNED DEFAULT NULL,
    ticket_type ENUM('appointment','walk_in') NOT NULL DEFAULT 'walk_in',
    status ENUM('waiting','serving','completed','skipped','cancelled','no_show') NOT NULL DEFAULT 'waiting',
    called_at DATETIME DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    estimated_wait INT DEFAULT NULL COMMENT 'minutes',
    recall_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_queue_day_number (queue_date, queue_number),
    INDEX idx_queue_status (status),
    INDEX idx_queue_date (queue_date),
    INDEX idx_queue_service (service_id),
    CONSTRAINT fk_qt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_qt_service FOREIGN KEY (service_id) REFERENCES services(id),
    CONSTRAINT fk_qt_counter FOREIGN KEY (counter_id) REFERENCES counters(id) ON DELETE SET NULL,
    CONSTRAINT fk_qt_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    CONSTRAINT fk_qt_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE queue_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_status VARCHAR(30) DEFAULT NULL,
    new_status VARCHAR(30) DEFAULT NULL,
    performed_by INT UNSIGNED DEFAULT NULL,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_qh_ticket (ticket_id),
    CONSTRAINT fk_qh_ticket FOREIGN KEY (ticket_id) REFERENCES queue_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_qh_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ===================== APPLICATIONS =====================
CREATE TABLE applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(30) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED DEFAULT NULL,
    applicant_name VARCHAR(150) NOT NULL,
    applicant_ic VARCHAR(50) NOT NULL,
    applicant_email VARCHAR(150) DEFAULT NULL,
    applicant_phone VARCHAR(30) DEFAULT NULL,
    purpose TEXT,
    status ENUM('pending','under_review','approved','rejected','additional_docs') NOT NULL DEFAULT 'pending',
    assigned_staff_id INT UNSIGNED DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_app_status (status),
    INDEX idx_app_user (user_id),
    CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_app_service FOREIGN KEY (service_id) REFERENCES services(id),
    CONSTRAINT fk_app_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    CONSTRAINT fk_app_staff FOREIGN KEY (assigned_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    CONSTRAINT fk_app_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE application_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_adoc_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE application_remarks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    remark TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_are_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_are_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== NOTIFICATIONS =====================
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== SYSTEM SETTINGS =====================
CREATE TABLE system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===================== ACTIVITY LOGS =====================
CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) DEFAULT NULL,
    details TEXT,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alog_user (user_id),
    INDEX idx_alog_created (created_at),
    CONSTRAINT fk_alog_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ===================== SEED DATA =====================
-- Passwords: admin123 / staff123 / user123
INSERT INTO users (full_name, ic_passport, email, phone, password, role, is_active) VALUES
('System Administrator', 'A0000001', 'admin@immigration.gov', '0123456789', '$2y$10$VKsHn20u0UbJCx./jloHXeqIRlE4lCzhzEMeKrSSasjEfIkeSb2Bi', 'admin', 1),
('Staff Officer 01', 'S0000001', 'staff01@immigration.gov', '0123456781', '$2y$10$Krr5QTIxWsKU/UNcOcLe5e6rCahDbfgLQFxGGpyNOi9awL2dAUPvq', 'staff', 1),
('Staff Officer 02', 'S0000002', 'staff02@immigration.gov', '0123456782', '$2y$10$Krr5QTIxWsKU/UNcOcLe5e6rCahDbfgLQFxGGpyNOi9awL2dAUPvq', 'staff', 1),
('Ahmad Bin Ali', '900101145678', 'ahmad@email.com', '0198765432', '$2y$10$spI85a8OLIc3keIQTHB2E.FYrfteDype/uFNiR3wBMteM7HZHGXA2', 'public', 1),
('Siti Nurhaliza', '920202085432', 'siti@email.com', '0187654321', '$2y$10$spI85a8OLIc3keIQTHB2E.FYrfteDype/uFNiR3wBMteM7HZHGXA2', 'public', 1);

INSERT INTO staff (user_id, staff_code, department, working_status, is_active) VALUES
(2, 'STF-001', 'Immigration Counter', 'online', 1),
(3, 'STF-002', 'Immigration Counter', 'offline', 1);

INSERT INTO services (name, code, description, estimated_duration, queue_prefix, icon, is_active) VALUES
('Passport Renewal', 'PASS-REN', 'Renew your existing passport with standard or express processing.', 20, 'A', 'fa-passport', 1),
('Passport Application', 'PASS-APP', 'Apply for a new Malaysian passport for the first time.', 30, 'B', 'fa-id-card', 1),
('Visa Application', 'VISA-APP', 'Apply for entry visa, extension or related visa services.', 25, 'C', 'fa-plane', 1),
('Work Permit', 'WORK-PER', 'Submit or renew employment pass and work permit applications.', 35, 'D', 'fa-briefcase', 1),
('Immigration Enquiry', 'IMM-ENQ', 'General immigration enquiries and document verification.', 10, 'E', 'fa-circle-question', 1);

INSERT INTO counters (counter_number, counter_name, status, is_active) VALUES
('C01', 'Counter 1 - Passport', 'available', 1),
('C02', 'Counter 2 - Passport', 'available', 1),
('C03', 'Counter 3 - Visa', 'offline', 1),
('C04', 'Counter 4 - Work Permit', 'available', 1),
('C05', 'Counter 5 - Enquiry', 'available', 1);

INSERT INTO counter_services (counter_id, service_id) VALUES
(1, 1), (1, 2),
(2, 1), (2, 2),
(3, 3),
(4, 4),
(5, 5), (5, 1);

INSERT INTO staff_counter_assignments (staff_id, counter_id, is_current) VALUES
(1, 1, 1),
(2, 4, 1);

INSERT INTO staff_service_permissions (staff_id, service_id) VALUES
(1, 1), (1, 2), (1, 5),
(2, 4), (2, 5);

INSERT INTO system_settings (setting_key, setting_value) VALUES
('department_name', 'Immigration Department'),
('office_hours', '08:00 - 17:00'),
('avg_service_time', '15'),
('max_slot_capacity', '5'),
('session_timeout', '3600'),
('queue_refresh_seconds', '5');

-- Generate appointment slots for next 14 days
INSERT INTO appointment_slots (service_id, slot_date, start_time, end_time, max_capacity, booked_count, is_available)
SELECT s.id, DATE_ADD(CURDATE(), INTERVAL d.n DAY), t.start_t, t.end_t, 5, 0, 1
FROM services s
CROSS JOIN (
    SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
    UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13
) d
CROSS JOIN (
    SELECT '09:00:00' start_t, '09:30:00' end_t UNION
    SELECT '09:30:00', '10:00:00' UNION
    SELECT '10:00:00', '10:30:00' UNION
    SELECT '10:30:00', '11:00:00' UNION
    SELECT '11:00:00', '11:30:00' UNION
    SELECT '11:30:00', '12:00:00' UNION
    SELECT '14:00:00', '14:30:00' UNION
    SELECT '14:30:00', '15:00:00' UNION
    SELECT '15:00:00', '15:30:00' UNION
    SELECT '15:30:00', '16:00:00' UNION
    SELECT '16:00:00', '16:30:00'
) t
WHERE s.is_active = 1;

-- Sample queue tickets for today
INSERT INTO queue_tickets (ticket_ref, queue_number, queue_date, visitor_name, visitor_ic, visitor_phone, user_id, service_id, counter_id, ticket_type, status, estimated_wait, created_at) VALUES
('TKT-DEMO001', 'A001', CURDATE(), 'Walk-in Customer', 'W0000001', '0111111111', NULL, 1, NULL, 'walk_in', 'waiting', 10, DATE_ADD(NOW(), INTERVAL -40 MINUTE)),
('TKT-DEMO002', 'A002', CURDATE(), 'Ahmad Bin Ali', '900101145678', '0198765432', 4, 1, 1, 'walk_in', 'serving', 0, DATE_ADD(NOW(), INTERVAL -30 MINUTE)),
('TKT-DEMO003', 'B001', CURDATE(), 'Siti Nurhaliza', '920202085432', '0187654321', 5, 2, NULL, 'walk_in', 'waiting', 20, DATE_ADD(NOW(), INTERVAL -25 MINUTE)),
('TKT-DEMO004', 'C001', CURDATE(), 'Walk-in Visitor', 'W0000002', '0122222222', NULL, 3, NULL, 'walk_in', 'waiting', 15, DATE_ADD(NOW(), INTERVAL -20 MINUTE)),
('TKT-DEMO005', 'A003', CURDATE(), 'Demo Visitor', 'W0000003', '0133333333', NULL, 1, NULL, 'walk_in', 'completed', 0, DATE_ADD(NOW(), INTERVAL -60 MINUTE)),
('TKT-DEMO006', 'E001', CURDATE(), 'Enquiry Guest', 'W0000004', '0144444444', NULL, 5, NULL, 'walk_in', 'skipped', 0, DATE_ADD(NOW(), INTERVAL -50 MINUTE));

UPDATE queue_tickets SET called_at = DATE_ADD(NOW(), INTERVAL -10 MINUTE), started_at = DATE_ADD(NOW(), INTERVAL -8 MINUTE), staff_id = 1 WHERE queue_number = 'A002' AND queue_date = CURDATE();
UPDATE queue_tickets SET completed_at = DATE_ADD(NOW(), INTERVAL -15 MINUTE), counter_id = 1, staff_id = 1, started_at = DATE_ADD(NOW(), INTERVAL -45 MINUTE), called_at = DATE_ADD(NOW(), INTERVAL -50 MINUTE) WHERE queue_number = 'A003' AND queue_date = CURDATE();
UPDATE counters SET status = 'serving' WHERE id = 1;

INSERT INTO queue_history (ticket_id, action, old_status, new_status, performed_by, notes) VALUES
(1, 'created', NULL, 'waiting', NULL, 'Walk-in registration'),
(2, 'created', NULL, 'waiting', 4, 'Walk-in registration'),
(2, 'called', 'waiting', 'serving', 2, 'Called by Staff 01'),
(3, 'created', NULL, 'waiting', 5, 'Walk-in registration'),
(4, 'created', NULL, 'waiting', NULL, 'Walk-in registration'),
(5, 'created', NULL, 'waiting', NULL, 'Walk-in registration'),
(5, 'completed', 'serving', 'completed', 2, 'Service completed'),
(6, 'created', NULL, 'waiting', NULL, 'Walk-in registration'),
(6, 'skipped', 'serving', 'skipped', 2, 'Visitor skipped');

INSERT INTO appointments (reference_no, user_id, service_id, slot_id, appointment_date, appointment_time, remarks, reminder_option, status)
SELECT 'APT-20260001', 4, 1, id, slot_date, start_time, 'Passport renewal appointment', 'email', 'confirmed'
FROM appointment_slots WHERE service_id = 1 AND slot_date = CURDATE() LIMIT 1;

UPDATE appointment_slots SET booked_count = booked_count + 1 WHERE id = (SELECT slot_id FROM appointments WHERE reference_no = 'APT-20260001');

INSERT INTO applications (reference_no, user_id, service_id, applicant_name, applicant_ic, applicant_email, applicant_phone, purpose, status, assigned_staff_id) VALUES
('APP-20260001', 4, 1, 'Ahmad Bin Ali', '900101145678', 'ahmad@email.com', '0198765432', 'Passport renewal application', 'pending', 1),
('APP-20260002', 5, 3, 'Siti Nurhaliza', '920202085432', 'siti@email.com', '0187654321', 'Visa extension request', 'under_review', 1);

INSERT INTO application_remarks (application_id, user_id, remark, is_internal) VALUES
(2, 2, 'Documents received. Under verification.', 1);

INSERT INTO activity_logs (user_id, action, module, details, ip_address) VALUES
(1, 'system_seed', 'system', 'Initial database seeded', '127.0.0.1'),
(2, 'login', 'auth', 'Staff 01 logged in', '127.0.0.1');
