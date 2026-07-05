<?php
require_once 'config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = clean($_POST['name']);
    $email       = clean($_POST['email']);
    $password    = $_POST['password'];
    $confirm     = $_POST['confirm_password'];
    $department  = clean($_POST['department']);
    $roll_number = clean($_POST['roll_number']);

    if (strlen($name) < 3) {
        $error = "Name must be at least 3 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "An account with this email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, department, roll_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $hash, $department, $roll_number);
            if ($stmt->execute()) {
                $success = "Account created successfully! You can now log in.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

$departments = ['Computer Science', 'Information Technology', 'Electronics & Communication', 'Mechanical Engineering', 'Civil Engineering', 'Electrical Engineering', 'Business Administration', 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #0b0f1a;
    --card: #111827;
    --border: #1f2d3d;
    --accent: #3b82f6;
    --text: #f1f5f9;
    --muted: #64748b;
    --error: #f87171;
    --success: #34d399;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 16px;
  }

  .bg-glow {
    position: fixed; inset: 0; z-index: 0;
    background: radial-gradient(ellipse 80% 60% at 80% 10%, rgba(59,130,246,0.12), transparent),
                radial-gradient(ellipse 60% 80% at 10% 90%, rgba(16,185,129,0.08), transparent);
  }

  .container {
    position: relative; z-index: 1;
    width: min(520px, 100%);
    background: var(--card);
    border-radius: 24px;
    border: 1px solid var(--border);
    padding: 48px 44px;
    box-shadow: 0 40px 80px rgba(0,0,0,0.4);
    animation: slideUp 0.5s ease both;
  }

  @keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .logo {
    font-family: 'Syne', sans-serif;
    font-size: 20px;
    font-weight: 800;
    margin-bottom: 28px;
    color: #fff;
  }
  .logo span { color: var(--accent); }

  h2 {
    font-family: 'Syne', sans-serif;
    font-size: 26px;
    font-weight: 700;
    margin-bottom: 6px;
  }

  p.sub {
    color: var(--muted);
    font-size: 14px;
    margin-bottom: 28px;
  }

  .alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 20px;
  }

  .alert-error { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.3); color: var(--error); }
  .alert-success { background: rgba(52,211,153,0.1); border: 1px solid rgba(52,211,153,0.3); color: var(--success); }

  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

  .field { margin-bottom: 16px; }

  label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 7px;
  }

  input, select {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 12px 14px;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    outline: none;
    transition: border 0.2s, box-shadow 0.2s;
    appearance: none;
  }

  input:focus, select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
  }

  select option { background: #1e293b; }

  .btn {
    width: 100%;
    padding: 14px;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-family: 'Syne', sans-serif;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 8px;
    transition: background 0.2s, transform 0.1s;
  }
  .btn:hover { background: #2563eb; transform: translateY(-1px); }

  .form-footer {
    text-align: center;
    margin-top: 18px;
    font-size: 14px;
    color: var(--muted);
  }
  .form-footer a { color: var(--accent); text-decoration: none; font-weight: 500; }

  @media (max-width: 480px) {
    .container { padding: 32px 22px; }
    .grid-2 { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="bg-glow"></div>
<div class="container">
  <div class="logo">Campus<span>Events</span></div>
  <h2>Create Account</h2>
  <p class="sub">Join your college event platform</p>

  <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= $error ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success">✅ <?= $success ?> <a href="login.php" style="color:inherit;font-weight:700;">Login now →</a></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST">
    <div class="grid-2">
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="name" placeholder="Mahesh Kumar" required value="<?= $_POST['name'] ?? '' ?>">
      </div>
      <div class="field">
        <label>Roll Number</label>
        <input type="text" name="roll_number" placeholder="21CS001" value="<?= $_POST['roll_number'] ?? '' ?>">
      </div>
    </div>
    <div class="field">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="you@college.edu" required value="<?= $_POST['email'] ?? '' ?>">
    </div>
    <div class="field">
      <label>Department</label>
      <select name="department">
        <option value="">Select Department</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= $d ?>" <?= (($_POST['department'] ?? '') === $d) ? 'selected' : '' ?>><?= $d ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="grid-2">
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="Min 6 characters" required>
      </div>
      <div class="field">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Repeat password" required>
      </div>
    </div>
    <button type="submit" class="btn">Create Account →</button>
  </form>
  <?php endif; ?>

  <div class="form-footer">
    Already have an account? <a href="login.php">Sign in</a>
  </div>
</div>
</body>
</html>
