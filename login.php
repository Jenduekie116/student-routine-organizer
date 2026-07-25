<?php
// login.php
session_start();
require_once 'database.php';

// Auto-fill email if cookie exists
$saved_email = $_COOKIE['remember_user'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    $stmt = $conn->prepare("SELECT user_id, name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            // Prevent Session Fixation
            session_regenerate_id(true);

            // Set Session Data
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            // Handle Cookie Usage for "Remember Me"
            if ($remember) {
                setcookie("remember_user", $email, time() + (86400 * 30), "/", "", false, true);
            } else {
                setcookie("remember_user", "", time() - 3600, "/");
            }

            // Redirect based on User Role
            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: student_dashboard.php");
            }
            exit();
        } else {
            $message = "Invalid password.";
        }
    } else {
        $message = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login - Student Routine Organizer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card p-4 shadow-sm border-0 rounded-4">
                <h3 class="fw-bold text-center mb-3">Welcome Back</h3>

                <?php if ($message): ?>
                    <div class="alert alert-danger p-2 small"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($saved_email) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember" <?= $saved_email ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="remember">Remember my Email</label>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 rounded-pill py-2">Login</button>
                </form>
                <p class="text-center mt-3 small">Don't have an account? <a href="register.php">Register</a></p>
            </div>
        </div>
    </div>
</div>

<!-- Custom Bootstrap Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4 rounded-4 border-0 shadow">
      <div class="modal-body">
        <div class="mb-3 text-success">
          <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.018-1.042z"/>
          </svg>
        </div>
        <h4 class="fw-bold">Success!</h4>
        <p class="text-muted small mb-4">Account created successfully.</p>
        <button type="button" class="btn btn-dark rounded-pill px-4" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS Bundle (Includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Trigger custom modal if registered successfully
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'registered') {
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
    }
</script>

</body>
</html>