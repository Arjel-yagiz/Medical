USE `medical_appointment_db`;
-- Adds doctor login to an existing database (run once in phpMyAdmin > SQL)
ALTER TABLE `doctors`
  ADD COLUMN `username` VARCHAR(30) DEFAULT NULL AFTER `status`,
  ADD COLUMN `password` VARCHAR(255) DEFAULT NULL AFTER `username`,
  ADD UNIQUE KEY `uq_doctors_username` (`username`);
UPDATE `doctors` SET `username`='drsantos', `password`='$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS' WHERE `doctor_id`=1;
UPDATE `doctors` SET `username`='drdelacruz', `password`='$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS' WHERE `doctor_id`=2;
UPDATE `doctors` SET `username`='drreyes', `password`='$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS' WHERE `doctor_id`=3;
UPDATE `doctors` SET `username`='drmendoza', `password`='$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS' WHERE `doctor_id`=4;
UPDATE `doctors` SET `username`='drgarcia', `password`='$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS' WHERE `doctor_id`=5;
UPDATE `doctors` SET `username`='drvillanueva', `password`='$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS' WHERE `doctor_id`=6;
UPDATE `doctors` SET `username`='drlim', `password`='$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS' WHERE `doctor_id`=7;
UPDATE `doctors` SET `username`='drtorres', `password`='$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS' WHERE `doctor_id`=8;
UPDATE `doctors` SET `username`='drramirez', `password`='$2b$10$G47S2Jnwlodva3acuE2Tzebfd75K/u.zLlo3cXhBYErAk34mT.QBS' WHERE `doctor_id`=9;
