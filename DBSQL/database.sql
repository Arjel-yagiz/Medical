-- =====================================================================
-- Online Medical Appointment System  |  phpMyAdmin / XAMPP ready
-- Import:  phpMyAdmin > Import > choose this file > Go
-- Works on MariaDB 10.2+ (XAMPP) and MySQL 5.7+ / 8.x
-- =====================================================================
CREATE DATABASE IF NOT EXISTS `medical_appointment_db`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `medical_appointment_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `schedules`;
DROP TABLE IF EXISTS `doctors`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- users (patients)
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `user_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`    VARCHAR(20)  NOT NULL,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(120) NOT NULL,
  `contact`       VARCHAR(20)  NOT NULL,
  `address`       VARCHAR(255) NOT NULL,
  `date_of_birth` DATE         NOT NULL,
  `gender`        ENUM('Male','Female','Other') NOT NULL,
  `username`      VARCHAR(30)  NOT NULL,
  `password`      VARCHAR(255) NOT NULL COMMENT 'password_hash() output',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_patient_id` (`patient_id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- admins (admin / staff)
-- ---------------------------------------------------------------------
CREATE TABLE `admins` (
  `admin_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `username`   VARCHAR(30)  NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- departments
-- ---------------------------------------------------------------------
CREATE TABLE `departments` (
  `department_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_name` VARCHAR(100) NOT NULL,
  `description`     VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `uq_department_name` (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- doctors
-- ---------------------------------------------------------------------
CREATE TABLE `doctors` (
  `doctor_id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id`    INT UNSIGNED NOT NULL,
  `doctor_name`      VARCHAR(100) NOT NULL,
  `specialization`   VARCHAR(100) NOT NULL,
  `license_no`       VARCHAR(30)  DEFAULT NULL,
  `experience_years` TINYINT UNSIGNED DEFAULT NULL,
  `clinic_room`      VARCHAR(30)  DEFAULT NULL,
  `consultation_fee` DECIMAL(10,2) DEFAULT NULL,
  `available_days`   VARCHAR(40)  NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri' COMMENT 'comma list: Mon,Tue,...',
  `status`           ENUM('Available','Inactive') NOT NULL DEFAULT 'Available',
  `username`         VARCHAR(30)  DEFAULT NULL COMMENT 'doctor login',
  `password`         VARCHAR(255) DEFAULT NULL COMMENT 'password_hash() output',
  PRIMARY KEY (`doctor_id`),
  UNIQUE KEY `uq_doctors_username` (`username`),
  KEY `idx_doctors_department` (`department_id`),
  CONSTRAINT `fk_doctors_department` FOREIGN KEY (`department_id`)
    REFERENCES `departments` (`department_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- schedules  (one row per doctor per date, max 100 slots)
-- ---------------------------------------------------------------------
CREATE TABLE `schedules` (
  `schedule_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`        INT UNSIGNED NOT NULL,
  `appointment_date` DATE NOT NULL,
  `start_time`       TIME NOT NULL DEFAULT '08:00:00',
  `end_time`         TIME NOT NULL DEFAULT '17:00:00',
  `max_slots`        SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  PRIMARY KEY (`schedule_id`),
  UNIQUE KEY `uq_schedule_doctor_date` (`doctor_id`,`appointment_date`),
  KEY `idx_schedule_date` (`appointment_date`),
  CONSTRAINT `fk_schedules_doctor` FOREIGN KEY (`doctor_id`)
    REFERENCES `doctors` (`doctor_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- appointments
--  * UNIQUE(schedule_id, slot_number) stops two patients getting the same slot.
--  * slot_number is set to NULL when an appointment is cancelled, which frees the slot
--    (MySQL allows many NULLs in a UNIQUE key).
-- ---------------------------------------------------------------------
CREATE TABLE `appointments` (
  `appointment_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_no`   VARCHAR(40)  NOT NULL,
  `user_id`          INT UNSIGNED NOT NULL,
  `doctor_id`        INT UNSIGNED NOT NULL,
  `schedule_id`      INT UNSIGNED NOT NULL,
  `appointment_date` DATE NOT NULL,
  `appointment_time` TIME NOT NULL,
  `slot_number`      SMALLINT UNSIGNED DEFAULT NULL COMMENT 'NULL when cancelled (frees the slot)',
  `reason`           VARCHAR(255) DEFAULT NULL,
  `status`           ENUM('Pending','Confirmed','Completed','Cancelled','No Show') NOT NULL DEFAULT 'Pending',
  `remind_sent`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`appointment_id`),
  UNIQUE KEY `uq_appointment_no` (`appointment_no`),
  UNIQUE KEY `uq_schedule_slot` (`schedule_id`,`slot_number`),
  KEY `idx_appt_user` (`user_id`),
  KEY `idx_appt_doctor` (`doctor_id`),
  KEY `idx_appt_date` (`appointment_date`),
  CONSTRAINT `fk_appt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- notifications
-- ---------------------------------------------------------------------
CREATE TABLE `notifications` (
  `notification_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `appointment_id`  INT UNSIGNED DEFAULT NULL,
  `type`            ENUM('Confirmed','Reminder','Cancelled','Updated','Info') NOT NULL DEFAULT 'Info',
  `title`           VARCHAR(100) NOT NULL,
  `message`         VARCHAR(500) NOT NULL,
  `is_read`         TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notif_user_read` (`user_id`,`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Default admin  ->  username: admin   password: Admin@123
INSERT INTO `admins` (`name`,`username`,`password`) VALUES
('System Administrator','admin','$2b$10$oBE4Qt0x8kjVfKPP0M2CpOck6yMByL.G8RBZczqBVt/9UFFJ1Fn2m');

-- Demo doctors   ->  usernames: drsantos, drdelacruz, drreyes, drmendoza, drgarcia,
--                     drvillanueva, drlim, drtorres, drramirez   password: Doctor@123

-- Demo patient   ->  username: juan   password: Patient@123
INSERT INTO `users` (`patient_id`,`name`,`email`,`contact`,`address`,`date_of_birth`,`gender`,`username`,`password`) VALUES
('PT-2026-00001','Juan Dela Cruz','juan@example.com','09171234567','Sample Street, Calabarzon','2000-05-15','Male','juan','$2b$10$r8vDYCVnXJSQAmoo0uXRRel3aVtHR5t8giYLkqazqusLsBqVE15RW');

INSERT INTO `departments` (`department_id`,`department_name`,`description`) VALUES
(1,'Pediatrics (Pedia)','Medical care for infants, children and adolescents.'),
(2,'Obstetrics and Gynecology (OB-GYN)','Women''s health, pregnancy and childbirth care.'),
(3,'Cardiology','Diagnosis and treatment of heart and blood vessel conditions.'),
(4,'Dermatology','Skin, hair and nail conditions.'),
(5,'Internal Medicine','Adult general medicine and chronic disease management.');

INSERT INTO `doctors`
(`doctor_id`,`department_id`,`doctor_name`,`specialization`,`license_no`,`experience_years`,`clinic_room`,`consultation_fee`,`available_days`,`status`,`username`,`password`) VALUES
(1,1,'Dr. Maria Santos','Pediatrics','PRC-0101234',12,'Room 101',500.00,'Mon,Tue,Wed,Thu,Fri','Available','drsantos','$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS'),
(2,1,'Dr. Juan Dela Cruz','Pediatrics','PRC-0101235',8,'Room 102',500.00,'Tue,Wed,Thu,Fri,Sat','Available','drdelacruz','$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS'),
(3,2,'Dr. Ana Reyes','OB-GYN','PRC-0201236',15,'Room 201',700.00,'Mon,Tue,Wed,Thu','Available','drreyes','$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS'),
(4,2,'Dr. Carla Mendoza','OB-GYN','PRC-0201237',10,'Room 202',700.00,'Wed,Thu,Fri,Sat','Available','drmendoza','$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS'),
(5,3,'Dr. Roberto Garcia','Cardiology','PRC-0301238',20,'Room 301',900.00,'Mon,Wed,Fri','Available','drgarcia','$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS'),
(6,3,'Dr. Elena Villanueva','Cardiology','PRC-0301239',14,'Room 302',900.00,'Tue,Thu,Sat','Available','drvillanueva','$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS'),
(7,4,'Dr. Patricia Lim','Dermatology','PRC-0401240',9,'Room 401',600.00,'Mon,Tue,Thu,Fri','Available','drlim','$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS'),
(8,5,'Dr. Miguel Torres','Internal Medicine','PRC-0501241',18,'Room 501',600.00,'Mon,Tue,Wed,Thu,Fri','Available','drtorres','$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS'),
(9,5,'Dr. Sofia Ramirez','Internal Medicine','PRC-0501242',11,'Room 502',600.00,'Tue,Wed,Thu,Fri,Sat','Available','drramirez','$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS');

-- Demo schedules: the next 60 days for every doctor, on that doctor's available days.
INSERT INTO `schedules` (`doctor_id`,`appointment_date`,`start_time`,`end_time`,`max_slots`)
SELECT d.`doctor_id`, DATE_ADD(CURDATE(), INTERVAL n.`n` DAY), '08:00:00', '17:00:00', 100
FROM `doctors` d
JOIN (SELECT a.`i` + b.`i` * 10 AS `n`
      FROM (SELECT 0 AS `i` UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
            UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
      CROSS JOIN
           (SELECT 0 AS `i` UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
            UNION ALL SELECT 5) b) n
  ON n.`n` < 60
WHERE FIND_IN_SET(DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL n.`n` DAY), '%a'), d.`available_days`) > 0;
