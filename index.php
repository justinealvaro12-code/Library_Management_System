<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect(SITE_URL . "/$role/dashboard.php");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $result = dbSelect($conn, "SELECT * FROM users WHERE email = ? AND status = 'active'", 's', [$email]);
        if ($result && $row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $row['id'];
                $_SESSION['user_name'] = $row['full_name'];
                $_SESSION['user_role'] = $row['role'];
                $_SESSION['user_email']= $row['email'];
                logActivity($conn, $row['id'], 'login', 'User logged in');
                redirect(SITE_URL . '/' . $row['role'] . '/dashboard.php');
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – RMC Library Management System</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --navy: #0f1e38;
    --gold: #c9a84c;
    --gold-light: #e8c96a;
    --cream: #fdf8ef;
    --white: #ffffff;
    --red: #d64545;
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
  .divider {
    width: 60px; height: 2px;
    background: var(--gold);
    margin: 32px auto;
  }
  .features { text-align: left; }
  .feature-item {
    display: flex; align-items: flex-start; gap: 14px;
    margin-bottom: 20px;
  }
  .feature-dot {
    width: 8px; height: 8px;
    background: var(--gold);
    border-radius: 50%;
    margin-top: 6px;
    flex-shrink: 0;
  }
  .feature-item p { color: rgba(255,255,255,0.65); font-size: 14px; line-height: 1.5; }
  .right-panel {
    width: 480px;
    background: var(--cream);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 60px 50px;
  }
  .form-header { margin-bottom: 40px; }
  .form-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    color: var(--navy);
    margin-bottom: 8px;
  }
  .form-header p { color: #666; font-size: 14px; }
  .form-group { margin-bottom: 22px; }
  label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--navy);
    margin-bottom: 8px;
  }
  input[type=email], input[type=password] {
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid #ddd;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    color: var(--navy);
    background: white;
    transition: border-color 0.2s;
    outline: none;
  }
  input:focus { border-color: var(--gold); }
  .role-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 28px;
  }
  .role-btn {
    padding: 12px 8px;
    border: 1.5px solid #ddd;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    text-align: center;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    color: #555;
    transition: all 0.2s;
  }
  .role-btn:hover { border-color: var(--gold); color: var(--navy); }
  .role-label { font-size: 10px; font-weight: 500; letter-spacing: 0.5px; text-transform: uppercase; display: block; margin-top: 4px; opacity: 0.6; }
  .btn-login {
    width: 100%;
    padding: 15px;
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
  }
  .btn-login:hover { background: #1a3460; transform: translateY(-1px); }
  .btn-login:active { transform: translateY(0); }
  .error-msg {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: var(--red);
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 20px;
  }
  @media (max-width: 900px) {
    .left-panel { display: none; }
    .right-panel { width: 100%; }
  }
</style>
</head>
<body>

<div class="left-panel">
  <div class="logo-area">
    <div class="logo-icon">📚</div>
    <h1>Regis Marie College<br>Library System</h1>
    <p>Library Management System</p>
  </div>
  <div class="divider"></div>
  <div class="features">
    <div class="feature-item">
      <div class="feature-dot"></div>
      <p>Search and borrow books from the digital catalog</p>
    </div>
    <div class="feature-item">
      <div class="feature-dot"></div>
      <p>Track borrowing history and manage due dates with ease</p>
    </div>
    <div class="feature-item">
      <div class="feature-dot"></div>
      <p>Generate reports on inventory, transactions, and overdue books</p>
    </div>
    <div class="feature-item">
      <div class="feature-dot"></div>
      <p>Role-based access for Students, Librarians, and Administrators</p>
    </div>
  </div>
</div>

<div class="right-panel">
  <div class="form-header">
    <h2>Welcome back</h2>
    <p>Sign in to your library account</p>
  </div>

  <?php if ($error): ?>
    <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
    <div class="error-msg">⚠️ You don't have permission to access that page.</div>
  <?php elseif (isset($_GET['error']) && $_GET['error'] === 'disabled'): ?>
    <div class="error-msg">⚠️ Your account has been disabled. Please contact the administrator.</div>
  <?php endif; ?>

  <form method="POST">
    <?= csrfField() ?>
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="you@rmc.edu.ph" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Enter your password" required>
    </div>
    <button type="submit" class="btn-login">Sign In →</button>
  </form>

  <div style="text-align:center;margin-top:20px;font-size:13px;color:#666;">
    New student? <a href="<?= SITE_URL ?>/register.php" style="color:var(--navy);font-weight:600;text-decoration:none;">Create an account →</a>
  </div>

</div>

</body>
</html>
