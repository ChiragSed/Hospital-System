<?php
include "../config.php";
header("Content-Type: application/json");

$result = $conn->query("SELECT id, full_name, email, specialization FROM doctors ORDER BY full_name ASC");
$rows = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

echo json_encode([
    "success" => true,
    "doctors" => $rows
]);

