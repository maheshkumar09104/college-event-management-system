<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Safe query for campus events - handle if new columns don't exist yet
$my_events = [];
$result = $conn->query("
    SELECT e.*, r.registered_at, r.status AS reg_status,
        r.full_name, r.roll_number, r.department, r.phone, r.team_name
    FROM registrations r
    JOIN events e ON r.event_id = e.id
    WHERE r.user_id = $user_id
    ORDER BY e.event_date ASC
");
if ($result) {
    $my_events = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Fallback if new columns don't exist
    $result = $conn->query("
        SELECT e.*, r.registered_at, r.status AS reg_status
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        WHERE r.user_id = $user_id
        ORDER BY e.event_date ASC
    ");
    if ($result) $my_events = $result->fetch_all(MYSQLI_ASSOC);
}

// Safe query for outer events - handle if table doesn't exist yet
$my_outer_events = [];
$table_check = $conn->query("SHOW TABLES LIKE 'outer_registrations'");
if ($table_check && $table_check->num_rows > 0) {
    $result = $conn->query("
        SELECT oe.*, r.registered_at, r.status AS reg_status,
            r.full_name, r.roll_number, r.department, r.phone,
            r.team_name, r.college_name
        FROM outer_registrations r
        JOIN outer_events oe ON r.outer_event_id = oe.id
        WHERE r.user_id = $user_id
        ORDER BY oe.event_date ASC
    ");
    if ($result) $my_outer_events = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Events – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0b0f1a; --card: #111827; --border: #1f2d3d;
    --accent: #3b82f6; --text: #f1f5f9; --muted: #64748b;
    --green: #10b981; --red: #ef4444; --yellow: #f59e0b;
  }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
  nav {
    background: rgba(17,24,39,0.9); backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border); padding: 0 32px; height: 64px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
  }
  .nav-logo { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; }
  .nav-logo span { color: var(--accent); }
  .nav-links { display: flex; gap: 12px; }
  .nav-link { font-size: 13px; font-weight: 500; color: var(--muted); text-decoration: none; padding: 7px 14px; border-radius: 8px; border: 1px solid var(--border); transition: all 0.2s; }
  .nav-link:hover, .nav-link.active { color: var(--text); border-color: var(--accent); }
  .nav-link.outer { color: var(--yellow); border-color: rgba(245,158,11,0.3); }
  .nav-link.logout { color: #f87171; border-color: rgba(239,68,68,0.3); }
  .main { max-width: 900px; margin: 0 auto; padding: 40px 24px; }
  h1 { font-family: 'Syne', sans-serif; font-size: 30px; font-weight: 800; margin-bottom: 6px; }
  p.sub { color: var(--muted); margin-bottom: 36px; }
  .section-header {
    font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.07em;
    margin-bottom: 14px; margin-top: 32px; padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
  }
  .section-header:first-of-type { margin-top: 0; }
  .event-row {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 14px; padding: 20px 24px; margin-bottom: 12px;
    display: grid; grid-template-columns: 10px 1fr auto; gap: 18px; align-items: center;
    transition: transform 0.2s, box-shadow 0.2s;
    animation: fadeIn 0.3s ease both;
  }
  @keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
  .event-row:hover { transform: translateX(4px); box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
  .event-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
  .event-info h3 { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; margin-bottom: 5px; }
  .event-info .meta { font-size: 13px; color: var(--muted); display: flex; flex-wrap: wrap; gap: 12px; }
  .badge { font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 600; white-space: nowrap; }
  .badge-active { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.2); }
  .badge-cancelled { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.2); }
  .badge-completed { background: rgba(100,116,139,0.1); color: var(--muted); border: 1px solid var(--border); }
  .ticket-btn { font-size:12px; font-weight:600; padding:5px 14px; border-radius:20px; background:rgba(59,130,246,0.12); color:#3b82f6; border:1px solid rgba(59,130,246,0.25); text-decoration:none; white-space:nowrap; }
  .ticket-btn:hover { background:rgba(59,130,246,0.25); }
  .empty { text-align: center; padding: 80px 20px; color: var(--muted); }
  .empty h3 { font-size: 22px; margin-bottom: 10px; color: var(--text); }
  .empty a { color: var(--accent); text-decoration: none; font-weight: 500; }
  @media (max-width: 600px) {
    nav { padding: 0 16px; }
    .main { padding: 24px 14px; }
    .event-row { grid-template-columns: 10px 1fr; }
    .event-row > div:last-child { grid-column: 2; justify-content: flex-start; }
  }
</style>
</head>
<body>
<nav>
  <div class="nav-logo">Campus<span>Events</span></div>
  <div class="nav-links">
    <a href="dashboard.php" class="nav-link">Campus Events</a>
    <a href="outer-events-student.php" class="nav-link outer">🌐 Outer</a>
    <a href="my-events.php" class="nav-link active">My Events</a>
    <a href="logout.php" class="nav-link logout">Logout</a>
  </div>
</nav>

<div class="main">
  <h1>My Registrations</h1>
  <p class="sub">All events you've registered for</p>

  <?php $all_empty = empty($my_events) && empty($my_outer_events); ?>

  <?php if ($all_empty): ?>
  <div class="empty">
    <h3>No registrations yet</h3>
    <p>You haven't registered for any events.<br><a href="dashboard.php">Browse campus events →</a> or <a href="outer-events-student.php">explore outer events →</a></p>
  </div>
  <?php endif; ?>

  <!-- CAMPUS EVENTS -->
  <?php if (!empty($my_events)): ?>
  <div class="section-header" style="color:var(--accent)">🎓 Campus Events (<?= count($my_events) ?>)</div>
  <?php foreach ($my_events as $i => $e): ?>
  <?php
    $color = $e['banner_color'] ?? '#3b82f6';
    $status_class = $e['reg_status'] === 'cancelled' ? 'badge-cancelled' :
                    ($e['status'] === 'completed' ? 'badge-completed' : 'badge-active');
    $label = $e['reg_status'] === 'cancelled' ? 'Cancelled' :
             ($e['status'] === 'completed' ? 'Completed' : 'Registered ✓');
  ?>
  <div class="event-row" style="animation-delay:<?= $i*0.05 ?>s">
    <div class="event-dot" style="background:<?= $color ?>"></div>
    <div class="event-info">
      <h3><?= htmlspecialchars($e['title']) ?></h3>
      <div class="meta">
        <span>📅 <?= date('d M Y', strtotime($e['event_date'])) ?></span>
        <span>📍 <?= htmlspecialchars($e['venue']) ?></span>
        <span>🏷 <?= $e['category'] ?></span>
        <?php if (!empty($e['team_name'])): ?><span>👥 <?= htmlspecialchars($e['team_name']) ?></span><?php endif; ?>
        <span>Registered <?= date('d M Y', strtotime($e['registered_at'])) ?></span>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
      <span class="badge <?= $status_class ?>"><?= $label ?></span>
      <?php if ($e['reg_status'] === 'registered'): ?>
      <a href="ticket.php?event_id=<?= $e['event_id'] ?>" class="ticket-btn">🎟 Ticket</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- OUTER EVENTS -->
  <?php if (!empty($my_outer_events)): ?>
  <div class="section-header" style="color:var(--yellow)">🌐 Outer Events (<?= count($my_outer_events) ?>)</div>
  <?php foreach ($my_outer_events as $i => $e): ?>
  <?php
    $status_class = $e['reg_status'] === 'cancelled' ? 'badge-cancelled' :
                    ($e['status'] === 'completed' ? 'badge-completed' : 'badge-active');
    $label = $e['reg_status'] === 'cancelled' ? 'Cancelled' :
             ($e['status'] === 'completed' ? 'Completed' : 'Registered ✓');
  ?>
  <div class="event-row" style="animation-delay:<?= $i*0.05 ?>s;border-left:3px solid var(--yellow)">
    <div class="event-dot" style="background:var(--yellow)"></div>
    <div class="event-info">
      <h3>
        <?= htmlspecialchars($e['title']) ?>
        <span style="font-size:11px;color:var(--yellow);font-weight:500"> · <?= htmlspecialchars($e['organizing_college']) ?></span>
      </h3>
      <div class="meta">
        <span>📅 <?= date('d M Y', strtotime($e['event_date'])) ?></span>
        <span>📍 <?= htmlspecialchars($e['venue']) ?></span>
        <span>🏷 <?= $e['category'] ?></span>
        <?php if (!empty($e['college_name'])): ?><span>🏫 <?= htmlspecialchars($e['college_name']) ?></span><?php endif; ?>
        <?php if (!empty($e['team_name'])): ?><span>👥 <?= htmlspecialchars($e['team_name']) ?></span><?php endif; ?>
        <span>Registered <?= date('d M Y', strtotime($e['registered_at'])) ?></span>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
      <span class="badge <?= $status_class ?>"><?= $label ?></span>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>
</body>
</html>