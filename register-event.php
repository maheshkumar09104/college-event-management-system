<?php
require_once 'config.php';
if (file_exists('mailer.php')) require_once 'mailer.php';
requireLogin();
if (isAdmin()) { header("Location: admin/dashboard.php"); exit(); }

$user_id   = $_SESSION['user_id'];
$event_id  = (int)($_GET['event_id'] ?? 0);
$type      = ($_GET['type'] ?? 'campus') === 'outer' ? 'outer' : 'campus';

if (!$event_id) { header("Location: dashboard.php"); exit(); }

// Fetch event details
if ($type === 'campus') {
    $event = $conn->query("SELECT * FROM events WHERE id=$event_id AND status!='cancelled'")->fetch_assoc();
    if (!$event) { header("Location: dashboard.php"); exit(); }
    // Check already registered
    $already = $conn->query("SELECT id FROM registrations WHERE user_id=$user_id AND event_id=$event_id AND status='registered'")->fetch_assoc();
    if ($already) { header("Location: event-detail.php?id=$event_id"); exit(); }
} else {
    $event = $conn->query("SELECT * FROM outer_events WHERE id=$event_id AND status!='cancelled'")->fetch_assoc();
    if (!$event) { header("Location: outer-events-student.php"); exit(); }
    // Check already registered
    $already = $conn->query("SELECT id FROM outer_registrations WHERE user_id=$user_id AND outer_event_id=$event_id AND status='registered'")->fetch_assoc();
    if ($already) { header("Location: outer-events-student.php"); exit(); }
}

// Pre-fill from user profile
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = clean($_POST['full_name']);
    $roll_number  = clean($_POST['roll_number']);
    $department   = clean($_POST['department']);
    $phone        = clean($_POST['phone']);
    $email        = clean($_POST['email']);
    $year         = clean($_POST['year_of_study']);
    $team_name    = clean($_POST['team_name']);
    $college_name = clean($_POST['college_name'] ?? '');

    if (!$full_name || !$phone || !$email) {
        $error = "Full name, phone and email are required.";
    } else {
        if ($type === 'campus') {
            // Check capacity
            $cap = $conn->query("SELECT max_participants, (SELECT COUNT(*) FROM registrations WHERE event_id=$event_id AND status='registered') AS registered FROM events WHERE id=$event_id")->fetch_assoc();
            if ($cap['registered'] >= $cap['max_participants']) {
                $error = "Sorry, this event is full!";
            } else {
                $stmt = $conn->prepare("INSERT IGNORE INTO registrations (user_id, event_id, full_name, roll_number, department, phone, email, year_of_study, team_name, event_type) VALUES (?,?,?,?,?,?,?,?,?,'campus')");
                $stmt->bind_param("iisssssss", $user_id, $event_id, $full_name, $roll_number, $department, $phone, $email, $year, $team_name);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Send email
                    $ticket_url = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/ticket.php?event_id='.$event_id;
                    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=".urlencode($ticket_url)."&bgcolor=0d1829&color=f1f5f9&margin=10";
                    if (function_exists('sendRegistrationEmail')) sendRegistrationEmail($email, $full_name, $event, $qr_url);
                    header("Location: ticket.php?event_id=$event_id&registered=1");
                    exit();
                } else {
                    $error = "Registration failed. You may already be registered.";
                }
            }
        } else {
            // Outer event registration
            $stmt = $conn->prepare("INSERT IGNORE INTO outer_registrations (user_id, outer_event_id, full_name, roll_number, department, phone, email, year_of_study, team_name, college_name) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("iissssssss", $user_id, $event_id, $full_name, $roll_number, $department, $phone, $email, $year, $team_name, $college_name);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                header("Location: my-events.php?registered=outer");
                exit();
            } else {
                $error = "Registration failed. You may already be registered.";
            }
        }
    }
}

