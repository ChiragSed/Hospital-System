<?php
session_start();
include "../config.php";

if (!isset($_SESSION["patient_id"])) {
    header("Location: ../auth/patient-login.html");
    exit;
}

$patient_id = (int) $_SESSION["patient_id"];

$pstmt = $conn->prepare("SELECT id, full_name, email, phone FROM patients WHERE id = ?");
$pstmt->bind_param("i", $patient_id);
$pstmt->execute();
$patient = $pstmt->get_result()->fetch_assoc();

$doctors = [];
$dres = $conn->query("SELECT id, full_name, specialization FROM doctors ORDER BY full_name ASC");
if ($dres) {
    while ($doc = $dres->fetch_assoc()) {
        $doctors[] = $doc;
    }
}

$appointments = [];
$astmt = $conn->prepare("SELECT a.id, a.appointment_date, a.reason, a.status, d.full_name AS doctor_name, d.specialization FROM appointments a JOIN doctors d ON d.id = a.doctor_id WHERE a.patient_id = ? ORDER BY a.appointment_date DESC");
$astmt->bind_param("i", $patient_id);
$astmt->execute();
$ares = $astmt->get_result();
while ($row = $ares->fetch_assoc()) {
    $appointments[] = $row;
}

$reports = [];
$rstmt = $conn->prepare("SELECT r.id, r.notes, r.file_name, r.created_at, d.full_name AS doctor_name FROM reports r JOIN doctors d ON d.id = r.doctor_id WHERE r.patient_id = ? ORDER BY r.created_at DESC");
$rstmt->bind_param("i", $patient_id);
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
  <title>Patient Dashboard</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <header class="site-header">
    <nav class="navbar">
      <a class="brand" href="../public/index.html"><i class="fa-solid fa-hospital"></i>CarePoint Hospital</a>
      <div class="row-actions">
        <a class="btn btn-warning" href="../public/index.html"><i class="fa-solid fa-house"></i>Back to Home</a>
        <a class="btn btn-secondary" href="patient-profile.php"><i class="fa-solid fa-id-card"></i>My Profile</a>
        <a class="btn btn-danger" href="../api/logout.php" onclick="return confirm('Are you sure you want to logout?');"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
      </div>
    </nav>
  </header>

  <div class="container">
    <div id="message"></div>

    <section class="card">
      <h2 class="section-title"><i class="fa-solid fa-user"></i> Welcome, <?php echo htmlspecialchars($patient["full_name"]); ?></h2>
      <p class="muted">Email: <?php echo htmlspecialchars($patient["email"]); ?> | Phone: <?php echo htmlspecialchars($patient["phone"] ?: "-"); ?></p>
    </section>

    <section class="grid grid-2">
      <article class="card">
        <h3 class="section-title"><i class="fa-solid fa-calendar-plus"></i> Book Appointment</h3>
        <form id="appointmentForm">
          <input type="hidden" name="action" value="book">

          <label>Doctor</label>
          <select name="doctor_id" required>
            <option value="">Select doctor</option>
            <?php foreach ($doctors as $doc): ?>
              <option value="<?php echo (int)$doc['id']; ?>">
                <?php echo htmlspecialchars($doc['full_name'] . ($doc['specialization'] ? ' - ' . $doc['specialization'] : '')); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label>Appointment Date</label>
          <div class="input-icon">
            <i class="fa-solid fa-calendar"></i>
            <input type="datetime-local" name="appointment_date" required>
          </div>

          <label>Reason</label>
          <div class="input-icon">
            <i class="fa-solid fa-notes-medical"></i>
            <textarea name="reason" rows="4" placeholder="Brief reason for visit"></textarea>
          </div>

          <button type="submit"><i class="fa-solid fa-calendar-check"></i> Book Appointment</button>
        </form>
      </article>

      <article class="card">
        <h3 class="section-title"><i class="fa-solid fa-file-medical"></i> My Reports</h3>
        <?php if (count($reports) === 0): ?>
          <p class="muted"><i class="fa-regular fa-face-smile"></i> No reports yet.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Doctor</th>
                <th>Notes</th>
                <th>Download</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reports as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                  <td><?php echo htmlspecialchars($r['doctor_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['notes']); ?></td>
                  <td><a class="btn" href="../api/reports.php?action=download&id=<?php echo (int)$r['id']; ?>"><i class="fa-solid fa-file-arrow-down"></i>Open</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </article>
    </section>

    <section class="card">
      <h3 class="section-title"><i class="fa-solid fa-calendar-days"></i> My Appointments</h3>
      <?php if (count($appointments) === 0): ?>
        <p class="muted"><i class="fa-regular fa-face-smile"></i> No appointments yet. Book your first appointment above.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Doctor</th>
              <th>Specialization</th>
              <th>Date</th>
              <th>Reason</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($appointments as $a): ?>
              <tr>
                <td><?php echo htmlspecialchars($a['doctor_name']); ?></td>
                <td><?php echo htmlspecialchars($a['specialization']); ?></td>
                <td><?php echo htmlspecialchars($a['appointment_date']); ?></td>
                <td><?php echo htmlspecialchars($a['reason']); ?></td>
                <td><span class="badge <?php echo status_badge_class($a['status']); ?>"><?php echo htmlspecialchars($a['status']); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
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

    document.getElementById("appointmentForm").addEventListener("submit", async function (e) {
      e.preventDefault();
      const formData = new FormData(e.target);
      const res = await fetch("../api/appointments.php", {
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

