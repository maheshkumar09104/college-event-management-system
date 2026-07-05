<?php
require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (isLoggedIn()) {
    header("Location: " . (isAdmin() ? "admin/dashboard.php" : "dashboard.php"));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            header("Location: " . ($user['role'] === 'admin' ? "admin/dashboard.php" : "dashboard.php"));
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "No account found with that email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #0b0f1a;
    --card: #111827;
    --border: #1f2d3d;
    --accent: #3b82f6;
    --accent2: #06b6d4;
    --text: #f1f5f9;
    --muted: #64748b;
    --error: #f87171;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .bg-glow {
    position: fixed; inset: 0; z-index: 0;
    background: radial-gradient(ellipse 80% 60% at 20% 20%, rgba(59,130,246,0.15), transparent),
                radial-gradient(ellipse 60% 80% at 80% 80%, rgba(6,182,212,0.1), transparent);
  }

  .container {
    position: relative; z-index: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    width: min(900px, 95vw);
    min-height: 520px;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 40px 80px rgba(0,0,0,0.5);
    border: 1px solid var(--border);
    animation: slideUp 0.6s ease both;
  }

  @keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .hero {
    background: linear-gradient(135deg, #1e3a5f 0%, #0f2342 50%, #0a1628 100%);
    padding: 50px 40px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
  }

  .hero::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%233b82f6' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  }

  .hero-logo {
    font-family: 'Syne', sans-serif;
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -1px;
  }

  .hero-logo span { color: var(--accent); }

  .hero-content h1 {
    font-family: 'Syne', sans-serif;
    font-size: 38px;
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 16px;
    color: #fff;
  }

  .hero-content p {
    color: rgba(255,255,255,0.55);
    font-size: 15px;
    line-height: 1.6;
  }

  .hero-badges {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 30px;
  }

  .badge {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: rgba(255,255,255,0.6);
  }

  .badge-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: var(--accent);
    flex-shrink: 0;
  }

  .form-side {
    background: var(--card);
    padding: 50px 44px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .form-side h2 {
    font-family: 'Syne', sans-serif;
    font-size: 26px;
    font-weight: 700;
    margin-bottom: 6px;
  }

  .form-side p.sub {
    color: var(--muted);
    font-size: 14px;
    margin-bottom: 30px;
  }

  .error-box {
    background: rgba(248,113,113,0.1);
    border: 1px solid rgba(248,113,113,0.3);
    color: var(--error);
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 20px;
  }

  .field {
    margin-bottom: 18px;
  }

  label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 8px;
  }

  input[type="email"], input[type="password"] {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 13px 16px;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    outline: none;
    transition: border 0.2s, box-shadow 0.2s;
  }

  input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
  }

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
    letter-spacing: 0.3px;
  }

  .btn:hover { background: #2563eb; transform: translateY(-1px); }
  .btn:active { transform: translateY(0); }

  .form-footer {
    text-align: center;
    margin-top: 20px;
    font-size: 14px;
    color: var(--muted);
  }

  .form-footer a {
    color: var(--accent);
    text-decoration: none;
    font-weight: 500;
  }

  .demo-info {
    background: rgba(59,130,246,0.08);
    border: 1px solid rgba(59,130,246,0.2);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 12px;
    color: var(--accent2);
    margin-top: 16px;
    line-height: 1.7;
  }

  @media (max-width: 640px) {
    .container { grid-template-columns: 1fr; }
    .hero { display: none; }
    .form-side { padding: 40px 28px; }
  }
</style>
</head>
<body>
<div class="bg-glow"></div>
<div class="container">
  <div class="hero">
    <div class="hero-logo">Campus<span>Events</span></div>
    <div class="hero-content">
      <h1>Your Campus, Your Events</h1>
      <p>Discover, register, and manage college events all in one place.</p>
      <div class="hero-badges">
        <div class="badge"><div class="badge-dot"></div> Browse upcoming events</div>
        <div class="badge"><div class="badge-dot"></div> One-click registration</div>
        <div class="badge"><div class="badge-dot"></div> Admin event management</div>
      </div>
    </div>
    <div style="color:rgba(255,255,255,0.2); font-size:12px;">© 2025 CampusEvents</div>
  </div>
  <div class="form-side">
    <h2>Welcome back</h2>
    <p class="sub">Sign in to your account to continue</p>

    <?php if ($error): ?>
      <div class="error-box">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="you@college.edu" required value="<?= isset($_POST['email']) ? clean($_POST['email']) : '' ?>">
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn">Sign In →</button>
    </form>

    <div class="form-footer">
      Don't have an account? <a href="register.php">Register here</a>
    </div>

    <div class="demo-info">
      🔑 <strong>Demo Admin:</strong> admin@college.edu / admin123<br>
      Students can register using the link above.
    </div>
  </div>
</div>
</body>
</html>
