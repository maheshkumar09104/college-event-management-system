<?php
require_once 'config.php';
if (file_exists('mailer.php')) require_once 'mailer.php';
requireLogin();
if (isAdmin()) { header("Location: admin/dashboard.php"); exit(); }

$user_id  = $_SESSION['user_id'];
$event_id = (int)($_GET['id'] ?? 0);
if (!$event_id) { header("Location: dashboard.php"); exit(); }

$event = $conn->query("
    SELECT e.*,
        (SELECT COUNT(*) FROM registrations WHERE event_id=e.id AND status='registered') AS registered_count,
        (SELECT id FROM registrations WHERE user_id=$user_id AND event_id=e.id AND status='registered') AS is_registered
    FROM events e WHERE e.id = $event_id AND e.status != 'cancelled'
")->fetch_assoc();

if (!$event) { header("Location: dashboard.php"); exit(); }

$message = '';
$msg_type = '';

// Handle register / cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'register') {
        $cap = $conn->query("SELECT e.max_participants,
            (SELECT COUNT(*) FROM registrations WHERE event_id=e.id AND status='registered') AS registered
            FROM events e WHERE e.id=$event_id")->fetch_assoc();

        if ($cap['registered'] < $cap['max_participants']) {
            $stmt = $conn->prepare("INSERT IGNORE INTO registrations (user_id, event_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $event_id);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $user = $conn->query("SELECT name, email FROM users WHERE id=$user_id")->fetch_assoc();
                $ticket_url = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/ticket.php?event_id='.$event_id;
                $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=".urlencode($ticket_url)."&bgcolor=0d1829&color=f1f5f9&margin=10";
                if (function_exists('sendRegistrationEmail')) sendRegistrationEmail($user['email'], $user['name'], $event, $qr_url);
                header("Location: ticket.php?event_id=$event_id&registered=1");
                exit();
            }
        } else {
            $message = "Sorry, this event is full!";
            $msg_type = "error";
        }
    } elseif ($_POST['action'] === 'cancel') {
        $stmt = $conn->prepare("UPDATE registrations SET status='cancelled' WHERE user_id=? AND event_id=?");
        $stmt->bind_param("ii", $user_id, $event_id);
        $stmt->execute();
        $user = $conn->query("SELECT name, email FROM users WHERE id=$user_id")->fetch_assoc();
        if (function_exists('sendCancellationEmail')) sendCancellationEmail($user['email'], $user['name'], $event);
        $message = "Registration cancelled successfully.";
        $msg_type = "info";
        // Refresh event data
        $event = $conn->query("SELECT e.*, (SELECT COUNT(*) FROM registrations WHERE event_id=e.id AND status='registered') AS registered_count, (SELECT id FROM registrations WHERE user_id=$user_id AND event_id=e.id AND status='registered') AS is_registered FROM events e WHERE e.id=$event_id")->fetch_assoc();
    }
}

