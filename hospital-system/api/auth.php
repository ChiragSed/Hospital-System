<?php
include "../config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "Invalid request";
    exit;
}

function verify_secret($input, $stored)
{
    if (!$stored) {
        return false;
    }
    return password_verify($input, $stored);
}

function validate_pin_strength($pin)
{
    if (!preg_match('/^\d{4,8}$/', $pin)) {
        return "PIN must be 4 to 8 digits.";
    }

    return null;
}

function pin_already_in_use($conn, $pin)
{
    $result = $conn->query("SELECT pin_hash FROM doctors");
    if (!$result) {
        return false;
    }
    while ($row = $result->fetch_assoc()) {
        if (verify_secret($pin, $row["pin_hash"])) {
            return true;
        }
    }
    return false;
}

function go_with_error($path, $message)
{
    header("Location: " . $path . "?error=" . urlencode($message));
    exit;
}

function table_exists($conn, $table)
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $res = $conn->query("SHOW TABLES LIKE '$safeTable'");
    return $res && $res->num_rows > 0;
}

function ensure_doctor_attempts_table($conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS doctor_login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(64) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        success TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_ip_attempted_at (ip_address, attempted_at)
    )");
}

function client_ip()
{
    $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    return substr($ip, 0, 64);
}

function is_local_ip($ip)
{
    return in_array($ip, ["127.0.0.1", "::1", "localhost"], true);
}

function record_doctor_attempt($conn, $ip, $success)
{
    $ok = $success ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO doctor_login_attempts (ip_address, success) VALUES (?, ?)");
    $stmt->bind_param("si", $ip, $ok);
    $stmt->execute();
    $stmt->close();
}

function clear_doctor_failed_attempts($conn, $ip)
{
    $stmt = $conn->prepare("DELETE FROM doctor_login_attempts WHERE ip_address = ? AND success = 0");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
}

function is_doctor_login_locked($conn, $ip, $maxAttempts = 5, $windowSeconds = 900, $lockSeconds = 900)
{
    $windowStart = date("Y-m-d H:i:s", time() - $windowSeconds);
    $stmt = $conn->prepare("SELECT COUNT(*) AS fail_count, MAX(attempted_at) AS latest_fail FROM doctor_login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at >= ?");
    $stmt->bind_param("ss", $ip, $windowStart);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    $failCount = (int) ($row["fail_count"] ?? 0);
    $latestFail = $row["latest_fail"] ?? null;
    if ($failCount < $maxAttempts || !$latestFail) {
        return false;
    }

    $lockedUntil = strtotime($latestFail) + $lockSeconds;
    return time() < $lockedUntil;
}

function doctor_pin_lookup($conn, $pin)
{
    $result = $conn->query("SELECT id, full_name, pin_hash FROM doctors");
    if (!$result) {
        return null;
    }
    $matches = [];

    while ($row = $result->fetch_assoc()) {
        if (verify_secret($pin, $row["pin_hash"])) {
            $matches[] = $row;
        }
    }

    if (count($matches) > 1) {
        return ["error" => "duplicate_pin"];
    }
    if (count($matches) === 1) {
        return $matches[0];
    }
    return null;
}

$action = $_POST["action"] ?? "";

if ($action === "admin_login") {
    if (!table_exists($conn, "admins")) {
        go_with_error("../auth/admin-login.html", "Admin setup missing. Run database/setup_demo.php, then create an admin user manually.");
    }

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        go_with_error("../auth/admin-login.html", "Email and password are required.");
    }

    $stmt = $conn->prepare("SELECT id, full_name, password FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if (!$admin || !verify_secret($password, $admin["password"])) {
        go_with_error("../auth/admin-login.html", "Invalid admin credentials.");
    }

    session_start();
    unset($_SESSION["patient_id"], $_SESSION["patient_name"], $_SESSION["doctor_id"], $_SESSION["doctor_name"]);
    $_SESSION["admin_id"] = $admin["id"];
    $_SESSION["admin_name"] = $admin["full_name"];

    header("Location: ../dashboard/admin.php");
    exit;
}

if ($action === "patient_signup") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $rawPass = $_POST["password"] ?? "";

    if ($full_name === "" || $email === "" || $rawPass === "") {
        go_with_error("../auth/patient-signup.html", "All required fields must be filled.");
    }

    $password = password_hash($rawPass, PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM patients WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        go_with_error("../auth/patient-signup.html", "Email already registered.");
    }
    $check->close();

    $stmt = $conn->prepare("INSERT INTO patients (full_name, email, password, phone) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $full_name, $email, $password, $phone);

    if ($stmt->execute()) {
        header("Location: ../auth/patient-login.html");
        exit;
    }

    go_with_error("../auth/patient-signup.html", "Signup failed. Please try again.");
}

