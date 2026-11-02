<?php
/**
 * register.php
 * User registration page.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error  = '';
$name   = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // Validate
    if (strlen($name) < 2 || strlen($name) > 120) {
        $error = 'Name must be 2–120 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check for duplicate email
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $ins  = db()->prepare(
                'INSERT INTO users (name, email, password, created_at)
                 VALUES (?, ?, ?, NOW())'
            );
            $ins->execute([$name, $email, $hash]);

            // Log the new user in automatically
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)db()->lastInsertId();
            $_SESSION['user_name'] = $name;

            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account – AI Voice Summarizer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-body">

<div class="auth-card card shadow-sm mx-auto">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="fa-solid fa-microphone fa-2x text-primary"></i>
            <h3 class="mt-2 mb-0 fw-bold">Create Account</h3>
            <p class="text-secondary small">Join VoiceSum – it's free</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger small"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="regForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?= e($name) ?>" required minlength="2" maxlength="120"
                           placeholder="Jane Doe">
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e($email) ?>" required
                           placeholder="you@example.com">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password"
                           required minlength="8" autocomplete="new-password"
                           placeholder="At least 8 characters">
                    <button class="btn btn-outline-secondary" type="button" id="togglePass1"
                            aria-label="Show or hide password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label for="password_confirm" class="form-label">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password_confirm"
                           name="password_confirm" required minlength="8"
                           autocomplete="new-password" placeholder="Re-enter password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePass2"
                            aria-label="Show or hide password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="fa-solid fa-user-plus me-1"></i> Create Account
            </button>
        </form>

        <hr class="my-3">
        <p class="text-center small mb-0">
            Already have an account?
            <a href="login.php" class="fw-semibold">Sign in</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function bindToggle(btnId, inpId) {
    document.getElementById(btnId).addEventListener('click', function() {
        const inp  = document.getElementById(inpId);
        const icon = this.querySelector('i');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            inp.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
}
bindToggle('togglePass1', 'password');
bindToggle('togglePass2', 'password_confirm');

document.getElementById('regForm').addEventListener('submit', function(e) {
    const p = document.getElementById('password').value;
    const c = document.getElementById('password_confirm').value;
    if (p !== c) {
        e.preventDefault();
        alert('Passwords do not match.');
    }
});
</script>
</body>
</html>
