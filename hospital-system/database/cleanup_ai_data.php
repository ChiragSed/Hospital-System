<?php
include "../config.php";

header("Content-Type: text/plain; charset=utf-8");

echo "Starting AI/demo data cleanup...\n";

function run_delete($conn, $sql, $types = "", $params = []) {
    $stmt = $conn->prepare($sql);
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

$deleted = [];

// Reports linked to demo/testing notes or files
$deleted['reports_demo_notes'] = run_delete($conn, "DELETE FROM reports WHERE notes LIKE ?", "s", ["%Demo report upload from script%"]);
$deleted['reports_demo_file'] = run_delete($conn, "DELETE FROM reports WHERE file_name LIKE ? OR file_path LIKE ?", "ss", ["demo-report%", "%/report_%"]);

// Appointments created by scripted demo flow
$deleted['appointments_demo'] = run_delete($conn, "DELETE FROM appointments WHERE reason LIKE ?", "s", ["%Demo flow booking%"]);

// Doctors from demo/test accounts
$deleted['doctors_demo'] = run_delete(
    $conn,
    "DELETE FROM doctors WHERE email IN ('doctor1@demo.com','doctor2@demo.com') OR email LIKE ? OR email LIKE ? OR email LIKE ? OR full_name LIKE ? OR full_name LIKE ?",
    "sssss",
    ["adminmade_%", "newdoc_%", "dupdoc_%", "Dr Demo %", "Admin Created%"]
);

// Patients from demo/test accounts
$deleted['patients_demo'] = run_delete(
    $conn,
    "DELETE FROM patients WHERE email IN ('patient1@demo.com','patient2@demo.com') OR email LIKE ?",
    "s",
    ["demo_patient_%"]
);

// Admin demo account
$deleted['admins_demo'] = run_delete($conn, "DELETE FROM admins WHERE email = ?", "s", ["admin@carepoint.test"]);

foreach ($deleted as $k => $v) {
    echo $k . ": " . $v . "\n";
}

echo "Cleanup complete.\n";
?>
