<?php
session_start();
include "../config.php";

function ensure_reports_directory()
{
    $dir = __DIR__ . "/../uploads/reports";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function json_response($data, $code = 200)
{
    http_response_code($code);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];
$action = $_POST["action"] ?? $_GET["action"] ?? "";

if ($method === "POST" && $action === "upload") {
    if (!isset($_SESSION["doctor_id"])) {
        json_response(["success" => false, "message" => "Unauthorized"], 401);
    }

    if (!isset($_FILES["report_file"]) || $_FILES["report_file"]["error"] !== UPLOAD_ERR_OK) {
        json_response(["success" => false, "message" => "Please select a valid file."], 422);
    }

    $doctor_id = (int) $_SESSION["doctor_id"];
    $patient_id = (int) ($_POST["patient_id"] ?? 0);
    $notes = trim($_POST["notes"] ?? "");
    $title = trim($_POST["title"] ?? "Medical Report");

    if ($patient_id <= 0 || $notes === "") {
        json_response(["success" => false, "message" => "Patient ID and notes are required."], 422);
    }

    $patientCheck = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $patientCheck->bind_param("i", $patient_id);
    $patientCheck->execute();
    $patientCheck->store_result();

    if ($patientCheck->num_rows === 0) {
        json_response(["success" => false, "message" => "Patient not found."], 404);
    }

    $file = $_FILES["report_file"];
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ((int) $file["size"] <= 0 || (int) $file["size"] > $maxSize) {
        json_response(["success" => false, "message" => "File must be between 1 byte and 5MB."], 422);
    }

    $extension = pathinfo($file["name"], PATHINFO_EXTENSION);
    $extension = strtolower($extension);
    $allowedExtensions = ["pdf", "jpg", "jpeg", "png"];
    if (!in_array($extension, $allowedExtensions, true)) {
        json_response(["success" => false, "message" => "Only PDF, JPG, JPEG, and PNG files are allowed."], 422);
    }

    $tmpMime = mime_content_type($file["tmp_name"]);
    $allowedMimes = ["application/pdf", "image/jpeg", "image/png"];
    if (!in_array($tmpMime, $allowedMimes, true)) {
        json_response(["success" => false, "message" => "Uploaded file type is not allowed."], 422);
    }

    $originalName = basename($file["name"]);
    $safeName = "report_" . time() . "_" . bin2hex(random_bytes(4));
    if ($extension !== "") {
        $safeName .= "." . $extension;
    }

    ensure_reports_directory();

    $relativePath = "../uploads/reports/" . $safeName;
    $absolutePath = __DIR__ . "/../uploads/reports/" . $safeName;

    if (!move_uploaded_file($file["tmp_name"], $absolutePath)) {
        json_response(["success" => false, "message" => "Unable to save uploaded file."], 500);
    }

    $mime = mime_content_type($absolutePath);
    $size = filesize($absolutePath);

    $stmt = $conn->prepare("INSERT INTO reports (doctor_id, patient_id, title, notes, file_name, file_path, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssssi", $doctor_id, $patient_id, $title, $notes, $originalName, $relativePath, $mime, $size);

    if (!$stmt->execute()) {
        json_response(["success" => false, "message" => "Unable to save report record.", "error" => $stmt->error], 500);
    }

    json_response(["success" => true, "message" => "Report uploaded successfully."]);
}

if ($method === "GET" && $action === "list_patient") {
    if (!isset($_SESSION["patient_id"])) {
        json_response(["success" => false, "message" => "Unauthorized"], 401);
    }

    $patient_id = (int) $_SESSION["patient_id"];
    $stmt = $conn->prepare("SELECT r.id, r.title, r.notes, r.file_name, r.file_size, r.created_at, d.full_name AS doctor_name FROM reports r JOIN doctors d ON d.id = r.doctor_id WHERE r.patient_id = ? ORDER BY r.created_at DESC");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    json_response(["success" => true, "reports" => $rows]);
}

if ($method === "GET" && $action === "download") {
    if (!isset($_SESSION["patient_id"]) && !isset($_SESSION["doctor_id"])) {
        http_response_code(401);
        echo "Unauthorized";
        exit;
    }

    $report_id = (int) ($_GET["id"] ?? 0);
    if ($report_id <= 0) {
        http_response_code(422);
        echo "Invalid report id";
        exit;
    }

    $stmt = $conn->prepare("SELECT id, doctor_id, patient_id, file_name, file_path, mime_type FROM reports WHERE id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();

    if (!$report) {
        http_response_code(404);
        echo "Report not found";
        exit;
    }

    if (isset($_SESSION["doctor_id"])) {
        if ((int) $_SESSION["doctor_id"] !== (int) $report["doctor_id"]) {
            http_response_code(403);
            echo "Access denied";
            exit;
        }
    } elseif (isset($_SESSION["patient_id"])) {
        if ((int) $_SESSION["patient_id"] !== (int) $report["patient_id"]) {
            http_response_code(403);
            echo "Access denied";
            exit;
        }
    }

    $absolutePath = __DIR__ . "/" . $report["file_path"];
    if (!file_exists($absolutePath)) {
        http_response_code(404);
        echo "File missing";
        exit;
    }

    header("Content-Type: " . ($report["mime_type"] ?: "application/octet-stream"));
    header("Content-Disposition: attachment; filename=\"" . basename($report["file_name"]) . "\"");
    header("Content-Length: " . filesize($absolutePath));

    readfile($absolutePath);
    exit;
}

json_response(["success" => false, "message" => "Unknown action."], 404);

