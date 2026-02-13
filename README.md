# Hospital Appointment & Report Management System

A role-based hospital management web application built with PHP and MySQL.

## Features
- Patient signup and login
- Doctor PIN login
- Role-based dashboards (patient and doctor)
- Appointment booking by patients
- Appointment confirm/cancel by doctors
- Live appointment status updates (Pending/Confirmed/Cancelled)
- Report upload by doctors (file + notes)
- Report view/download by patients
- Public website pages with shared navigation
- Doctor listing page loaded dynamically from database
- Notification microservice support (`node-service/server.js`)

## Technologies Used
- PHP 8.x
- MySQL / MariaDB
- HTML5, CSS3, JavaScript (Fetch API)
- Font Awesome (icons)
- Apache (XAMPP)
- Node.js + Express (notification microservice)

## How To Run (XAMPP)
1. Copy project folder to `C:\xampp\htdocs\hospital-system`.
2. Start Apache and MySQL from XAMPP Control Panel.
3. Create database `hospital_system` in phpMyAdmin.
4. Import `database/hospital.sql` into `hospital_system`.
5. Run schema bootstrap once: open `http://localhost/hospital-system/database/setup_demo.php`.
6. Open: `http://localhost/hospital-system/public/index.html`.

## Manual Data Setup
- No demo users are created automatically.
- Create your own admin/doctor/patient users using: `docs/MANUAL_DATA_SETUP.md`

## Security Notes
- Doctor account creation is admin-only.
- Doctors log in using shared hospital email with unique PIN.
- PIN login includes failed-attempt lockout.
- Report upload is restricted to PDF/JPG/JPEG/PNG and max 5MB.

## Production
- Review `docs/PRODUCTION_CHECKLIST.md` before deployment.

## Test Script
1. Patient signup
2. Patient login
3. Book appointment
4. Logout
5. Doctor login
6. Confirm appointment
7. Upload report
8. Logout
9. Patient login
10. View report

## Screenshots
Add your screenshots here:
- `screenshots/home.png`
- `screenshots/patient-dashboard.png`
- `screenshots/doctor-dashboard.png`
- `screenshots/reports.png`

## Project Structure
- `public/` - public site pages
- `auth/` - login/signup pages
- `dashboard/` - role-based dashboards
- `api/` - backend endpoints
- `assets/` - shared styles
- `uploads/reports/` - uploaded report files
- `database/` - SQL schema and seed data
- `node-service/` - optional notification service
