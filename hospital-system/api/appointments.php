<?php
session_start();
include "../config.php";

function json_response($data, $code = 200)
{
    http_response_code($code);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

function notify_new_appointment($appointmentId, $doctorId, $patientId)
{
    $payload = json_encode([
        "message" => "New appointment booked",
        "appointment_id" => (int) $appointmentId,
        "doctor_id" => (int) $doctorId,
        "patient_id" => (int) $patientId
    ]);

    $context = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json\r\n",
            "content" => $payload,
            "timeout" => 1
        ]
    ]);

    @file_get_contents("http://127.0.0.1:3001/notify", false, $context);
}

$method = $_SERVER["REQUEST_METHOD"];
$action = $_POST["action"] ?? $_GET["action"] ?? "";

if ($method === "POST" && $action === "book") {
    if (!isset($_SESSION["patient_id"])) {
        json_response(["success" => false, "message" => "Unauthorized"], 401);
    }

    $patient_id = (int) $_SESSION["patient_id"];
    $doctor_id = (int) ($_POST["doctor_id"] ?? 0);
    $appointment_date = trim($_POST["appointment_date"] ?? "");
    $reason = trim($_POST["reason"] ?? "");
    $appointment_date = str_replace("T", " ", $appointment_date);

    if ($doctor_id <= 0 || $appointment_date === "") {
        json_response(["success" => false, "message" => "Doctor and appointment date are required."], 422);
    }

    $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, reason, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("iiss", $patient_id, $doctor_id, $appointment_date, $reason);

    if (!$stmt->execute()) {
        json_response(["success" => false, "message" => "Unable to book appointment.", "error" => $stmt->error], 500);
    }

    $appointmentId = $stmt->insert_id;
    notify_new_appointment($appointmentId, $doctor_id, $patient_id);

    json_response(["success" => true, "message" => "Appointment booked successfully.", "appointment_id" => $appointmentId]);
}

if ($method === "POST" && $action === "update_status") {
    if (!isset($_SESSION["doctor_id"])) {
        json_response(["success" => false, "message" => "Unauthorized"], 401);
    }

    $doctor_id = (int) $_SESSION["doctor_id"];
    $appointment_id = (int) ($_POST["appointment_id"] ?? 0);
    $status = trim($_POST["status"] ?? "");
    $allowed = ["Confirmed", "Cancelled"];

    if ($appointment_id <= 0 || !in_array($status, $allowed, true)) {
        json_response(["success" => false, "message" => "Invalid appointment update request."], 422);
    }

    $check = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND doctor_id = ?");
    $check->bind_param("ii", $appointment_id, $doctor_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        json_response(["success" => false, "message" => "Appointment not found or not owned by doctor."], 404);
    }

    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("sii", $status, $appointment_id, $doctor_id);
    $stmt->execute();

    json_response(["success" => true, "message" => "Appointment status updated."]);
}

if ($method === "GET" && $action === "list_doctor") {
    if (!isset($_SESSION["doctor_id"])) {
        json_response(["success" => false, "message" => "Unauthorized"], 401);
    }

    $doctor_id = (int) $_SESSION["doctor_id"];
    $stmt = $conn->prepare("SELECT a.id, a.patient_id, a.appointment_date, a.reason, a.status, p.full_name AS patient_name, p.email AS patient_email FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    json_response(["success" => true, "appointments" => $rows]);
}

if ($method === "GET" && $action === "list_patient") {
    if (!isset($_SESSION["patient_id"])) {
        json_response(["success" => false, "message" => "Unauthorized"], 401);
    }

    $patient_id = (int) $_SESSION["patient_id"];
    $stmt = $conn->prepare("SELECT a.id, a.appointment_date, a.reason, a.status, d.full_name AS doctor_name, d.specialization FROM appointments a JOIN doctors d ON d.id = a.doctor_id WHERE a.patient_id = ? ORDER BY a.appointment_date DESC");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    json_response(["success" => true, "appointments" => $rows]);
}

json_response(["success" => false, "message" => "Unknown action."], 404);