$departments = ['Computer Science','Information Technology','Electronics & Communication','Mechanical Engineering','Civil Engineering','Electrical Engineering','Business Administration','Other'];
$years = ['1st Year','2nd Year','3rd Year','4th Year','PG 1st Year','PG 2nd Year'];
$color = $type === 'outer' ? '#f59e0b' : ($event['banner_color'] ?? '#3b82f6');
$icon  = $type === 'outer' ? '🌐' : '🎓';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register – <?= htmlspecialchars($event['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0b0f1a; --card: #111827; --card2: #0d1829;
    --border: #1f2d3d; --accent: <?= $color ?>; --text: #f1f5f9;
    --muted: #64748b; --green: #10b981; --red: #ef4444;
  }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 32px 16px 60px; }

  .bg-glow {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background: radial-gradient(ellipse 70% 50% at 50% 0%, <?= $color ?>18, transparent);
  }

  nav {
    position: relative; z-index: 10;
    display: flex; justify-content: space-between; align-items: center;
    max-width: 720px; margin: 0 auto 32px;
  }
  .nav-logo { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; }
  .nav-logo span { color: #3b82f6; }
  .back-btn { font-size: 13px; color: var(--muted); text-decoration: none; padding: 7px 14px; border: 1px solid var(--border); border-radius: 8px; transition: all 0.2s; }
  .back-btn:hover { color: var(--text); border-color: var(--accent); }

  .container { position: relative; z-index: 1; max-width: 720px; margin: 0 auto; }

  /* Event summary card */
  .event-summary {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 16px; overflow: hidden; margin-bottom: 24px;
    animation: slideUp 0.4s ease both;
  }
  .event-summary-bar { height: 6px; background: var(--accent); }
  .event-summary-body { padding: 20px 24px; display: flex; align-items: center; gap: 16px; }
  .event-icon { font-size: 36px; flex-shrink: 0; }
  .event-summary-info h2 { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; margin-bottom: 4px; }
  .event-summary-info p { font-size: 13px; color: var(--muted); }
  .event-type-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: rgba(255,255,255,0.06); color: var(--muted); border: 1px solid var(--border); margin-left: 8px; }

  /* Form card */
  .form-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 16px; padding: 32px;
    animation: slideUp 0.4s 0.1s ease both;
  }
  @keyframes slideUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

  .form-title { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; margin-bottom: 6px; }
  .form-sub { color: var(--muted); font-size: 14px; margin-bottom: 28px; }

  .section-label {
    font-family: 'Syne', sans-serif; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent);
    margin-bottom: 14px; margin-top: 24px; padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
  }
  .section-label:first-of-type { margin-top: 0; }

  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .field { margin-bottom: 14px; }
  label { display: block; font-size: 12px; font-weight: 500; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); margin-bottom: 7px; }
  .required { color: var(--accent); }

  input, select { width: 100%; background: rgba(255,255,255,0.04); border: 1px solid var(--border); color: var(--text); padding: 12px 14px; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; transition: border 0.2s, box-shadow 0.2s; appearance: none; }
  input:focus, select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px <?= $color ?>22; }
  select option { background: #1e293b; }
  input::placeholder { color: var(--muted); }

  .alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; }
  .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--red); }

  .optional-tag { font-size: 10px; color: var(--muted); font-weight: 400; text-transform: none; letter-spacing: 0; margin-left: 4px; }

  .submit-btn {
    width: 100%; padding: 15px;
    background: var(--accent); color: <?= $type === 'outer' ? '#000' : '#fff' ?>;
    border: none; border-radius: 12px;
    font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 800;
    cursor: pointer; margin-top: 24px;
    transition: all 0.2s; letter-spacing: 0.3px;
  }
  .submit-btn:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 8px 24px <?= $color ?>44; }
  .submit-btn:active { transform: translateY(0); }

  .cancel-link { display: block; text-align: center; margin-top: 14px; color: var(--muted); font-size: 14px; text-decoration: none; }
  .cancel-link:hover { color: var(--text); }

  @media (max-width: 560px) {
    .grid-2 { grid-template-columns: 1fr; }
    .form-card { padding: 22px; }
  }
