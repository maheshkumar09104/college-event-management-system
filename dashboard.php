<?php
require_once 'config.php';
if (file_exists('mailer.php')) require_once 'mailer.php';
requireLogin();

if (isAdmin()) {
    header("Location: admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $event_id = (int)$_POST['event_id'];

    if ($_POST['action'] === 'register') {
        $cap = $conn->query("SELECT e.max_participants,
            (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') AS registered
            FROM events e WHERE e.id = $event_id")->fetch_assoc();

        if ($cap['registered'] < $cap['max_participants']) {
            $stmt = $conn->prepare("INSERT IGNORE INTO registrations (user_id, event_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $event_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $event = $conn->query("SELECT * FROM events WHERE id = $event_id")->fetch_assoc();
                $user  = $conn->query("SELECT name, email FROM users WHERE id = $user_id")->fetch_assoc();
                $ticket_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/ticket.php?event_id=' . $event_id;
                $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($ticket_url) . "&bgcolor=0d1829&color=f1f5f9&margin=10";
                if (function_exists('sendRegistrationEmail')) sendRegistrationEmail($user['email'], $user['name'], $event, $qr_url);
                header("Location: ticket.php?event_id=$event_id&registered=1");
                exit();
            }
        }
    } elseif ($_POST['action'] === 'cancel') {
        $stmt = $conn->prepare("UPDATE registrations SET status='cancelled' WHERE user_id=? AND event_id=?");
        $stmt->bind_param("ii", $user_id, $event_id);
        $stmt->execute();
        $event = $conn->query("SELECT * FROM events WHERE id = $event_id")->fetch_assoc();
        $user  = $conn->query("SELECT name, email FROM users WHERE id = $user_id")->fetch_assoc();
        if (function_exists('sendCancellationEmail')) sendCancellationEmail($user['email'], $user['name'], $event);
    }
    header("Location: dashboard.php");
    exit();
}

$events = $conn->query("
    SELECT e.*,
        (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') AS registered_count,
        (SELECT id FROM registrations WHERE user_id = $user_id AND event_id = e.id AND status='registered') AS is_registered
    FROM events e
    WHERE e.status != 'cancelled'
    ORDER BY e.event_date ASC
")->fetch_all(MYSQLI_ASSOC);

$my_count = $conn->query("SELECT COUNT(*) AS c FROM registrations WHERE user_id=$user_id AND status='registered'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Campus Events – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0b0f1a; --card: #111827; --border: #1f2d3d;
    --accent: #3b82f6; --text: #f1f5f9; --muted: #64748b;
    --green: #10b981; --red: #ef4444; --yellow: #f59e0b;
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
  .nav-user { font-size: 14px; color: var(--muted); margin-right: 4px; }
  .nav-user strong { color: var(--text); }
  .nav-link { font-size: 13px; font-weight: 500; color: var(--muted); text-decoration: none; padding: 7px 14px; border-radius: 8px; border: 1px solid var(--border); transition: all 0.2s; }
  .nav-link:hover { color: var(--text); border-color: var(--accent); }
  .nav-link.outer { color: var(--yellow); border-color: rgba(245,158,11,0.3); }
  .nav-link.outer:hover { background: rgba(245,158,11,0.08); border-color: var(--yellow); }
  .nav-link.logout { color: #f87171; border-color: rgba(239,68,68,0.3); }
  .nav-link.logout:hover { background: rgba(239,68,68,0.1); }

  .main { max-width: 1200px; margin: 0 auto; padding: 40px 24px; }

  .page-header { margin-bottom: 32px; }
  .page-header h1 { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; margin-bottom: 6px; }
  .page-header p { color: var(--muted); font-size: 15px; }

  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 32px; }
  .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px; }
  .stat-card .label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
  .stat-card .value { font-family: 'Syne', sans-serif; font-size: 30px; font-weight: 800; color: var(--accent); }

  .tabs { display: flex; gap: 8px; margin-bottom: 28px; flex-wrap: wrap; }
  .tab { padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid var(--border); background: transparent; color: var(--muted); transition: all 0.2s; }
  .tab.active, .tab:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

  .events-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 22px; }

  .event-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 16px; overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    animation: fadeIn 0.4s ease both;
  }
  @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
  .event-card:hover { transform: translateY(-4px); box-shadow: 0 16px 48px rgba(0,0,0,0.3); }

  /* Poster or color banner */
  .event-poster { width: 100%; height: 200px; object-fit: cover; display: block; }
  .event-banner { height: 8px; }
  .event-banner-placeholder { width: 100%; height: 200px; display: flex; align-items: center; justify-content: center; font-size: 56px; }

  .event-body { padding: 20px; }

  .event-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
  .chip { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
  .chip-cat { background: rgba(59,130,246,0.1); color: var(--accent); border: 1px solid rgba(59,130,246,0.2); }
  .chip-upcoming { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.2); }
  .chip-ongoing { background: rgba(59,130,246,0.1); color: var(--accent); border: 1px solid rgba(59,130,246,0.2); }
  .chip-completed { background: rgba(100,116,139,0.1); color: var(--muted); border: 1px solid var(--border); }

  .event-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; margin-bottom: 8px; line-height: 1.3; }
  .event-desc { font-size: 13px; color: var(--muted); line-height: 1.6; margin-bottom: 14px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

  .event-details { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
  .detail { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }

  /* Contact box */
  .contact-box { background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; }
  .contact-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); margin-bottom: 5px; }
  .contact-name { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
  .contact-info { font-size: 12px; color: var(--muted); }

  /* Capacity */
  .progress-label { display: flex; justify-content: space-between; font-size: 11px; color: var(--muted); margin-bottom: 5px; }
  .progress-bar { height: 4px; background: var(--border); border-radius: 4px; margin-bottom: 14px; overflow: hidden; }
  .progress-fill { height: 100%; border-radius: 4px; background: var(--green); }
  .progress-fill.warn { background: var(--yellow); }
  .progress-fill.full { background: var(--red); }

  /* Buttons */
  .btn { width: 100%; padding: 12px; border-radius: 10px; font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; text-align: center; text-decoration: none; display: block; }
  .btn-register { background: var(--accent); color: #fff; }
  .btn-register:hover { background: #2563eb; }
  .btn-ext { background: var(--yellow); color: #000; }
  .btn-ext:hover { opacity: 0.9; }
  .btn-cancel { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }
  .btn-cancel:hover { background: rgba(239,68,68,0.2); }
  .btn-full { background: rgba(100,116,139,0.15); color: var(--muted); cursor: not-allowed; }
  .btn-past { background: var(--border); color: var(--muted); cursor: not-allowed; }

  .registered-badge { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--green); font-weight: 600; margin-bottom: 10px; }

  .empty { text-align: center; padding: 60px 20px; color: var(--muted); grid-column: 1/-1; }
  .empty h3 { font-size: 20px; margin-bottom: 8px; color: var(--text); }

  @media (max-width: 640px) { nav { padding: 0 16px; } .main { padding: 24px 14px; } .nav-user { display: none; } }
</style>
</head>
<body>
<nav>
  <div class="nav-logo">Campus<span>Events</span></div>
  <div class="nav-right">
    <div class="nav-user">Hello, <strong><?= htmlspecialchars($_SESSION['name']) ?></strong></div>
    <a href="outer-events-student.php" class="nav-link outer">🌐 Outer Events</a>
    <a href="my-events.php" class="nav-link">My Events</a>
    <a href="logout.php" class="nav-link logout">Logout</a>
  </div>
</nav>

<div class="main">
  <div class="page-header">
    <h1>Upcoming Events 🎓</h1>
    <p>Browse and register for events happening at your campus</p>
  </div>

  <div class="stats">
    <div class="stat-card"><div class="label">Total Events</div><div class="value"><?= count($events) ?></div></div>
    <div class="stat-card"><div class="label">My Registrations</div><div class="value"><?= $my_count ?></div></div>
    <div class="stat-card"><div class="label">Upcoming</div><div class="value"><?= count(array_filter($events, fn($e) => $e['status'] === 'upcoming')) ?></div></div>
  </div>

  <div class="tabs">
    <button class="tab active" onclick="filterEvents('all', this)">All Events</button>
    <button class="tab" onclick="filterEvents('Technical', this)">Technical</button>
    <button class="tab" onclick="filterEvents('Cultural', this)">Cultural</button>
    <button class="tab" onclick="filterEvents('Sports', this)">Sports</button>
    <button class="tab" onclick="filterEvents('my', this)">My Events</button>
  </div>

  <div class="events-grid" id="eventsGrid">
    <?php foreach ($events as $i => $e): ?>
    <?php
      $pct = $e['max_participants'] > 0 ? round(($e['registered_count'] / $e['max_participants']) * 100) : 0;
      $full = $e['registered_count'] >= $e['max_participants'];
      $past = in_array($e['status'], ['completed', 'cancelled']);
      $deadline_passed = $e['registration_deadline'] && date('Y-m-d') > $e['registration_deadline'];
      $has_poster = !empty($e['poster_image']);
      $has_ext_link = !empty($e['registration_link']);
    ?>
    <div class="event-card" style="cursor:pointer" onclick="window.location='event-detail.php?id=<?= $e['id'] ?>'" data-category="<?= $e['category'] ?>" data-registered="<?= $e['is_registered'] ? '1' : '0' ?>" style="animation-delay:<?= $i*0.07 ?>s">

      <?php if ($has_poster): ?>
        <img src="uploads/posters/<?= htmlspecialchars($e['poster_image']) ?>" class="event-poster" alt="Event Poster">
      <?php else: ?>
        <div class="event-banner-placeholder" style="background:linear-gradient(135deg,<?= $e['banner_color'] ?>33,<?= $e['banner_color'] ?>11)">
          <?php $icons = ['Technical'=>'💻','Cultural'=>'🎭','Sports'=>'🏆','Academic'=>'📚','Workshop'=>'🔧','Seminar'=>'🎤','Other'=>'🎉']; ?>
          <?= $icons[$e['category']] ?? '🎓' ?>
        </div>
        <div class="event-banner" style="background:<?= $e['banner_color'] ?>"></div>
      <?php endif; ?>

      <div class="event-body">
        <div class="event-meta">
          <span class="chip chip-cat"><?= $e['category'] ?></span>
          <span class="chip chip-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span>
        </div>

      <a href="event-detail.php?id=<?= $e['id'] ?>" style="text-decoration:none;color:inherit">
<div class="event-title" style="transition:color 0.2s" onmouseover="this.style.color='#3b82f6'" onmouseout="this.style.color='inherit'"><?= htmlspecialchars($e['title']) ?></div>
</a>
        <?php if ($e['description']): ?>
        <div class="event-desc"><?= htmlspecialchars($e['description']) ?></div>
        <?php endif; ?>

        <div class="event-details">
          <div class="detail">📅 <?= date('D, d M Y', strtotime($e['event_date'])) ?></div>
          <div class="detail">⏰ <?= date('h:i A', strtotime($e['event_time'])) ?></div>
          <div class="detail">📍 <?= htmlspecialchars($e['venue']) ?></div>
          <?php if ($e['registration_deadline']): ?>
          <div class="detail">⏳ Deadline: <?= date('d M Y', strtotime($e['registration_deadline'])) ?></div>
          <?php endif; ?>
        </div>

        <?php if ($e['contact_name'] || $e['contact_phone'] || $e['contact_email']): ?>
        <div class="contact-box">
          <div class="contact-label">Contact</div>
          <?php if ($e['contact_name']): ?><div class="contact-name">👤 <?= htmlspecialchars($e['contact_name']) ?></div><?php endif; ?>
          <div class="contact-info">
            <?php if ($e['contact_email']): ?>📧 <?= htmlspecialchars($e['contact_email']) ?><?php endif; ?>
            <?php if ($e['contact_phone']): ?> · 📞 <?= htmlspecialchars($e['contact_phone']) ?><?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!$has_ext_link): ?>
        <div class="progress-label">
          <span><?= $e['registered_count'] ?> / <?= $e['max_participants'] ?> registered</span>
          <span><?= $pct ?>%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill <?= $pct>=100?'full':($pct>=75?'warn':'') ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <?php endif; ?>

        <?php if ($e['is_registered'] && !$has_ext_link): ?>
          <div class="registered-badge">✅ You are registered</div>
          <?php if (!$past): ?>
          <form method="POST">
            <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-cancel" onclick="return confirm('Cancel your registration?')">Cancel Registration</button>
          </form>
          <?php endif; ?>
        <?php elseif ($has_ext_link): ?>
          <?php if (!$past && !$deadline_passed): ?>
            <a href="<?= htmlspecialchars($e['registration_link']) ?>" target="_blank" class="btn btn-ext">🔗 Register Now →</a>
          <?php else: ?>
            <span class="btn btn-past">Registration Closed</span>
          <?php endif; ?>
        <?php elseif ($past || $deadline_passed): ?>
          <button class="btn btn-past" disabled>Registration Closed</button>
        <?php elseif ($full): ?>
          <button class="btn btn-full" disabled>Event Full</button>
        <?php else: ?>
          <a href="event-detail.php?id=<?= $e['id'] ?>" class="btn btn-register">Register Now →</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($events)): ?>
    <div class="empty"><h3>No Events Yet</h3><p>Check back soon for upcoming college events!</p></div>
    <?php endif; ?>
  </div>
</div>

<script>
function filterEvents(category, btn) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.event-card').forEach(card => {
    if (category === 'all') card.style.display = '';
    else if (category === 'my') card.style.display = card.dataset.registered === '1' ? '' : 'none';
    else card.style.display = card.dataset.category === category ? '' : 'none';
  });
}
</script>
</body>
</html>