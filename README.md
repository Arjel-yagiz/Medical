# Online Medical Appointment System (PHP + MySQL)

## Setup (XAMPP + phpMyAdmin) – 4 steps
1. Copy this folder to `C:\xampp\htdocs\medical_appointment_system`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open <http://localhost/phpmyadmin> -> **Import** -> choose `database.sql` -> **Go**.
   (It creates the database `medical_appointment_db`, all tables, and demo data.)
4. Open <http://localhost/medical_appointment_system/>

DB settings are in `config/db.php` (XAMPP default: user `root`, empty password).

## Demo accounts
| Role    | Username | Password    | Login page |
|---------|----------|-------------|------------|
| Admin   | admin    | Admin@123   | /admin/login.php |
| Patient | juan     | Patient@123 | /login.php |
| Doctor  | drsantos | Doctor@123  | /doctor/login.php |

Demo schedules are generated for the **next 60 days** from the day you import the SQL file
(re-import, or use Admin > Schedules > "Set doctor's schedule", to create more).

## Already imported the old database?
Run `migration_doctor_login.sql` once in phpMyAdmin (SQL tab) to add doctor logins, or re-import `database.sql`.
Other doctor usernames: drdelacruz, drreyes, drmendoza, drgarcia, drvillanueva, drlim, drtorres, drramirez (all `Doctor@123`).

## Features covered
- Doctor portal: login, today's queue, own appointments (confirm / complete / no show), own schedule, change password
- Registration (password_hash), login, sessions, logout, patient/admin access control, CSRF tokens
- Department -> Doctor -> Date (live calendar) -> Slot -> Confirm
- 100 slots/day per doctor schedule; Total / Used / Available are computed by SQL (never hard-coded)
- Calendar shows Available / Almost Full (<= 20 left) / Fully Booked; booking is blocked at 0
- Appointment no. `APPT-YYYYMMDD-###` + slot/queue number; transaction + row lock + UNIQUE keys prevent double booking
- Statuses: Pending, Confirmed, Completed, Cancelled, No Show (cancelled appointments free their slot)
- Notifications stored in DB: booking, confirmation, reminder, cancellation, reschedule; bell with unread count
- Admin: CRUD departments/doctors, schedules (single date + date range), manage appointments (confirm/cancel/reschedule/complete/no-show), notifications
- All SQL uses PDO prepared statements; output escaped with htmlspecialchars

## Quick test of the "Fully Booked" rule
Admin > Schedules > Edit a date and set **Max slots = 2**. Book twice with two patients -> the date turns **FULLY BOOKED**
and further bookings are refused. Cancel one -> the slot is available again.

## ERD (text)
```
departments 1---* doctors 1---* schedules 1---* appointments *---1 users 1---* notifications
                     doctors 1---* appointments          appointments 1---* notifications (nullable)
admins (separate login table)
```
Key rules in `appointments`: UNIQUE(schedule_id, active_slot) and UNIQUE(schedule_id, active_user) where the generated
columns become NULL when status = Cancelled.

## Folder structure
```
config/db.php          database settings
includes/              functions.php (slot logic, notifications), header.php, footer.php
patient/               dashboard, book, api_availability, appointment_form/confirmation, my_appointments, history, notifications, profile
admin/                 login, dashboard, departments, doctors, schedules, appointments, notifications
assets/                css + calendar javascript
database.sql           import this in phpMyAdmin
```