</style>
</head>
<body>
<div class="bg-glow"></div>

<div class="container">
  <nav>
    <div class="nav-logo">Campus<span>Events</span></div>
    <a href="<?= $type === 'outer' ? 'outer-events-student.php' : 'event-detail.php?id='.$event_id ?>" class="back-btn">← Back</a>
  </nav>

  <!-- EVENT SUMMARY -->
  <div class="event-summary">
    <div class="event-summary-bar"></div>
    <div class="event-summary-body">
      <div class="event-icon"><?= $icon ?></div>
      <div class="event-summary-info">
        <h2>
          <?= htmlspecialchars($event['title']) ?>
          <span class="event-type-badge"><?= $type === 'outer' ? 'Outer Event' : 'Campus Event' ?></span>
        </h2>
        <p>
          📅 <?= date('D, d M Y', strtotime($event['event_date'])) ?>
          · 📍 <?= htmlspecialchars($event['venue']) ?>
          <?php if ($type === 'outer'): ?>
          · 🏫 <?= htmlspecialchars($event['organizing_college']) ?>
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>

  <!-- FORM -->
  <div class="form-card">
    <div class="form-title">Complete Your Registration</div>
    <div class="form-sub">Fill in your details below to secure your spot.</div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="section-label">Personal Information</div>
      <div class="grid-2">
        <div class="field">
          <label>Full Name <span class="required">*</span></label>
          <input type="text" name="full_name" placeholder="Mahesh Kumar R" required value="<?= htmlspecialchars($_POST['full_name'] ?? $user['name']) ?>">
        </div>
        <div class="field">
          <label>Roll Number</label>
          <input type="text" name="roll_number" placeholder="2416087" value="<?= htmlspecialchars($_POST['roll_number'] ?? $user['roll_number']) ?>">
        </div>
      </div>
      <div class="grid-2">
        <div class="field">
          <label>Department</label>
          <select name="department">
            <option value="">Select Department</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d ?>" <?= (($_POST['department'] ?? $user['department']) === $d) ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Year of Study</label>
          <select name="year_of_study">
            <option value="">Select Year</option>
            <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= (($_POST['year_of_study'] ?? '') === $y) ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="section-label">Contact Information</div>
      <div class="grid-2">
        <div class="field">
          <label>Email <span class="required">*</span></label>
          <input type="email" name="email" placeholder="you@college.edu" required value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>">
        </div>
        <div class="field">
          <label>Phone Number <span class="required">*</span></label>
          <input type="tel" name="phone" placeholder="9876543210" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
      </div>

      <?php if ($type === 'outer'): ?>
      <div class="section-label">College Details</div>
      <div class="field">
        <label>Your College Name <span class="required">*</span></label>
        <input type="text" name="college_name" placeholder="e.g., SA Engineering College, Chennai" required value="<?= htmlspecialchars($_POST['college_name'] ?? '') ?>">
      </div>
      <?php endif; ?>

      <div class="section-label">Team Details <span class="optional-tag">(optional — fill only for team events)</span></div>
      <div class="field">
        <label>Team Name <span class="optional-tag">optional</span></label>
        <input type="text" name="team_name" placeholder="e.g., Team Alpha (leave empty if individual)" value="<?= htmlspecialchars($_POST['team_name'] ?? '') ?>">
      </div>

      <button type="submit" class="submit-btn">
        <?= $type === 'outer' ? '🌐 Submit Registration →' : '✅ Confirm Registration →' ?>
      </button>
    </form>

    <a href="<?= $type === 'outer' ? 'outer-events-student.php' : 'event-detail.php?id='.$event_id ?>" class="cancel-link">← Cancel and go back</a>
  </div>
</div>
</body>
</html>