$pct = $event['max_participants'] > 0 ? round(($event['registered_count'] / $event['max_participants']) * 100) : 0;
$full = $event['registered_count'] >= $event['max_participants'];
$past = in_array($event['status'], ['completed', 'cancelled']);
$deadline_passed = $event['registration_deadline'] && date('Y-m-d') > $event['registration_deadline'];
$has_ext_link = !empty($event['registration_link']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($event['title']) ?> – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0b0f1a; --card: #111827; --card2: #0d1829;
    --border: #1f2d3d; --accent: #3b82f6; --text: #f1f5f9;
    --muted: #64748b; --green: #10b981; --red: #ef4444; --yellow: #f59e0b;
  }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

  nav {
    background: rgba(17,24,39,0.95); backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border); padding: 0 32px; height: 64px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
  }
  .nav-logo { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; }
  .nav-logo span { color: var(--accent); }
  .nav-right { display: flex; align-items: center; gap: 10px; }
  .nav-link { font-size: 13px; font-weight: 500; color: var(--muted); text-decoration: none; padding: 7px 14px; border-radius: 8px; border: 1px solid var(--border); transition: all 0.2s; }
  .nav-link:hover { color: var(--text); border-color: var(--accent); }
  .nav-link.logout { color: #f87171; border-color: rgba(239,68,68,0.3); }

  .main { max-width: 900px; margin: 0 auto; padding: 40px 24px 60px; }

  .back-btn {
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--muted); text-decoration: none; font-size: 14px;
    padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px;
    margin-bottom: 28px; transition: all 0.2s;
  }
  .back-btn:hover { color: var(--text); border-color: var(--accent); }

  /* HERO */
  .event-hero {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 24px;
    animation: slideUp 0.5s ease both;
  }
  @keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

  .event-poster-img { width: 100%; max-height: 380px; object-fit: cover; display: block; }
  .event-banner-placeholder {
    width: 100%; height: 220px;
    display: flex; align-items: center; justify-content: center;
    font-size: 80px;
  }
  .event-color-bar { height: 6px; }

  .event-hero-body { padding: 32px; }

  .event-top { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
  .chip { font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 20px; }
  .chip-cat { background: rgba(59,130,246,0.1); color: var(--accent); border: 1px solid rgba(59,130,246,0.2); }
  .chip-upcoming { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.2); }
  .chip-ongoing  { background: rgba(59,130,246,0.1); color: var(--accent); border: 1px solid rgba(59,130,246,0.2); }
  .chip-completed{ background: rgba(100,116,139,0.1); color: var(--muted); border: 1px solid var(--border); }

  .event-title { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; line-height: 1.2; margin-bottom: 14px; }
  .event-desc { color: var(--muted); font-size: 15px; line-height: 1.8; margin-bottom: 28px; }

  /* INFO GRID */
  .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; margin-bottom: 28px; }
  .info-box { background: var(--card2); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; }
  .info-box .info-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); margin-bottom: 6px; }
  .info-box .info-value { font-size: 15px; font-weight: 600; color: var(--text); line-height: 1.3; }

  /* CAPACITY BAR */
  .capacity-section { margin-bottom: 28px; }
  .capacity-header { display: flex; justify-content: space-between; font-size: 13px; color: var(--muted); margin-bottom: 8px; }
  .capacity-header span:last-child { font-weight: 600; color: var(--text); }
  .progress-bar { height: 8px; background: var(--border); border-radius: 8px; overflow: hidden; }
  .progress-fill { height: 100%; border-radius: 8px; background: var(--green); transition: width 0.6s ease; }
  .progress-fill.warn { background: var(--yellow); }
  .progress-fill.full { background: var(--red); }

  /* CONTACT */
  .contact-section { background: var(--card2); border: 1px solid var(--border); border-radius: 14px; padding: 20px 24px; margin-bottom: 28px; }
  .section-label { font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--accent); margin-bottom: 14px; }
  .contact-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
  .contact-item { display: flex; align-items: center; gap: 10px; font-size: 14px; }
  .contact-icon { font-size: 18px; flex-shrink: 0; }
  .contact-info-label { font-size: 11px; color: var(--muted); margin-bottom: 2px; }
  .contact-info-value { font-weight: 600; color: var(--text); }

  /* REGISTER SECTION */
  .register-section { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; }
  .register-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; margin-bottom: 6px; }
  .register-sub { font-size: 14px; color: var(--muted); margin-bottom: 20px; }

  .alert { padding: 13px 18px; border-radius: 10px; font-size: 14px; margin-bottom: 18px; }
  .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--red); }
  .alert-info { background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); color: var(--accent); }
  .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }

  .registered-badge {
    display: flex; align-items: center; gap: 10px;
    background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2);
    border-radius: 12px; padding: 14px 18px; margin-bottom: 16px;
    font-size: 14px; font-weight: 600; color: var(--green);
  }

  .btn {
    display: block; width: 100%; padding: 15px;
    border-radius: 12px; font-family: 'Syne', sans-serif;
    font-size: 16px; font-weight: 700; cursor: pointer;
    border: none; text-align: center; text-decoration: none;
    transition: all 0.2s; letter-spacing: 0.3px;
  }
  .btn-register { background: var(--accent); color: #fff; }
  .btn-register:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(59,130,246,0.3); }
  .btn-ext { background: var(--yellow); color: #000; }
  .btn-ext:hover { opacity: 0.9; transform: translateY(-2px); }
  .btn-cancel { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.3); margin-top: 10px; }
  .btn-cancel:hover { background: rgba(239,68,68,0.2); }
  .btn-disabled { background: var(--border); color: var(--muted); cursor: not-allowed; }

  .view-ticket-link {
    display: block; text-align: center; margin-top: 12px;
    color: var(--accent); font-size: 14px; text-decoration: none; font-weight: 600;
  }
  .view-ticket-link:hover { text-decoration: underline; }

  @media (max-width: 640px) {
    nav { padding: 0 16px; }
    .main { padding: 24px 14px 40px; }
    .event-hero-body { padding: 20px; }
    .event-title { font-size: 24px; }
    .info-grid { grid-template-columns: 1fr 1fr; }
  }
</style>
</head>
<body>

<nav>
  <div class="nav-logo">Campus<span>Events</span></div>
  <div class="nav-right">
    <a href="outer-events-student.php" class="nav-link" style="color:var(--yellow);border-color:rgba(245,158,11,0.3)">🌐 Outer Events</a>
    <a href="my-events.php" class="nav-link">My Events</a>
    <a href="logout.php" class="nav-link logout">Logout</a>
  </div>
</nav>

