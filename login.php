<?php
/**
 * login.php
 * User login page.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Already logged in → dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate
    if (empty($email) || empty($password)) {
        $error = 'Both email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In – AI Voice Summarizer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-body">

<div class="auth-card card shadow-sm mx-auto">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="fa-solid fa-microphone fa-2x text-primary"></i>
            <h3 class="mt-2 mb-0 fw-bold">Sign In</h3>
            <p class="text-secondary small">Welcome back to VoiceSum</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger small"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e($email) ?>" required autocomplete="email"
                           placeholder="you@example.com">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password"
                           required autocomplete="current-password" placeholder="••••••••">
                    <button class="btn btn-outline-secondary" type="button" id="togglePass"
                            aria-label="Show or hide password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
            </button>
        </form>

        <hr class="my-3">
        <p class="text-center small mb-0">
            Don't have an account?
            <a href="register.php" class="fw-semibold">Create one</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show / hide password
document.getElementById('togglePass').addEventListener('click', function() {
    const inp = document.getElementById('password');
    const icon = this.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});

// Prevent form submission if fields empty
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value.trim();
    const pass  = document.getElementById('password').value;
    if (!email || !pass) {
        e.preventDefault();
        alert('Please fill in all fields.');
    }
});
</script>
</body>
</html>