if ($action === "patient_login") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        go_with_error("../auth/patient-login.html", "Email and password are required.");
    }

    $stmt = $conn->prepare("SELECT id, full_name, password FROM patients WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        go_with_error("../auth/patient-login.html", "Account not found.");
    }

    if (!verify_secret($password, $user["password"])) {
        go_with_error("../auth/patient-login.html", "Wrong password.");
    }

    session_start();
    unset($_SESSION["doctor_id"], $_SESSION["doctor_name"]);
    $_SESSION["patient_id"] = $user["id"];
    $_SESSION["patient_name"] = $user["full_name"];

    header("Location: ../dashboard/patient.php");
    exit;
}

if ($action === "doctor_setup") {
    session_start();
    if (!isset($_SESSION["admin_id"])) {
        go_with_error("../auth/admin-login.html", "Admin access required to create doctor accounts.");
    }

    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $specialization = trim($_POST["specialization"] ?? "");
    $pin = $_POST["pin"] ?? "";
    $hospitalLoginEmail = "doctors@carepointhospital.com";

    if ($full_name === "" || $email === "" || $pin === "") {
        go_with_error("../auth/doctor-setup.html", "All required fields must be filled.");
    }

    $pinPolicyError = validate_pin_strength($pin);
    if ($pinPolicyError !== null) {
        go_with_error("../auth/doctor-setup.html", $pinPolicyError);
    }

    // Doctors use one shared hospital login email, but DB email must stay unique.
    $storageEmail = $email;
    if (strcasecmp($email, $hospitalLoginEmail) === 0) {
        $storageEmail = "doctor+" . bin2hex(random_bytes(6)) . "@carepointhospital.com";
    }

    $check = $conn->prepare("SELECT id FROM doctors WHERE email = ?");
    $check->bind_param("s", $storageEmail);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        go_with_error("../auth/doctor-setup.html", "Email already registered as doctor.");
    }
    $check->close();

    if (pin_already_in_use($conn, $pin)) {
        go_with_error("../auth/doctor-setup.html", "PIN already in use. Choose a different PIN.");
    }

    $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO doctors (full_name, email, password, specialization, pin_hash) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $full_name, $storageEmail, $pin_hash, $specialization, $pin_hash);

    if ($stmt->execute()) {
        header("Location: ../dashboard/admin.php");
        exit;
    }

    go_with_error("../auth/doctor-setup.html", "Doctor setup failed. Please try again.");
}

if ($action === "doctor_login_pin") {
    $email = trim($_POST["email"] ?? "");
    $pin = $_POST["pin"] ?? "";
    $hospitalLoginEmail = "doctors@carepointhospital.com";
    ensure_doctor_attempts_table($conn);
    $ip = client_ip();
    $isLocal = is_local_ip($ip);

    if ($email === "" || $pin === "") {
        go_with_error("../auth/doctor-login.html", "Email and PIN are required.");
    }

    if (!$isLocal && is_doctor_login_locked($conn, $ip)) {
        go_with_error("../auth/doctor-login.html", "Too many failed attempts. Try again in 15 minutes.");
    }

    if (strcasecmp($email, $hospitalLoginEmail) !== 0) {
        if (!$isLocal) {
            record_doctor_attempt($conn, $ip, false);
        }
        go_with_error("../auth/doctor-login.html", "Use the hospital doctor login email.");
    }

    $doctor = doctor_pin_lookup($conn, $pin);
    if (is_array($doctor) && isset($doctor["error"]) && $doctor["error"] === "duplicate_pin") {
        if (!$isLocal) {
            record_doctor_attempt($conn, $ip, false);
        }
        go_with_error("../auth/doctor-login.html", "PIN conflict detected. Contact admin.");
    }
    if (!$doctor) {
        if (!$isLocal) {
            record_doctor_attempt($conn, $ip, false);
        }
        go_with_error("../auth/doctor-login.html", "Wrong PIN.");
    }

    if (!$isLocal) {
        clear_doctor_failed_attempts($conn, $ip);
        record_doctor_attempt($conn, $ip, true);
    }
    session_start();
    unset($_SESSION["patient_id"], $_SESSION["patient_name"], $_SESSION["admin_id"], $_SESSION["admin_name"]);
    $_SESSION["doctor_id"] = $doctor["id"];
    $_SESSION["doctor_name"] = $doctor["full_name"];

    header("Location: ../dashboard/doctor.php");
    exit;
}

echo "Unknown action.";

