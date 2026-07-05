<?php
require_once 'config.php';
requireLogin();
if (isAdmin()) { header("Location: admin/dashboard.php"); exit(); }

$filter = clean($_GET['category'] ?? 'all');

$where = "WHERE status != 'cancelled'";
if ($filter !== 'all') $where .= " AND category = '$filter'";

$events = $conn->query("SELECT * FROM outer_events $where ORDER BY event_date ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Outer Events – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0b0f1a; --card: #111827; --border: #1f2d3d;
    --accent: #f59e0b; --accent2: #3b82f6; --text: #f1f5f9;
    --muted: #64748b; --green: #10b981; --red: #ef4444;
  }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

  nav {
    background: rgba(17,24,39,0.95); backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border); padding: 0 32px; height: 64px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
  }
  .nav-logo { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; }
  .nav-logo span { color: var(--accent2); }
  .nav-right { display: flex; align-items: center; gap: 12px; }
  .nav-user { font-size: 14px; color: var(--muted); }
  .nav-user strong { color: var(--text); }
  .nav-link { font-size: 13px; font-weight: 500; color: var(--muted); text-decoration: none; padding: 7px 14px; border-radius: 8px; border: 1px solid var(--border); transition: all 0.2s; }
  .nav-link:hover { color: var(--text); border-color: var(--accent); }
  .nav-link.active { color: var(--accent); border-color: var(--accent); background: rgba(245,158,11,0.08); }
  .nav-link.logout { color: #f87171; border-color: rgba(239,68,68,0.3); }
  .nav-link.logout:hover { background: rgba(239,68,68,0.1); }

  .main { max-width: 1200px; margin: 0 auto; padding: 40px 24px; }

  .page-header { margin-bottom: 32px; }
  .page-header h1 { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; margin-bottom: 6px; }
  .page-header p { color: var(--muted); font-size: 15px; }
  .page-header h1 span { color: var(--accent); }

  .tabs { display: flex; gap: 8px; margin-bottom: 28px; flex-wrap: wrap; }
  .tab { padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid var(--border); background: transparent; color: var(--muted); text-decoration: none; transition: all 0.2s; }
  .tab.active, .tab:hover { background: var(--accent); color: #000; border-color: var(--accent); }

  .events-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 22px; }

  .event-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 16px; overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    animation: fadeIn 0.4s ease both;
  }
  @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
  .event-card:hover { transform: translateY(-4px); box-shadow: 0 16px 48px rgba(0,0,0,0.3); }

  .event-poster { width: 100%; height: 200px; object-fit: cover; display: block; }
  .event-poster-placeholder {
    width: 100%; height: 200px; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #1a2840, #0d1824); font-size: 64px;
  }

  .event-body { padding: 20px; }
  .event-top { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
  .chip { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
  .chip-cat { background: rgba(245,158,11,0.1); color: var(--accent); border: 1px solid rgba(245,158,11,0.25); }
  .chip-college { background: rgba(59,130,246,0.1); color: var(--accent2); border: 1px solid rgba(59,130,246,0.2); font-size: 10px; }
  .chip-status { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.2); }

  .event-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; margin-bottom: 8px; line-height: 1.3; }
  .event-desc { font-size: 13px; color: var(--muted); line-height: 1.6; margin-bottom: 14px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

  .event-details { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
  .detail { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }

  .contact-box {
    background: rgba(255,255,255,0.03); border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 14px; margin-bottom: 16px;
  }
  .contact-box .contact-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); margin-bottom: 6px; }
  .contact-box .contact-name { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 3px; }
  .contact-box .contact-info { font-size: 12px; color: var(--muted); }

  .btn-register {
    display: block; width: 100%; padding: 12px;
    background: var(--accent); color: #000;
    border-radius: 10px; font-family: 'Syne', sans-serif;
    font-size: 14px; font-weight: 700; text-align: center;
    text-decoration: none; transition: all 0.2s;
  }
  .btn-register:hover { opacity: 0.9; transform: translateY(-1px); }

  .btn-closed { display: block; width: 100%; padding: 12px; background: var(--border); color: var(--muted); border-radius: 10px; font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; text-align: center; cursor: not-allowed; }

  .empty { text-align: center; padding: 80px 20px; color: var(--muted); grid-column: 1/-1; }
  .empty h3 { font-size: 22px; margin-bottom: 8px; color: var(--text); }

  @media (max-width: 640px) { nav { padding: 0 16px; } .main { padding: 24px 14px; } .nav-user { display: none; } }
