-- Run in phpMyAdmin: select database medical_appointment_db > SQL tab > paste > Go
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `otp_hash`   VARCHAR(255) NOT NULL COMMENT 'password_hash() of the 6-digit code',
  `expires_at` DATETIME NOT NULL,
  `attempts`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `used`       TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reset_id`),
  KEY `idx_reset_user` (`user_id`,`used`,`expires_at`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
