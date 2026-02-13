# Manual Data Setup

Use these SQL commands in phpMyAdmin (database: `hospital_system`) to add your own users.

## 1) Create Admin User
Generate a hash for your chosen admin password using PHP:

```php
<?php echo password_hash("YourStrongAdminPassword", PASSWORD_DEFAULT); ?>
```

Then insert:

```sql
INSERT INTO admins (full_name, email, password)
VALUES ('Hospital Admin', 'your-admin@email.com', 'PASTE_HASH_HERE');
```

## 2) Create Doctor User
Generate PIN hash:

```php
<?php echo password_hash("8392", PASSWORD_DEFAULT); ?>
```

Then insert:

```sql
INSERT INTO doctors (full_name, email, password, specialization, pin_hash)
VALUES ('Dr. Your Name', 'doctor@hospital.com', 'PASTE_PIN_HASH_HERE', 'Cardiology', 'PASTE_PIN_HASH_HERE');
```

Notes:
- Doctor login uses shared hospital email on login page.
- Doctor identity is selected by unique PIN hash.

## 3) Create Patient User
Generate patient password hash:

```php
<?php echo password_hash("PatientPassword123", PASSWORD_DEFAULT); ?>
```

Then insert:

```sql
INSERT INTO patients (full_name, email, password, phone)
VALUES ('Patient Name', 'patient@email.com', 'PASTE_HASH_HERE', '+1-555-0000');
```

## 4) Optional: Add Appointment Manually

```sql
INSERT INTO appointments (patient_id, doctor_id, appointment_date, reason, status)
VALUES (1, 1, '2026-03-01 10:00:00', 'General consultation', 'Pending');
```

## 5) Optional: Add Report Record Manually

```sql
INSERT INTO reports (doctor_id, patient_id, title, notes, file_name, file_path, mime_type, file_size)
VALUES (1, 1, 'Blood Report', 'Initial upload', 'report.pdf', '../uploads/reports/report.pdf', 'application/pdf', 123456);
```