<div class="main">
  <a href="dashboard.php" class="back-btn">← Back to Events</a>

  <!-- EVENT HERO -->
  <div class="event-hero">
    <?php if (!empty($event['poster_image'])): ?>
      <img src="uploads/posters/<?= htmlspecialchars($event['poster_image']) ?>" class="event-poster-img" alt="Event Poster">
      <div class="event-color-bar" style="background:<?= $event['banner_color'] ?>"></div>
    <?php else: ?>
      <?php $icons = ['Technical'=>'💻','Cultural'=>'🎭','Sports'=>'🏆','Academic'=>'📚','Workshop'=>'🔧','Seminar'=>'🎤','Other'=>'🎉']; ?>
      <div class="event-banner-placeholder" style="background:linear-gradient(135deg,<?= $event['banner_color'] ?>22,<?= $event['banner_color'] ?>08)">
        <?= $icons[$event['category']] ?? '🎓' ?>
      </div>
      <div class="event-color-bar" style="background:<?= $event['banner_color'] ?>"></div>
    <?php endif; ?>

    <div class="event-hero-body">
      <div class="event-top">
        <span class="chip chip-cat"><?= $event['category'] ?></span>
        <span class="chip chip-<?= $event['status'] ?>"><?= ucfirst($event['status']) ?></span>
      </div>
      <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
      <?php if ($event['description']): ?>
      <div class="event-desc"><?= nl2br(htmlspecialchars($event['description'])) ?></div>
      <?php endif; ?>

      <!-- INFO GRID -->
      <div class="info-grid">
        <div class="info-box">
          <div class="info-label">📅 Date</div>
          <div class="info-value"><?= date('D, d M Y', strtotime($event['event_date'])) ?></div>
        </div>
        <div class="info-box">
          <div class="info-label">⏰ Time</div>
          <div class="info-value"><?= date('h:i A', strtotime($event['event_time'])) ?></div>
        </div>
        <div class="info-box">
          <div class="info-label">📍 Venue</div>
          <div class="info-value"><?= htmlspecialchars($event['venue']) ?></div>
        </div>
        <div class="info-box">
          <div class="info-label">👥 Capacity</div>
          <div class="info-value"><?= $event['registered_count'] ?> / <?= $event['max_participants'] ?></div>
        </div>
        <?php if ($event['registration_deadline']): ?>
        <div class="info-box">
          <div class="info-label">⏳ Deadline</div>
          <div class="info-value" style="color:<?= $deadline_passed ? 'var(--red)' : 'var(--text)' ?>">
            <?= date('d M Y', strtotime($event['registration_deadline'])) ?>
            <?= $deadline_passed ? ' (Passed)' : '' ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- CAPACITY BAR -->
      <?php if (!$has_ext_link): ?>
      <div class="capacity-section">
        <div class="capacity-header">
          <span>Registration capacity</span>
          <span><?= $pct ?>% filled</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill <?= $pct>=100?'full':($pct>=75?'warn':'') ?>" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- CONTACT -->
  <?php if ($event['contact_name'] || $event['contact_email'] || $event['contact_phone']): ?>
  <div class="contact-section" style="animation:slideUp 0.5s 0.1s ease both">
    <div class="section-label">Contact Details</div>
    <div class="contact-grid">
      <?php if ($event['contact_name']): ?>
      <div class="contact-item">
        <span class="contact-icon">👤</span>
        <div>
          <div class="contact-info-label">Contact Person</div>
          <div class="contact-info-value"><?= htmlspecialchars($event['contact_name']) ?></div>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($event['contact_email']): ?>
      <div class="contact-item">
        <span class="contact-icon">📧</span>
        <div>
          <div class="contact-info-label">Email</div>
          <div class="contact-info-value"><?= htmlspecialchars($event['contact_email']) ?></div>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($event['contact_phone']): ?>
      <div class="contact-item">
        <span class="contact-icon">📞</span>
        <div>
          <div class="contact-info-label">Phone</div>
          <div class="contact-info-value"><?= htmlspecialchars($event['contact_phone']) ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- REGISTER SECTION -->
  <div class="register-section" style="animation:slideUp 0.5s 0.2s ease both">
    <div class="register-title">Registration</div>
    <div class="register-sub">
      <?php if ($event['is_registered']): ?>
        You are registered for this event.
      <?php elseif ($past): ?>
        This event has ended.
      <?php elseif ($deadline_passed): ?>
        Registration deadline has passed.
      <?php elseif ($full): ?>
        This event is full.
      <?php else: ?>
        Secure your spot for this event!
      <?php endif; ?>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($event['is_registered'] && !$has_ext_link): ?>
      <div class="registered-badge">✅ You are registered for this event!</div>
      <a href="ticket.php?event_id=<?= $event_id ?>" class="btn btn-register">🎟 View My Ticket</a>
      <?php if (!$past): ?>
      <form method="POST">
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="btn btn-cancel" onclick="return confirm('Cancel your registration for this event?')">Cancel Registration</button>
      </form>
      <?php endif; ?>

    <?php elseif ($has_ext_link): ?>
      <?php if (!$past && !$deadline_passed): ?>
        <a href="<?= htmlspecialchars($event['registration_link']) ?>" target="_blank" class="btn btn-ext">🔗 Register via External Link →</a>
      <?php else: ?>
        <div class="btn btn-disabled">Registration Closed</div>
      <?php endif; ?>

    <?php elseif ($past || $deadline_passed): ?>
      <div class="btn btn-disabled">Registration Closed</div>

    <?php elseif ($full): ?>
      <div class="btn btn-disabled">Event Full — No Spots Available</div>

    <?php else: ?>
      <a href="register-event.php?event_id=<?= $event_id ?>&type=campus" class="btn btn-register">✅ Register for this Event →</a>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