</style>
</head>
<body>

<nav>
  <div class="nav-logo">Campus<span>Events</span></div>
  <div class="nav-right">
    <div class="nav-user">Hello, <strong><?= htmlspecialchars($_SESSION['name']) ?></strong></div>
    <a href="dashboard.php" class="nav-link">Campus Events</a>
    <a href="outer-events-student.php" class="nav-link active">🌐 Outer Events</a>
    <a href="my-events.php" class="nav-link">My Events</a>
    <a href="logout.php" class="nav-link logout">Logout</a>
  </div>
</nav>

<div class="main">
  <div class="page-header">
    <h1>🌐 <span>Outer</span> Events</h1>
    <p>Events from other colleges — explore and register directly</p>
  </div>

  <div class="tabs">
    <a href="outer-events-student.php" class="tab <?= $filter==='all'?'active':'' ?>">All</a>
    <a href="outer-events-student.php?category=Technical" class="tab <?= $filter==='Technical'?'active':'' ?>">Technical</a>
    <a href="outer-events-student.php?category=Cultural" class="tab <?= $filter==='Cultural'?'active':'' ?>">Cultural</a>
    <a href="outer-events-student.php?category=Sports" class="tab <?= $filter==='Sports'?'active':'' ?>">Sports</a>
    <a href="outer-events-student.php?category=Workshop" class="tab <?= $filter==='Workshop'?'active':'' ?>">Workshop</a>
  </div>

  <div class="events-grid">
    <?php if (empty($events)): ?>
    <div class="empty">
      <h3>No Outer Events Yet</h3>
      <p>Check back soon — your admin will post events from other colleges here.</p>
    </div>
    <?php endif; ?>

    <?php foreach ($events as $i => $e): ?>
    <?php $deadline_passed = $e['registration_deadline'] && date('Y-m-d') > $e['registration_deadline']; ?>
    <div class="event-card" style="animation-delay:<?= $i*0.07 ?>s">

      <?php if ($e['poster_image']): ?>
        <img src="uploads/posters/<?= htmlspecialchars($e['poster_image']) ?>" class="event-poster" alt="Event Poster">
      <?php else: ?>
        <div class="event-poster-placeholder">🌐</div>
      <?php endif; ?>

      <div class="event-body">
        <div class="event-top">
          <span class="chip chip-college">🏫 <?= htmlspecialchars($e['organizing_college']) ?></span>
          <span class="chip chip-cat"><?= $e['category'] ?></span>
          <span class="chip chip-status"><?= ucfirst($e['status']) ?></span>
        </div>

        <div class="event-title"><?= htmlspecialchars($e['title']) ?></div>
        <?php if ($e['description']): ?>
        <div class="event-desc"><?= htmlspecialchars($e['description']) ?></div>
        <?php endif; ?>

        <div class="event-details">
          <div class="detail">📅 <?= date('D, d M Y', strtotime($e['event_date'])) ?></div>
          <?php if ($e['event_time']): ?>
          <div class="detail">⏰ <?= date('h:i A', strtotime($e['event_time'])) ?></div>
          <?php endif; ?>
          <div class="detail">📍 <?= htmlspecialchars($e['venue']) ?></div>
          <?php if ($e['registration_deadline']): ?>
          <div class="detail" style="color:<?= $deadline_passed ? 'var(--red)' : 'var(--muted)' ?>">
            ⏳ Deadline: <?= date('d M Y', strtotime($e['registration_deadline'])) ?>
            <?= $deadline_passed ? ' (Closed)' : '' ?>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($e['contact_name'] || $e['contact_email'] || $e['contact_phone']): ?>
        <div class="contact-box">
          <div class="contact-label">Contact</div>
          <?php if ($e['contact_name']): ?><div class="contact-name">👤 <?= htmlspecialchars($e['contact_name']) ?></div><?php endif; ?>
          <div class="contact-info">
            <?php if ($e['contact_email']): ?>📧 <?= htmlspecialchars($e['contact_email']) ?><?php endif; ?>
            <?php if ($e['contact_phone']): ?> · 📞 <?= htmlspecialchars($e['contact_phone']) ?><?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!$deadline_passed && $e['status'] !== 'completed'): ?>
          <a href="register-event.php?event_id=<?= $e['id'] ?>&type=outer" class="btn-register">🌐 Register Now →</a>
        <?php else: ?>
          <span class="btn-closed">Registration Closed</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

</body>
</html>
