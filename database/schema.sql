-- ============================================================
-- Passenger Information System (PIS) - Database Schema
-- BIC21203 Web Development Group Project
-- ============================================================

CREATE DATABASE IF NOT EXISTS pis_db;
USE pis_db;

-- ------------------------------------------------------------
-- Table: stations
-- ------------------------------------------------------------
CREATE TABLE stations (
    station_id      INT AUTO_INCREMENT PRIMARY KEY,
    station_name    VARCHAR(100) NOT NULL,
    station_code    VARCHAR(10) NOT NULL UNIQUE,
    platform_count  INT NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Table: trains
-- ------------------------------------------------------------
CREATE TABLE trains (
    train_id            INT AUTO_INCREMENT PRIMARY KEY,
    train_no            VARCHAR(20) NOT NULL UNIQUE,
    line_name           VARCHAR(100) NOT NULL,
    origin_station_id   INT NOT NULL,
    destination_station_id INT NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (origin_station_id) REFERENCES stations(station_id),
    FOREIGN KEY (destination_station_id) REFERENCES stations(station_id)
);

-- ------------------------------------------------------------
-- Table: schedules
-- Links a train to a station stop, with timing + live status
-- ------------------------------------------------------------
CREATE TABLE schedules (
    schedule_id     INT AUTO_INCREMENT PRIMARY KEY,
    train_id        INT NOT NULL,
    station_id      INT NOT NULL,
    platform_no     VARCHAR(10),
    arrival_time    DATETIME,
    departure_time  DATETIME,
    status          ENUM('on_time', 'delayed', 'cancelled', 'boarding', 'departed') NOT NULL DEFAULT 'on_time',
    delay_minutes   INT DEFAULT 0,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (train_id) REFERENCES trains(train_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(station_id)
);

-- ------------------------------------------------------------
-- Table: alerts
-- Announcements / delay notices shown on the public site
-- ------------------------------------------------------------
CREATE TABLE alerts (
    alert_id        INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id     INT,
    station_id      INT,
    message         VARCHAR(255) NOT NULL,
    severity        ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'info',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Table: admin_users
-- Staff accounts for the admin panel
-- ------------------------------------------------------------
CREATE TABLE admin_users (
    user_id         INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(100),
    role            ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Feedback / contact form submissions
-- ------------------------------------------------------------
CREATE TABLE feedback (
    feedback_id     INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NOT NULL,
    message         TEXT NOT NULL,
    submitted_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Sample data (so pages show something immediately)
-- ============================================================

INSERT INTO stations (station_name, station_code, platform_count) VALUES
('Kuala Lumpur Sentral', 'KLS', 6),
('Ipoh', 'IPH', 3),
('Butterworth', 'BWH', 2),
('Johor Bahru Sentral', 'JBS', 4),
('Gemas', 'GMS', 2),
('Padang Besar', 'PDB', 2),
('Alor Setar', 'AS', 2),
('Tanjung Malim', 'TM', 1),
('Seremban', 'SRN', 2);

INSERT INTO trains (train_no, line_name, origin_station_id, destination_station_id) VALUES
('ETS-301', 'West Coast Line', 1, 3),
('ETS-305', 'West Coast Line', 1, 2),
('KTM-112', 'South Line', 1, 4),
('ETS-9315', 'West Coast Line (ETS Platinum)', 6, 9),
('ETS-9316', 'West Coast Line (ETS Platinum)', 9, 6);

-- Schedule times are anchored to CURDATE() rather than a fixed calendar
-- date, so a fresh import always seeds "today's" board instead of a date
-- that goes stale the moment it's in the past.
INSERT INTO schedules (train_id, station_id, platform_no, arrival_time, departure_time, status, delay_minutes) VALUES
-- ETS-301: Kuala Lumpur Sentral -> Butterworth
(1, 1, '2', NULL, CONCAT(CURDATE(), ' ', '08:00:00'), 'on_time', 0),
(1, 3, '1', CONCAT(CURDATE(), ' ', '11:30:00'), NULL, 'on_time', 0),
-- ETS-305: Kuala Lumpur Sentral -> Ipoh (signal maintenance delay)
(2, 1, '3', NULL, CONCAT(CURDATE(), ' ', '09:15:00'), 'delayed', 15),
(2, 2, '2', CONCAT(CURDATE(), ' ', '11:45:00'), NULL, 'delayed', 15),
-- KTM-112: Kuala Lumpur Sentral -> Gemas -> Johor Bahru Sentral (South Line)
(3, 1, '5', NULL, CONCAT(CURDATE(), ' ', '10:00:00'), 'on_time', 0),
(3, 5, '1', CONCAT(CURDATE(), ' ', '10:45:00'), CONCAT(CURDATE(), ' ', '10:47:00'), 'on_time', 0),
(3, 4, '3', CONCAT(CURDATE(), ' ', '11:30:00'), NULL, 'on_time', 0),
-- ETS-9316: Seremban -> ... -> Padang Besar (early-morning northbound working)
(5, 9, '1', NULL, CONCAT(CURDATE(), ' ', '06:30:00'), 'on_time', 0),
(5, 1, '4', CONCAT(CURDATE(), ' ', '07:15:00'), CONCAT(CURDATE(), ' ', '07:18:00'), 'on_time', 0),
(5, 8, '1', CONCAT(CURDATE(), ' ', '08:00:00'), CONCAT(CURDATE(), ' ', '08:02:00'), 'on_time', 0),
(5, 2, '1', CONCAT(CURDATE(), ' ', '09:10:00'), CONCAT(CURDATE(), ' ', '09:13:00'), 'on_time', 0),
(5, 3, '2', CONCAT(CURDATE(), ' ', '10:25:00'), CONCAT(CURDATE(), ' ', '10:30:00'), 'on_time', 0),
(5, 7, '2', CONCAT(CURDATE(), ' ', '11:10:00'), CONCAT(CURDATE(), ' ', '11:12:00'), 'on_time', 0),
(5, 6, '1', CONCAT(CURDATE(), ' ', '11:45:00'), NULL, 'on_time', 0),
-- ETS-9315: Padang Besar -> ... -> Seremban (afternoon southbound working)
(4, 6, '1', NULL, CONCAT(CURDATE(), ' ', '13:00:00'), 'on_time', 0),
(4, 7, '2', CONCAT(CURDATE(), ' ', '13:35:00'), CONCAT(CURDATE(), ' ', '13:37:00'), 'on_time', 0),
(4, 3, '2', CONCAT(CURDATE(), ' ', '14:20:00'), CONCAT(CURDATE(), ' ', '14:25:00'), 'on_time', 0),
(4, 2, '1', CONCAT(CURDATE(), ' ', '15:40:00'), CONCAT(CURDATE(), ' ', '15:43:00'), 'on_time', 0),
(4, 8, '1', CONCAT(CURDATE(), ' ', '16:50:00'), CONCAT(CURDATE(), ' ', '16:52:00'), 'on_time', 0),
(4, 1, '4', CONCAT(CURDATE(), ' ', '17:35:00'), CONCAT(CURDATE(), ' ', '17:38:00'), 'on_time', 0),
(4, 9, '1', CONCAT(CURDATE(), ' ', '18:20:00'), NULL, 'on_time', 0);

INSERT INTO alerts (schedule_id, station_id, message, severity) VALUES
(3, 1, 'ETS-305 to Ipoh is delayed by 15 minutes due to signal maintenance.', 'warning'),
(NULL, 1, 'Platform 2 and 3 undergoing cleaning between 2pm-3pm today.', 'info');

-- Default admin account: username = admin, password = admin123
-- (hash generated with PHP password_hash('admin123', PASSWORD_DEFAULT))
INSERT INTO admin_users (username, password_hash, full_name, role) VALUES
('admin', '$2y$10$8JR5fz.bN4FSf6AbJgosVOB8Ei0t9KCvMvpk3mENmnp2gJv/qnwjy', 'System Administrator', 'admin');
