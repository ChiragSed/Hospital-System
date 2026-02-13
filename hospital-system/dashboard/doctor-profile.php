<?php
session_start();
include "../config.php";

if (!isset($_SESSION["doctor_id"])) {
    header("Location: ../auth/doctor-login.html");
    exit;
}

$doctor_id = (int) $_SESSION["doctor_id"];
$stmt = $conn->prepare("SELECT full_name, email, specialization FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Doctor Profile</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <header class="site-header">
    <nav class="navbar">
      <a class="brand" href="../public/index.html"><i class="fa-solid fa-hospital"></i>CarePoint Hospital</a>
      <div class="row-actions">
        <a class="btn" href="doctor.php"><i class="fa-solid fa-arrow-left"></i>Back to Dashboard</a>
      </div>
    </nav>
  </header>

  <main class="container">
    <section class="card">
      <h2 class="section-title"><i class="fa-solid fa-id-card"></i> Doctor Profile</h2>
      <p><strong>Name:</strong> <?php echo htmlspecialchars($doctor["full_name"]); ?></p>
      <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor["email"]); ?></p>
      <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor["specialization"] ?: "-"); ?></p>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">&copy; 2026 Hospital System | Built by [Your Name]</div>
  </footer>
</body>
</html>

