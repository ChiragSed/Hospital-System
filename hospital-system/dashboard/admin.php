<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../auth/admin-login.html?error=" . urlencode("Admin login required."));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <header class="site-header">
    <nav class="navbar">
      <a class="brand" href="../public/index.html"><i class="fa-solid fa-hospital"></i>CarePoint Hospital</a>
      <div class="row-actions">
        <a class="btn btn-warning" href="../public/index.html"><i class="fa-solid fa-house"></i>Back to Home</a>
        <a class="btn btn-danger" href="../api/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
      </div>
    </nav>
  </header>

  <main class="container">
    <section class="card">
      <h2 class="section-title"><i class="fa-solid fa-user-shield"></i> Admin Dashboard</h2>
      <p>Welcome, <?php echo htmlspecialchars($_SESSION["admin_name"] ?? "Admin"); ?>.</p>
      <div class="row-actions">
        <a class="btn" href="../auth/doctor-setup.html"><i class="fa-solid fa-user-plus"></i>Create Doctor Account</a>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">&copy; 2026 Hospital System | Built by [Your Name]</div>
  </footer>
</body>
</html>
