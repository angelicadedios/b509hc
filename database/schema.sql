SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS dispensations;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS files;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS restock_logs;
DROP TABLE IF EXISTS medicines;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role          ENUM('patient','staff') NOT NULL,
    is_admin      TINYINT(1) NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    email         VARCHAR(190) NOT NULL,
    google_sub    VARCHAR(64)  NULL,
    password_hash VARCHAR(255) NULL,
    display_name  VARCHAR(150) NOT NULL DEFAULT '',
    approval_status    ENUM('new','pending','verified') NOT NULL DEFAULT 'new',
    submitted_at       DATETIME NULL,
    review_note        VARCHAR(255) NULL,
    verified_at        DATETIME NULL,
    verified_by        INT UNSIGNED NULL,
    deactivated_reason VARCHAR(255) NULL,
    deactivated_at     DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_approval (role, approval_status),
    UNIQUE KEY uq_users_google (google_sub)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE patients (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    is_self       TINYINT(1) NOT NULL DEFAULT 0,
    relationship  VARCHAR(30)  NULL,
    dep_seq       INT UNSIGNED NULL,
    philhealth_no VARCHAR(20)  NULL,
    philhealth_file VARCHAR(40) NULL,
    first_name    VARCHAR(50)  NOT NULL,
    middle_name   VARCHAR(50)  NULL,
    last_name     VARCHAR(50)  NOT NULL,
    suffix        VARCHAR(10)  NULL,
    sex           ENUM('Male','Female') NOT NULL,
    birth_date    DATE NOT NULL,
    mobile        CHAR(11) NOT NULL,
    region        VARCHAR(100) NOT NULL,
    province      VARCHAR(100) NOT NULL,
    municipality  VARCHAR(100) NOT NULL,
    barangay      VARCHAR(100) NOT NULL,
    district      VARCHAR(60)  NULL,
    zone          VARCHAR(20)  NULL,
    street        VARCHAR(150) NOT NULL,
    is_deleted    TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_patients_user (user_id),
    KEY idx_patients_philhealth (philhealth_no),
    CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_no         VARCHAR(20) NOT NULL,
    user_id          INT UNSIGNED NOT NULL,
    patient_id       INT UNSIGNED NOT NULL,
    booked_for       ENUM('self','dependent') NOT NULL,
    visit_type       ENUM('First Time','Follow-up') NOT NULL,
    service          ENUM('Consultation','Result Reading') NOT NULL,
    appt_date        DATE NOT NULL,
    slot_time        TIME NOT NULL,
    priority         ENUM('Regular','Senior Citizen','PWD','Pregnant') NOT NULL DEFAULT 'Regular',
    notes            VARCHAR(500) NULL,
    result_file      VARCHAR(100) NULL,
    id_file          VARCHAR(100) NULL,
    status           ENUM('upcoming','completed','missed','cancelled') NOT NULL DEFAULT 'upcoming',
    cancelled_by     ENUM('patient','staff') NULL,
    cancel_reason    VARCHAR(255) NULL,
    cancelled_at     DATETIME NULL,
    offer_date       DATE NULL,
    offer_time       TIME NULL,
    offer_status     ENUM('pending','accepted','declined','expired') NULL,
    rescheduled_from INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    slot_key VARCHAR(30) GENERATED ALWAYS AS
        (IF(status = 'upcoming', CONCAT(appt_date, ' ', slot_time), NULL)) STORED,
    UNIQUE KEY uq_active_slot (slot_key),
    KEY idx_appt_date (appt_date, status),
    KEY idx_appt_user (user_id),
    KEY idx_appt_patient (patient_id),
    KEY idx_appt_offer (offer_date, offer_status),
    CONSTRAINT fk_appt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    message    VARCHAR(500) NOT NULL,
    link       VARCHAR(255) NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_user (user_id, is_read),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medicines (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    in_stock      INT UNSIGNED NOT NULL DEFAULT 0,
    critical_qty  INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_medicine_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE restock_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id  INT UNSIGNED NOT NULL,
    quantity     INT UNSIGNED NOT NULL,
    staff_id     INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_restock_med FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    CONSTRAINT fk_restock_staff FOREIGN KEY (staff_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE doctors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_doctor_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Medicine Given": one row per medicine handed to a patient after a visit
CREATE TABLE dispensations (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL,
    patient_id     INT UNSIGNED NOT NULL,
    medicine_id    INT UNSIGNED NOT NULL,
    quantity       INT UNSIGNED NOT NULL,
    doctor_id      INT UNSIGNED NOT NULL,
    staff_id       INT UNSIGNED NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_disp_med (medicine_id, created_at),
    KEY idx_disp_date (created_at),
    KEY idx_disp_appt (appointment_id),
    KEY idx_disp_patient (patient_id),
    CONSTRAINT fk_disp_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    CONSTRAINT fk_disp_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
    CONSTRAINT fk_disp_med FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    CONSTRAINT fk_disp_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    CONSTRAINT fk_disp_staff FOREIGN KEY (staff_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uploaded files (PhilHealth IDs, results, Senior/PWD IDs) are kept here because Vercel can't store files
CREATE TABLE files (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(40) NOT NULL,
    mime       VARCHAR(40) NOT NULL,
    size       INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL,
    data       MEDIUMBLOB NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_files_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login sessions (Vercel runs on many servers, so sessions can't be files)
CREATE TABLE sessions (
    id         VARCHAR(128) NOT NULL PRIMARY KEY,
    data       MEDIUMTEXT NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    KEY idx_sessions_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default staff login: staff@b509hc.local / Staff@509  (change it right after your first login)
INSERT INTO users (role, is_admin, email, password_hash, display_name, approval_status) VALUES
('staff', 1, 'staff@b509hc.local', '$2y$10$95K2YAgHyb7/6YXDmVua/eFz7uN5DfTO1CXvZmmz/VRMAePmLoC8O', 'Health Center Staff', 'verified');

INSERT INTO doctors (name) VALUES ('Dr. Juan Dela Cruz'), ('Dra. Maria Reyes');

INSERT INTO medicines (name, in_stock, critical_qty) VALUES
('Amlodipine 5mg', 240, 50),
('Amoxicillin 500mg', 35, 50),
('Ascorbic Acid 500mg', 500, 100),
('Cetirizine 10mg', 120, 30),
('Ferrous Sulfate + Folic Acid', 80, 60),
('Losartan 50mg', 300, 60),
('Metformin 500mg', 45, 60),
('Oral Rehydration Salts', 60, 20),
('Paracetamol 500mg', 650, 150),
('Salbutamol 2mg', 18, 25);
