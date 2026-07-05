<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: dashboard.php"); exit(); }

$event = $conn->query("SELECT * FROM events WHERE id = $id")->fetch_assoc();
if (!$event) { header("Location: dashboard.php"); exit(); }

$students = $conn->query("
    SELECT u.name, u.email, u.department, u.roll_number, r.registered_at, r.status
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    WHERE r.event_id = $id
    ORDER BY r.registered_at ASC
")->fetch_all(MYSQLI_ASSOC);

$registered_count = count(array_filter($students, fn($s) => $s['status'] === 'registered'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Event – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --bg: #060a14; --sidebar: #0d1424; --card: #111827; --border: #1a2840; --accent: #3b82f6; --text: #f1f5f9; --muted: #64748b; --green: #10b981; --red: #ef4444; }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
  .sidebar { width: 240px; background: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 28px 0; position: fixed; height: 100vh; }
  .sidebar-logo { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; padding: 0 24px 28px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
  .sidebar-logo span { color: var(--accent); }
  .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 24px; font-size: 14px; color: var(--muted); text-decoration: none; transition: all 0.2s; }
  .sidebar-link:hover, .sidebar-link.active { background: rgba(59,130,246,0.1); color: var(--text); border-right: 3px solid var(--accent); }
  .main { margin-left: 240px; flex: 1; padding: 36px 32px; }
  .topbar { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
  .back-btn { color: var(--muted); text-decoration: none; font-size: 13px; padding: 8px 14px; border: 1px solid var(--border); border-radius: 8px; }
  h1 { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; }
  .event-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; margin-bottom: 28px; }
  .event-banner { height: 10px; }
  .event-body { padding: 28px; }
  .event-title { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; margin-bottom: 10px; }
  .event-desc { color: var(--muted); font-size: 14px; line-height: 1.7; margin-bottom: 24px; }
  .event-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }
  .detail-box { background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 10px; padding: 14px; }
  .detail-box .dlabel { font-size: 11px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); margin-bottom: 5px; }
  .detail-box .dvalue { font-size: 14px; font-weight: 600; }
  .section-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; margin-bottom: 18px; }
  .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
  table { width: 100%; border-collapse: collapse; }
  thead th { padding: 12px 18px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); background: rgba(0,0,0,0.2); border-bottom: 1px solid var(--border); }
  tbody tr { border-bottom: 1px solid var(--border); }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(59,130,246,0.04); }
  td { padding: 13px 18px; font-size: 14px; }
  .badge { font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 600; }
  .badge-active { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.2); }
  .badge-cancelled { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.2); }
  .empty-table { text-align: center; padding: 40px; color: var(--muted); }
  @media (max-width: 768px) { .sidebar { display: none; } .main { margin-left: 0; padding: 24px 16px; } }
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo">Campus<span>Events</span></div>
  <a href="dashboard.php" class="sidebar-link active"><span>🏠</span> Dashboard</a>
  <a href="create-event.php" class="sidebar-link"><span>➕</span> Create Event</a>
  <a href="students.php" class="sidebar-link"><span>👥</span> Students</a>
  <div style="margin-top:auto;padding:20px 24px 0;border-top:1px solid var(--border)">
    <a href="../logout.php" class="sidebar-link" style="padding:10px 0"><span>🚪</span> Logout</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <a href="dashboard.php" class="back-btn">← Back</a>
    <h1>Event Details</h1>
  </div>

  <div class="event-card">
    <div class="event-banner" style="background:<?= $event['banner_color'] ?>"></div>
    <div class="event-body">
      <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
      <div class="event-desc"><?= htmlspecialchars($event['description']) ?></div>
      <div class="event-grid">
        <div class="detail-box"><div class="dlabel">Date</div><div class="dvalue"><?= date('d M Y', strtotime($event['event_date'])) ?></div></div>
        <div class="detail-box"><div class="dlabel">Time</div><div class="dvalue"><?= date('h:i A', strtotime($event['event_time'])) ?></div></div>
        <div class="detail-box"><div class="dlabel">Venue</div><div class="dvalue"><?= htmlspecialchars($event['venue']) ?></div></div>
        <div class="detail-box"><div class="dlabel">Category</div><div class="dvalue"><?= $event['category'] ?></div></div>
        <div class="detail-box"><div class="dlabel">Registrations</div><div class="dvalue" style="color:var(--green)"><?= $registered_count ?> / <?= $event['max_participants'] ?></div></div>
        <div class="detail-box"><div class="dlabel">Status</div><div class="dvalue"><?= ucfirst($event['status']) ?></div></div>
      </div>
    </div>
  </div>

  <div class="section-title">Registered Students (<?= $registered_count ?>)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Roll No.</th>
          <th>Department</th>
          <th>Email</th>
          <th>Registered On</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
        <tr><td colspan="7" class="empty-table">No students have registered yet.</td></tr>
        <?php else: ?>
        <?php foreach ($students as $i => $s): ?>
        <tr>
          <td style="color:var(--muted)"><?= $i + 1 ?></td>
          <td style="font-weight:600"><?= htmlspecialchars($s['name']) ?></td>
          <td style="color:var(--muted)"><?= htmlspecialchars($s['roll_number']) ?: '—' ?></td>
          <td><?= htmlspecialchars($s['department']) ?: '—' ?></td>
          <td style="color:var(--muted)"><?= htmlspecialchars($s['email']) ?></td>
          <td><?= date('d M Y', strtotime($s['registered_at'])) ?></td>
          <td><span class="badge <?= $s['status'] === 'registered' ? 'badge-active' : 'badge-cancelled' ?>"><?= ucfirst($s['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>