<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$outer_events = $conn->query("SELECT * FROM outer_events ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$total = count($outer_events);
$upcoming = count(array_filter($outer_events, fn($e) => $e['status'] === 'upcoming'));
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
    --bg: #060a14; --sidebar: #0d1424; --card: #111827;
    --border: #1a2840; --accent: #3b82f6; --accent2: #f59e0b;
    --text: #f1f5f9; --muted: #64748b; --green: #10b981; --red: #ef4444;
  }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

  .sidebar {
    width: 240px; background: var(--sidebar); border-right: 1px solid var(--border);
    display: flex; flex-direction: column; padding: 28px 0; position: fixed; height: 100vh; overflow-y: auto;
  }
  .sidebar-logo { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; padding: 0 24px 20px; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
  .sidebar-logo span { color: var(--accent); }
  .sidebar-section { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); padding: 10px 24px 6px; }
  .sidebar-link { display: flex; align-items: center; gap: 10px; padding: 11px 24px; font-size: 14px; font-weight: 500; color: var(--muted); text-decoration: none; transition: all 0.2s; }
  .sidebar-link:hover, .sidebar-link.active { background: rgba(59,130,246,0.1); color: var(--text); border-right: 3px solid var(--accent); }
  .sidebar-link .icon { font-size: 16px; }
  .sidebar-divider { height: 1px; background: var(--border); margin: 12px 0; }

  .main { margin-left: 240px; flex: 1; padding: 36px 32px; }
  .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
  .topbar h1 { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; }

  .btn-create {
    display: flex; align-items: center; gap: 8px;
    background: var(--accent2); color: #000; padding: 11px 22px;
    border-radius: 10px; font-family: 'Syne', sans-serif; font-size: 14px;
    font-weight: 700; text-decoration: none; transition: all 0.2s;
  }
  .btn-create:hover { opacity: 0.9; transform: translateY(-1px); }

  .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
  .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px; }
  .stat-card .icon-wrap { font-size: 26px; margin-bottom: 10px; }
  .stat-card .val { font-family: 'Syne', sans-serif; font-size: 30px; font-weight: 800; color: var(--accent2); }
  .stat-card .lbl { font-size: 13px; color: var(--muted); margin-top: 4px; }

  .events-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }

  .event-card {
    background: var(--card); border: 1px solid var(--border); border-radius: 16px;
    overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;
  }
  .event-card:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(0,0,0,0.3); }

  .event-poster {
    width: 100%; height: 180px; object-fit: cover; display: block;
    background: linear-gradient(135deg, #1e3a5f, #0f2342);
  }
  .event-poster-placeholder {
    width: 100%; height: 180px; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #1a2840, #0d1824); font-size: 48px;
  }

  .event-body { padding: 20px; }
  .event-top { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
  .chip { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
  .chip-cat { background: rgba(245,158,11,0.1); color: var(--accent2); border: 1px solid rgba(245,158,11,0.25); }
  .chip-status-upcoming { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.2); }
  .chip-status-completed { background: rgba(100,116,139,0.1); color: var(--muted); border: 1px solid var(--border); }
  .chip-status-cancelled { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.2); }
  .chip-college { background: rgba(59,130,246,0.1); color: var(--accent); border: 1px solid rgba(59,130,246,0.2); }

  .event-title { font-family: 'Syne', sans-serif; font-size: 17px; font-weight: 700; margin-bottom: 12px; line-height: 1.3; }

  .event-details { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
  .detail { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }

  .event-actions { display: flex; gap: 8px; }
  .action-btn { font-size: 12px; padding: 7px 14px; border-radius: 8px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.15s; }
  .action-edit { background: rgba(59,130,246,0.15); color: var(--accent); }
  .action-edit:hover { background: rgba(59,130,246,0.3); }
  .action-delete { background: rgba(239,68,68,0.1); color: var(--red); }
  .action-delete:hover { background: rgba(239,68,68,0.2); }
  .action-link { background: rgba(245,158,11,0.1); color: var(--accent2); }
  .action-link:hover { background: rgba(245,158,11,0.2); }

  .empty { text-align: center; padding: 60px; color: var(--muted); grid-column: 1/-1; }
  .empty h3 { font-size: 20px; color: var(--text); margin-bottom: 8px; }

  @media (max-width: 1024px) { .sidebar { display: none; } .main { margin-left: 0; } }
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo">Campus<span>Events</span></div>
  <div class="sidebar-section">Campus Events</div>
  <a href="dashboard.php" class="sidebar-link"><span class="icon">🏠</span> Dashboard</a>
  <a href="create-event.php" class="sidebar-link"><span class="icon">➕</span> Create Event</a>
  <a href="students.php" class="sidebar-link"><span class="icon">👥</span> Students</a>
  <div class="sidebar-divider"></div>
  <div class="sidebar-section">Outer Events</div>
  <a href="outer-events.php" class="sidebar-link active"><span class="icon">🌐</span> Outer Events</a>
  <a href="create-outer-event.php" class="sidebar-link"><span class="icon">➕</span> Post Outer Event</a>
  <div style="margin-top:auto;padding:20px 24px 0;border-top:1px solid var(--border)">
    <div style="font-size:13px;color:var(--muted);margin-bottom:10px">
      <strong style="color:var(--text);display:block"><?= htmlspecialchars($_SESSION['name']) ?></strong>Administrator
    </div>
    <a href="../logout.php" class="sidebar-link" style="padding:10px 0"><span class="icon">🚪</span> Logout</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <h1>🌐 Outer Events</h1>
    <a href="create-outer-event.php" class="btn-create">➕ Post Outer Event</a>
  </div>

  <div class="stats">
    <div class="stat-card">
      <div class="icon-wrap">🌐</div>
      <div class="val"><?= $total ?></div>
      <div class="lbl">Total Outer Events</div>
    </div>
    <div class="stat-card">
      <div class="icon-wrap">📅</div>
      <div class="val"><?= $upcoming ?></div>
      <div class="lbl">Upcoming</div>
    </div>
    <div class="stat-card">
      <div class="icon-wrap">🏫</div>
      <div class="val"><?= count(array_unique(array_column($outer_events, 'organizing_college'))) ?></div>
      <div class="lbl">Partner Colleges</div>
    </div>
  </div>

  <div class="events-grid">
    <?php if (empty($outer_events)): ?>
    <div class="empty">
      <h3>No Outer Events Yet</h3>
      <p>Post events from other colleges for students to discover.</p>
    </div>
    <?php endif; ?>

    <?php foreach ($outer_events as $e): ?>
    <div class="event-card">
      <?php if ($e['poster_image']): ?>
        <img src="../uploads/posters/<?= htmlspecialchars($e['poster_image']) ?>" class="event-poster" alt="Poster">
      <?php else: ?>
        <div class="event-poster-placeholder">🌐</div>
      <?php endif; ?>

      <div class="event-body">
        <div class="event-top">
          <span class="chip chip-college"><?= htmlspecialchars($e['organizing_college']) ?></span>
          <span class="chip chip-cat"><?= $e['category'] ?></span>
          <span class="chip chip-status-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span>
        </div>

        <div class="event-title"><?= htmlspecialchars($e['title']) ?></div>

        <div class="event-details">
          <div class="detail">📅 <?= date('d M Y', strtotime($e['event_date'])) ?></div>
          <div class="detail">📍 <?= htmlspecialchars($e['venue']) ?></div>
          <?php if ($e['registration_deadline']): ?>
          <div class="detail">⏳ Deadline: <?= date('d M Y', strtotime($e['registration_deadline'])) ?></div>
          <?php endif; ?>
          <?php if ($e['contact_name']): ?>
          <div class="detail">👤 <?= htmlspecialchars($e['contact_name']) ?> · <?= htmlspecialchars($e['contact_phone']) ?></div>
          <?php endif; ?>
        </div>

        <div class="event-actions">
          <a href="edit-outer-event.php?id=<?= $e['id'] ?>" class="action-btn action-edit">Edit</a>
          <?php if ($e['registration_link']): ?>
          <a href="<?= htmlspecialchars($e['registration_link']) ?>" target="_blank" class="action-btn action-link">🔗 Reg. Link</a>
          <?php endif; ?>
          <a href="delete-outer-event.php?id=<?= $e['id'] ?>" class="action-btn action-delete"
             onclick="return confirm('Delete this event?')">Delete</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>