<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect(SITE_URL . "/$role/dashboard.php");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id     = sanitize($conn, $_POST['student_id'] ?? '');
    $full_name      = sanitize($conn, $_POST['full_name'] ?? '');
    $email          = sanitize($conn, $_POST['email'] ?? '');
    $contact_number = sanitize($conn, $_POST['contact_number'] ?? '');
    $address        = sanitize($conn, $_POST['address'] ?? '');
    $password       = $_POST['password'] ?? '';
    $confirm        = $_POST['confirm_password'] ?? '';

    if (empty($student_id) || empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check duplicate email or student_id
        $chk = $conn->query("SELECT id FROM users WHERE email='$email' OR student_id='$student_id' LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            $error = 'An account with that email or Student ID already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $conn->query("INSERT INTO users (student_id, full_name, email, password, role, contact_number, address)
                          VALUES ('$student_id', '$full_name', '$email', '$hashed', 'student', '$contact_number', '$address')");
            if ($conn->affected_rows > 0) {
                $success = 'Registration successful! You can now sign in.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register – RMC Library Management System</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --navy: #0f1e38;
    --gold: #c9a84c;
    --gold-light: #e8c96a;
    --cream: #fdf8ef;
    --white: #ffffff;
    --red: #d64545;
    --green: #166534;
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    min-height: 100vh;
    background: var(--navy);
    display: flex;
    font-family: 'DM Sans', sans-serif;
  }
  .left-panel {
    flex: 1;
    background: linear-gradient(135deg, #0f1e38 0%, #1a3460 60%, #0f1e38 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 40px;
    position: relative;
    overflow: hidden;
  }
  .left-panel::before {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    border: 1px solid rgba(201,168,76,0.15);
    border-radius: 50%;
    top: -100px; left: -100px;
  }
  .left-panel::after {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    border: 1px solid rgba(201,168,76,0.1);
    border-radius: 50%;
    bottom: -80px; right: -80px;
  }
  .logo-area { text-align: center; position: relative; z-index: 1; }
  .logo-icon {
    width: 80px; height: 80px;
    background: var(--gold);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 36px;
    margin: 0 auto 24px;
    box-shadow: 0 8px 32px rgba(201,168,76,0.3);
  }
  .logo-area h1 {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    color: var(--white);
    line-height: 1.3;
    margin-bottom: 8px;
  }
  .logo-area p { color: rgba(255,255,255,0.5); font-size: 14px; }
  .divider { width: 60px; height: 2px; background: var(--gold); margin: 32px auto; }
  .features { text-align: left; }
  .feature-item { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 20px; }
  .feature-dot { width: 8px; height: 8px; background: var(--gold); border-radius: 50%; margin-top: 6px; flex-shrink: 0; }
  .feature-item p { color: rgba(255,255,255,0.65); font-size: 14px; line-height: 1.5; }
  .right-panel {
    width: 520px;
    background: var(--cream);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 50px 50px;
    overflow-y: auto;
  }
  .form-header { margin-bottom: 32px; }
  .form-header h2 { font-family: 'Playfair Display', serif; font-size: 30px; color: var(--navy); margin-bottom: 6px; }
  .form-header p { color: #666; font-size: 14px; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .form-group { margin-bottom: 18px; }
  .form-group.full { grid-column: 1 / -1; }
  label { display: block; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; color: var(--navy); margin-bottom: 7px; }
  input[type=text], input[type=email], input[type=password], input[type=tel], textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #ddd;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: var(--navy);
    background: white;
    transition: border-color 0.2s;
    outline: none;
  }
  textarea { resize: vertical; min-height: 70px; }
  input:focus, textarea:focus { border-color: var(--gold); }
  .btn-register {
    width: 100%;
    padding: 14px;
    background: var(--navy);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    letter-spacing: 0.5px;
    margin-top: 6px;
  }
  .btn-register:hover { background: #1a3460; transform: translateY(-1px); }
  .error-msg { background: #fef2f2; border: 1px solid #fca5a5; color: var(--red); padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; }
  .success-msg { background: #f0fdf4; border: 1px solid #86efac; color: var(--green); padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; }
  .login-link { text-align: center; margin-top: 20px; font-size: 13px; color: #666; }
  .login-link a { color: var(--navy); font-weight: 600; text-decoration: none; }
  .login-link a:hover { color: var(--gold); }
  .req { color: var(--red); }
  @media (max-width: 900px) {
    .left-panel { display: none; }
    .right-panel { width: 100%; }
  }
  @media (max-width: 480px) {
    .form-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<div class="left-panel">
  <div class="logo-area">
    <div class="logo-icon">📚</div>
    <h1>Regis Marie College<br>Library System</h1>
    <p>Student Registration</p>
  </div>
  <div class="divider"></div>
  <div class="features">
    <div class="feature-item">
      <div class="feature-dot"></div>
      <p>Create your account to access the digital library catalog</p>
    </div>
    <div class="feature-item">
      <div class="feature-dot"></div>
      <p>Borrow, reserve, and track books from your dashboard</p>
    </div>
    <div class="feature-item">
      <div class="feature-dot"></div>
      <p>Use your RMC Student ID for account verification</p>
    </div>
  </div>
</div>

<div class="right-panel">
  <div class="form-header">
    <h2>Create Account</h2>
    <p>Register as an RMC student to use the library system</p>
  </div>

  <?php if ($error): ?>
    <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="success-msg">✅ <?= htmlspecialchars($success) ?>
      <br><a href="<?= SITE_URL ?>/index.php" style="font-weight:600;color:var(--green);">Go to Sign In →</a>
    </div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST">
    <div class="form-row">
      <div class="form-group">
        <label>Student ID <span class="req">*</span></label>
        <input type="text" name="student_id" placeholder="e.g. 2024-0001" required value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Full Name <span class="req">*</span></label>
        <input type="text" name="full_name" placeholder="Juan Dela Cruz" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email Address <span class="req">*</span></label>
        <input type="email" name="email" placeholder="you@rmc.edu.ph" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Contact Number</label>
        <input type="tel" name="contact_number" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password <span class="req">*</span></label>
        <input type="password" name="password" placeholder="Min. 6 characters" required>
      </div>
      <div class="form-group">
        <label>Confirm Password <span class="req">*</span></label>
        <input type="password" name="confirm_password" placeholder="Re-enter password" required>
      </div>
      <div class="form-group full">
        <label>Address</label>
        <textarea name="address" placeholder="Home address (optional)"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
      </div>
    </div>
    <button type="submit" class="btn-register">Register →</button>
  </form>
  <?php endif; ?>

  <div class="login-link">
    Already have an account? <a href="<?= SITE_URL ?>/index.php">Sign In</a>
  </div>
</div>

</body>
</html>
