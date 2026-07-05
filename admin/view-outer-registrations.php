<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: outer-events.php"); exit(); }

$event = $conn->query("SELECT * FROM outer_events WHERE id=$id")->fetch_assoc();
if (!$event) { header("Location: outer-events.php"); exit(); }

$registrations = $conn->query("
    SELECT r.*, u.email AS user_email
    FROM outer_registrations r
    JOIN users u ON r.user_id = u.id
    WHERE r.outer_event_id = $id
    ORDER BY r.registered_at ASC
")->fetch_all(MYSQLI_ASSOC);

$total = count(array_filter($registrations, fn($r) => $r['status'] === 'registered'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Outer Event Registrations – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --bg: #060a14; --sidebar: #0d1424; --card: #111827; --border: #1a2840; --accent: #3b82f6; --accent2: #f59e0b; --text: #f1f5f9; --muted: #64748b; --green: #10b981; --red: #ef4444; }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
  .sidebar { width: 240px; background: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 28px 0; position: fixed; height: 100vh; }
  .sidebar-logo { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; padding: 0 24px 20px; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
  .sidebar-logo span { color: var(--accent); }
  .sidebar-section { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); padding: 10px 24px 6px; }
  .sidebar-link { display: flex; align-items: center; gap: 10px; padding: 11px 24px; font-size: 14px; color: var(--muted); text-decoration: none; transition: all 0.2s; }
  .sidebar-link:hover, .sidebar-link.active { background: rgba(59,130,246,0.1); color: var(--text); border-right: 3px solid var(--accent); }
  .sidebar-divider { height: 1px; background: var(--border); margin: 12px 0; }
  .main { margin-left: 240px; flex: 1; padding: 36px 32px; }
  .topbar { display: flex; align-items: center; gap: 16px; margin-bottom: 28px; }
  .back-btn { color: var(--muted); text-decoration: none; font-size: 13px; padding: 8px 14px; border: 1px solid var(--border); border-radius: 8px; }
  h1 { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; }
  .event-info-card { background: var(--card); border: 1px solid var(--border); border-left: 4px solid var(--accent2); border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; }
  .event-info-card h2 { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; margin-bottom: 6px; }
  .event-info-card p { font-size: 13px; color: var(--muted); }
  .stats { display: flex; gap: 14px; margin-bottom: 24px; }
  .stat { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; }
  .stat .val { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; color: var(--accent2); }
  .stat .lbl { font-size: 12px; color: var(--muted); margin-top: 2px; }
  .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
  table { width: 100%; border-collapse: collapse; }
  thead th { padding: 12px 16px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); background: rgba(0,0,0,0.2); border-bottom: 1px solid var(--border); }
  tbody tr { border-bottom: 1px solid var(--border); }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(245,158,11,0.03); }
  td { padding: 12px 16px; font-size: 13px; }
  .badge { font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 600; }
  .badge-active { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.2); }
  .badge-cancelled { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.2); }
  .empty { text-align: center; padding: 40px; color: var(--muted); }
  @media (max-width: 768px) { .sidebar { display: none; } .main { margin-left: 0; } }
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo">Campus<span>Events</span></div>
  <div class="sidebar-section">Campus Events</div>
  <a href="dashboard.php" class="sidebar-link"><span>🏠</span> Dashboard</a>
  <a href="create-event.php" class="sidebar-link"><span>➕</span> Create Event</a>
  <a href="students.php" class="sidebar-link"><span>👥</span> Students</a>
  <div class="sidebar-divider"></div>
  <div class="sidebar-section">Outer Events</div>
  <a href="outer-events.php" class="sidebar-link active"><span>🌐</span> Outer Events</a>
  <a href="create-outer-event.php" class="sidebar-link"><span>➕</span> Post Outer Event</a>
  <div style="margin-top:auto;padding:20px 24px 0;border-top:1px solid var(--border)">
    <a href="../logout.php" class="sidebar-link" style="padding:10px 0"><span>🚪</span> Logout</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <a href="outer-events.php" class="back-btn">← Back</a>
    <h1>🌐 Outer Event Registrations</h1>
  </div>

  <div class="event-info-card">
    <h2><?= htmlspecialchars($event['title']) ?></h2>
    <p>📅 <?= date('d M Y', strtotime($event['event_date'])) ?> · 📍 <?= htmlspecialchars($event['venue']) ?> · 🏫 <?= htmlspecialchars($event['organizing_college']) ?></p>
  </div>

  <div class="stats">
    <div class="stat"><div class="val"><?= $total ?></div><div class="lbl">Registered</div></div>
    <div class="stat"><div class="val"><?= count($registrations) - $total ?></div><div class="lbl">Cancelled</div></div>
    <div class="stat"><div class="val"><?= count($registrations) ?></div><div class="lbl">Total</div></div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Roll No.</th>
          <th>Department</th>
          <th>College</th>
          <th>Phone</th>
          <th>Year</th>
          <th>Team</th>
          <th>Registered On</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($registrations)): ?>
        <tr><td colspan="10" class="empty">No registrations yet for this event.</td></tr>
        <?php else: ?>
        <?php foreach ($registrations as $i => $r): ?>
        <tr>
          <td style="color:var(--muted)"><?= $i+1 ?></td>
          <td style="font-weight:600"><?= htmlspecialchars($r['full_name']) ?><div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['email']) ?></div></td>
          <td style="color:var(--muted)"><?= htmlspecialchars($r['roll_number']) ?: '—' ?></td>
          <td><?= htmlspecialchars($r['department']) ?: '—' ?></td>
          <td><?= htmlspecialchars($r['college_name']) ?: '—' ?></td>
          <td><?= htmlspecialchars($r['phone']) ?: '—' ?></td>
          <td><?= htmlspecialchars($r['year_of_study']) ?: '—' ?></td>
          <td><?= htmlspecialchars($r['team_name']) ?: '—' ?></td>
          <td style="color:var(--muted)"><?= date('d M Y', strtotime($r['registered_at'])) ?></td>
          <td><span class="badge badge-<?= $r['status'] === 'registered' ? 'active' : 'cancelled' ?>"><?= ucfirst($r['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>