<?php
session_start();
include "../config.php";

if (!isset($_SESSION["doctor_id"])) {
    header("Location: ../auth/doctor-login.html");
    exit;
}

$doctor_id = (int) $_SESSION["doctor_id"];

$dstmt = $conn->prepare("SELECT id, full_name, email, specialization FROM doctors WHERE id = ?");
$dstmt->bind_param("i", $doctor_id);
$dstmt->execute();
$doctor = $dstmt->get_result()->fetch_assoc();

$appointments = [];
$stmt = $conn->prepare("SELECT a.id, a.appointment_date, a.reason, a.status, p.id AS patient_id, p.full_name AS patient_name, p.email AS patient_email FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

$reports = [];
$rstmt = $conn->prepare("SELECT r.id, r.patient_id, r.notes, r.file_name, r.created_at, p.full_name AS patient_name FROM reports r JOIN patients p ON p.id = r.patient_id WHERE r.doctor_id = ? ORDER BY r.created_at DESC");
$rstmt->bind_param("i", $doctor_id);
$rstmt->execute();
$rres = $rstmt->get_result();
while ($row = $rres->fetch_assoc()) {
    $reports[] = $row;
}

function status_badge_class($status)
{
    if ($status === "Confirmed") {
        return "badge-confirmed";
    }
    if ($status === "Cancelled") {
        return "badge-cancelled";
    }
    return "badge-pending";
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Doctor Dashboard</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <header class="site-header">
    <nav class="navbar">
      <a class="brand" href="../public/index.html"><i class="fa-solid fa-hospital"></i>CarePoint Hospital</a>
      <div class="row-actions">
        <a class="btn btn-warning" href="../public/index.html"><i class="fa-solid fa-house"></i>Back to Home</a>
        <a class="btn btn-secondary" href="doctor-profile.php"><i class="fa-solid fa-id-card"></i>My Profile</a>
        <a class="btn btn-danger" href="../api/logout.php" onclick="return confirm('Are you sure you want to logout?');"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
      </div>
    </nav>
  </header>

  <div class="container">
    <div id="message"></div>

    <section class="card">
      <h2 class="section-title"><i class="fa-solid fa-user-doctor"></i> Welcome, <?php echo htmlspecialchars($doctor["full_name"]); ?></h2>
      <p class="muted">Specialization: <?php echo htmlspecialchars($doctor["specialization"] ?: "General Medicine"); ?></p>
    </section>

    <section class="card">
      <h3 class="section-title"><i class="fa-solid fa-calendar-check"></i> Appointments</h3>
      <?php if (count($appointments) === 0): ?>
        <p class="muted"><i class="fa-regular fa-face-smile"></i> No appointments yet. New requests will appear here.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Patient ID</th>
              <th>Patient</th>
              <th>Email</th>
              <th>Date</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($appointments as $a): ?>
              <tr id="appointment-row-<?php echo (int)$a['id']; ?>">
                <td><?php echo (int)$a["patient_id"]; ?></td>
                <td><?php echo htmlspecialchars($a["patient_name"]); ?></td>
                <td><?php echo htmlspecialchars($a["patient_email"]); ?></td>
                <td><?php echo htmlspecialchars($a["appointment_date"]); ?></td>
                <td><?php echo htmlspecialchars($a["reason"]); ?></td>
                <td><span id="status-<?php echo (int)$a['id']; ?>" class="badge <?php echo status_badge_class($a["status"]); ?>"><?php echo htmlspecialchars($a["status"]); ?></span></td>
                <td id="action-<?php echo (int)$a['id']; ?>">
                  <?php if ($a["status"] === "Pending"): ?>
                    <div class="row-actions">
                      <button class="btn btn-success" onclick="updateStatus(<?php echo (int)$a['id']; ?>, 'Confirmed')"><i class="fa-solid fa-check"></i>Confirm</button>
                      <button class="btn btn-danger" onclick="updateStatus(<?php echo (int)$a['id']; ?>, 'Cancelled')"><i class="fa-solid fa-xmark"></i>Cancel</button>
                    </div>
                  <?php else: ?>
                    <span class="muted">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <section class="grid grid-2">
      <article class="card">
        <h3 class="section-title"><i class="fa-solid fa-file-arrow-up"></i> Upload Report</h3>
        <form id="reportForm" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">

          <label>Patient ID</label>
          <div class="input-icon">
            <i class="fa-solid fa-hashtag"></i>
            <input type="number" name="patient_id" required>
          </div>

          <label>Notes</label>
          <div class="input-icon">
            <i class="fa-solid fa-note-sticky"></i>
            <textarea name="notes" rows="4" required></textarea>
          </div>

          <label>File</label>
          <div class="input-icon">
            <i class="fa-solid fa-file-medical"></i>
            <input type="file" name="report_file" required>
          </div>

          <button type="submit"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>
        </form>
      </article>

      <article class="card">
        <h3 class="section-title"><i class="fa-solid fa-file-lines"></i> Uploaded Reports</h3>
        <?php if (count($reports) === 0): ?>
          <p class="muted"><i class="fa-regular fa-face-smile"></i> No reports uploaded yet.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Notes</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reports as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                  <td><?php echo htmlspecialchars($r['patient_name'] . ' (ID ' . $r['patient_id'] . ')'); ?></td>
                  <td><?php echo htmlspecialchars($r['notes']); ?></td>
                  <td><a class="btn" href="../api/reports.php?action=download&id=<?php echo (int)$r['id']; ?>"><i class="fa-solid fa-file-arrow-down"></i>Open</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </article>
    </section>
  </div>

  <footer class="site-footer">
    <div class="container">&copy; 2026 Hospital System | Built by [Your Name]</div>
  </footer>

  <script>
    function showMessage(text, ok) {
      const box = document.getElementById("message");
      box.className = ok ? "alert alert-success" : "alert alert-error";
      box.textContent = text;
    }

    function badgeClass(status) {
      if (status === "Confirmed") return "badge badge-confirmed";
      if (status === "Cancelled") return "badge badge-cancelled";
      return "badge badge-pending";
    }

    async function updateStatus(id, status) {
      const formData = new FormData();
      formData.append("action", "update_status");
      formData.append("appointment_id", id);
      formData.append("status", status);

      const res = await fetch("../api/appointments.php", {
        method: "POST",
        body: formData
      });

      const data = await res.json();
      showMessage(data.message || "Request processed", !!data.success);

      if (data.success) {
        const badge = document.getElementById(`status-${id}`);
        const actionCell = document.getElementById(`action-${id}`);
        badge.className = badgeClass(status);
        badge.textContent = status;
        if (actionCell) {
          actionCell.innerHTML = '<span class="muted">-</span>';
        }
      }
    }

    document.getElementById("reportForm").addEventListener("submit", async function (e) {
      e.preventDefault();
      const formData = new FormData(e.target);
      const res = await fetch("../api/reports.php", {
        method: "POST",
        body: formData
      });

      const data = await res.json();
      showMessage(data.message || "Request processed", !!data.success);
      if (data.success) {
        window.location.reload();
      }
    });
  </script>
</body>
</html>
