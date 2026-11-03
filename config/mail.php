<?php
/**
 * Email (SMTP) settings - used for the forgot-password OTP and other emails.
 *
 * GMAIL SETUP (easiest):
 *   1. Google Account > Security > turn on 2-Step Verification
 *   2. Google Account > Security > App passwords > create one named "Queue&Care" (16 letters)
 *   3. Fill MAIL_USER with your Gmail address and MAIL_PASS with that 16-letter app password
 *      (NOT your normal Gmail password).
 *
 * Leave MAIL_USER empty = DEV MODE: no email is sent, the message is written to storage/mail-log.txt
 * and (if MAIL_DEV_SHOW_OTP is true) the OTP is shown on screen so you can still demo the feature offline.
 * Never share this file or upload it to GitHub.
 */
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);              // 587 with 'tls'  |  465 with 'ssl'
define('MAIL_SECURE', 'tls');          // 'tls' = STARTTLS, 'ssl' = implicit SSL
define('MAIL_USER', '');               // e.g. 'yourclinic@gmail.com'
define('MAIL_PASS', '');               // 16-letter Gmail app password
define('MAIL_FROM', '');               // leave empty to use MAIL_USER
define('MAIL_FROM_NAME', 'Queue&Care');
define('MAIL_VERIFY_SSL', true);       // XAMPP "certificate verify failed"? see README_EMAIL.txt (false only on your own PC)
define('MAIL_DEV_SHOW_OTP', true);     // only used in DEV MODE (MAIL_USER empty). Set false for real use.
