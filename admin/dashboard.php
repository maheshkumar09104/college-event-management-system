<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$total_events = $conn->query("SELECT COUNT(*) AS c FROM events")->fetch_assoc()['c'];
$total_students = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='student'")->fetch_assoc()['c'];
$total_registrations = $conn->query("SELECT COUNT(*) AS c FROM registrations WHERE status='registered'")->fetch_assoc()['c'];
$upcoming = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status='upcoming'")->fetch_assoc()['c'];

$campus_events = $conn->query("
    SELECT e.*, (SELECT COUNT(*) FROM registrations WHERE event_id=e.id AND status='registered') AS reg_count
    FROM events e ORDER BY e.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$outer_events_list = $conn->query("
    SELECT oe.*, (SELECT COUNT(*) FROM outer_registrations WHERE outer_event_id=oe.id AND status='registered') AS reg_count
    FROM outer_events oe ORDER BY oe.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Check if outer_registrations table exists
$outer_reg_table = $conn->query("SHOW TABLES LIKE 'outer_registrations'");
$has_outer_reg = $outer_reg_table && $outer_reg_table->num_rows > 0;
if (!$has_outer_reg) {
    foreach ($outer_events_list as &$oe) $oe['reg_count'] = 0;
}

$students = $conn->query("
    SELECT u.*, (SELECT COUNT(*) FROM registrations WHERE user_id=u.id AND status='registered') AS campus_count,
    u.department, u.roll_number
    FROM users u WHERE u.role='student' ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #060a14; --sidebar: #0d1424; --card: #111827;
    --border: #1a2840; --accent: #3b82f6; --accent2: #f59e0b;
    --text: #f1f5f9; --muted: #64748b; --green: #10b981; --red: #ef4444;
  }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

  /* SIDEBAR */
  .sidebar {
    width: 240px; background: var(--sidebar); border-right: 1px solid var(--border);
    display: flex; flex-direction: column; padding: 0;
    position: fixed; height: 100vh; overflow-y: auto; z-index: 100;
  }
  .sidebar-logo {
    font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800;
    padding: 24px 24px 20px; border-bottom: 1px solid var(--border);
  }
  .sidebar-logo span { color: var(--accent); }

  /* Nav items */
  .nav-item { border-bottom: 1px solid var(--border); }

  .nav-btn {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 24px; font-size: 14px; font-weight: 600;
    color: var(--text); cursor: pointer; background: none; border: none;
    width: 100%; text-align: left; transition: background 0.2s;
    font-family: 'DM Sans', sans-serif;
  }
  .nav-btn:hover { background: rgba(255,255,255,0.04); }
  .nav-btn.active-section { background: rgba(59,130,246,0.08); color: var(--accent); }

  .nav-btn .nav-btn-left { display: flex; align-items: center; gap: 10px; }
  .nav-btn .icon { font-size: 17px; }
  .nav-btn .chevron { font-size: 11px; color: var(--muted); transition: transform 0.2s; }
  .nav-btn.open .chevron { transform: rotate(180deg); }

  /* Submenu */
  .submenu { display: none; background: rgba(0,0,0,0.2); }
  .submenu.open { display: block; }
  .submenu-link {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 24px 10px 44px; font-size: 13px;
    color: var(--muted); text-decoration: none; transition: all 0.15s;
  }
  .submenu-link:hover { color: var(--text); background: rgba(255,255,255,0.04); }
  .submenu-link.active { color: var(--accent); border-left: 2px solid var(--accent); padding-left: 42px; }

  /* Direct nav links (Students) */
  .nav-direct {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 24px; font-size: 14px; font-weight: 600;
    color: var(--text); text-decoration: none; transition: background 0.2s;
    border-bottom: 1px solid var(--border);
  }
  .nav-direct:hover { background: rgba(255,255,255,0.04); }
  .nav-direct.active { background: rgba(59,130,246,0.08); color: var(--accent); }

  /* Sidebar footer */
  .sidebar-footer {
    margin-top: auto; padding: 16px 24px;
    border-top: 1px solid var(--border);
  }
  .admin-name { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
  .admin-role { font-size: 11px; color: var(--muted); margin-bottom: 12px; }
  .logout-btn {
    display: flex; align-items: center; gap: 8px;
    color: #f87171; text-decoration: none; font-size: 13px; font-weight: 500;
    padding: 8px 0; transition: opacity 0.2s;
  }
  .logout-btn:hover { opacity: 0.8; }

  /* MAIN */
  .main { margin-left: 240px; flex: 1; padding: 32px; min-height: 100vh; }

  /* SECTIONS */
  .section { display: none; }
  .section.active { display: block; animation: fadeIn 0.3s ease; }
  @keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

  /* TOPBAR */
  .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
  .topbar h1 { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; }
  .btn-primary {
    display: flex; align-items: center; gap: 8px;
    background: var(--accent); color: #fff; padding: 10px 20px;
    border-radius: 10px; font-family: 'Syne', sans-serif; font-size: 13px;
    font-weight: 700; text-decoration: none; transition: all 0.2s; border: none; cursor: pointer;
  }
  .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
  .btn-amber { background: var(--accent2); color: #000; }
  .btn-amber:hover { opacity: 0.9; background: var(--accent2); }

  /* STATS */
  .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 28px; }
  .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; }
  .stat-icon { font-size: 24px; margin-bottom: 10px; }
  .stat-val { font-family: 'Syne', sans-serif; font-size: 30px; font-weight: 800; color: var(--accent); }
  .stat-val.amber { color: var(--accent2); }
  .stat-lbl { font-size: 13px; color: var(--muted); margin-top: 3px; }

  /* TABLE */
  .table-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; margin-top: 20px; }
  .table-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
  .table-title { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; }
  table { width: 100%; border-collapse: collapse; }
  thead th { padding: 12px 16px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); background: rgba(0,0,0,0.15); border-bottom: 1px solid var(--border); }
  tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(59,130,246,0.04); }
  td { padding: 12px 16px; font-size: 13px; vertical-align: middle; }
  .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .event-cell { display: flex; align-items: center; gap: 10px; }
  .status-badge { font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 600; }
  .s-upcoming { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.2); }
  .s-ongoing { background: rgba(59,130,246,0.1); color: var(--accent); border: 1px solid rgba(59,130,246,0.2); }
  .s-completed { background: rgba(100,116,139,0.1); color: var(--muted); border: 1px solid var(--border); }
  .s-cancelled { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.2); }
  .actions { display: flex; gap: 6px; }
  .action-btn { font-size: 11px; padding: 5px 10px; border-radius: 6px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.15s; }
  .action-edit { background: rgba(59,130,246,0.15); color: var(--accent); }
  .action-edit:hover { background: rgba(59,130,246,0.3); }
  .action-view { background: rgba(16,185,129,0.12); color: var(--green); }
  .action-view:hover { background: rgba(16,185,129,0.25); }
  .action-delete { background: rgba(239,68,68,0.1); color: var(--red); }
  .action-delete:hover { background: rgba(239,68,68,0.2); }
  .cap-bar { width: 70px; height: 4px; background: var(--border); border-radius: 4px; display: inline-block; vertical-align: middle; margin-right: 6px; overflow: hidden; }
  .cap-fill { height: 100%; border-radius: 4px; background: var(--green); }
  .cap-fill.warn { background: var(--accent2); }
  .cap-fill.full { background: var(--red); }

  /* Students table */
  .avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #7c3aed); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; color: #fff; flex-shrink: 0; }
  .search-input { background: rgba(255,255,255,0.04); border: 1px solid var(--border); color: var(--text); padding: 9px 14px; border-radius: 8px; font-size: 13px; outline: none; width: 260px; }
  .search-input:focus { border-color: var(--accent); }

  /* Registered students subsection */
  .reg-section { margin-top: 28px; }
  .reg-section-title { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }

  @media (max-width: 1024px) {
    .stats { grid-template-columns: repeat(2,1fr); }
    .sidebar { display: none; }
    .main { margin-left: 0; }
  }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">Campus<span>Events</span></div>

  <!-- Dashboard (expandable) -->
  <div class="nav-item">
    <button class="nav-btn open active-section" onclick="toggleMenu(this, 'menu-dashboard')" id="btn-dashboard">
      <span class="nav-btn-left"><span class="icon">🏠</span> Dashboard</span>
      <span class="chevron">▼</span>
    </button>
    <div class="submenu open" id="menu-dashboard">
      <a href="#" class="submenu-link active" onclick="showSection('dashboard'); return false;">📊 Overview</a>
      <a href="#" class="submenu-link" onclick="showSection('campus-events'); return false;">🎓 Campus Events</a>
      <a href="#" class="submenu-link" onclick="showSection('outer-events'); return false;">🌐 Outer Events</a>
    </div>
  </div>

  <!-- Students -->
  <a href="#" class="nav-direct" onclick="showSection('students'); return false;" id="nav-students">
    <span class="icon">👥</span> Students
  </a>

  <!-- Footer -->
  <div class="sidebar-footer">
    <div class="admin-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
    <div class="admin-role">Administrator</div>
    <a href="../logout.php" class="logout-btn">🚪 Logout</a>
  </div>
</aside>

<!-- MAIN CONTENT -->
<div class="main">

  <!-- ===== SECTION: OVERVIEW ===== -->
  <div class="section active" id="section-dashboard">
    <div class="topbar">
      <h1>Admin Dashboard</h1>
      <a href="create-event.php" class="btn-primary">➕ Create Event</a>
    </div>
    <div class="stats">
      <div class="stat-card"><div class="stat-icon">🎉</div><div class="stat-val"><?= $total_events ?></div><div class="stat-lbl">Total Events</div></div>
      <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-val"><?= $total_students ?></div><div class="stat-lbl">Students</div></div>
      <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-val"><?= $total_registrations ?></div><div class="stat-lbl">Registrations</div></div>
      <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-val"><?= $upcoming ?></div><div class="stat-lbl">Upcoming</div></div>
    </div>
    <!-- Quick campus events table -->
    <div class="table-card">
      <div class="table-header">
        <div class="table-title">🎓 Campus Events</div>
        <a href="create-event.php" class="btn-primary" style="padding:7px 14px;font-size:12px">➕ New</a>
      </div>
      <table>
        <thead><tr><th>Event</th><th>Date</th><th>Capacity</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($campus_events,0,5) as $e): ?>
          <?php $pct = $e['max_participants']>0 ? round(($e['reg_count']/$e['max_participants'])*100) : 0; ?>
          <tr>
            <td><div class="event-cell"><div class="dot" style="background:<?= $e['banner_color'] ?>"></div><div><div style="font-weight:600"><?= htmlspecialchars($e['title']) ?></div><div style="font-size:11px;color:var(--muted)"><?= $e['category'] ?></div></div></div></td>
            <td><?= date('d M Y', strtotime($e['event_date'])) ?></td>
            <td><div class="cap-bar"><div class="cap-fill <?= $pct>=100?'full':($pct>=75?'warn':'') ?>" style="width:<?= min($pct,100) ?>%"></div></div><?= $e['reg_count'] ?>/<?= $e['max_participants'] ?></td>
            <td><span class="status-badge s-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
            <td><div class="actions"><a href="edit-event.php?id=<?= $e['id'] ?>" class="action-btn action-edit">Edit</a><a href="view-event.php?id=<?= $e['id'] ?>" class="action-btn action-view">View</a><a href="delete-event.php?id=<?= $e['id'] ?>" class="action-btn action-delete" onclick="return confirm('Delete?')">Del</a></div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($campus_events)): ?><tr><td colspan="5" style="text-align:center;padding:30px;color:var(--muted)">No events yet</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ===== SECTION: CAMPUS EVENTS ===== -->
  <div class="section" id="section-campus-events">
    <div class="topbar">
      <h1>🎓 Campus Events</h1>
      <a href="create-event.php" class="btn-primary">➕ Create Event</a>
    </div>
    <div class="stats">
      <div class="stat-card"><div class="stat-icon">🎉</div><div class="stat-val"><?= count($campus_events) ?></div><div class="stat-lbl">Total Events</div></div>
      <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-val"><?= $total_registrations ?></div><div class="stat-lbl">Registrations</div></div>
      <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-val"><?= $upcoming ?></div><div class="stat-lbl">Upcoming</div></div>
      <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-val"><?= count(array_filter($campus_events,fn($e)=>$e['status']==='completed')) ?></div><div class="stat-lbl">Completed</div></div>
    </div>

    <!-- All campus events -->
    <div class="table-card">
      <div class="table-header"><div class="table-title">All Campus Events</div><a href="create-event.php" class="btn-primary" style="padding:7px 14px;font-size:12px">➕ New Event</a></div>
      <table>
        <thead><tr><th>Event</th><th>Category</th><th>Date</th><th>Capacity</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($campus_events as $e): ?>
          <?php $pct = $e['max_participants']>0 ? round(($e['reg_count']/$e['max_participants'])*100) : 0; ?>
          <tr>
            <td><div class="event-cell"><div class="dot" style="background:<?= $e['banner_color'] ?>"></div><div><div style="font-weight:600"><?= htmlspecialchars($e['title']) ?></div><div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($e['venue']) ?></div></div></div></td>
            <td style="color:var(--muted)"><?= $e['category'] ?></td>
            <td><?= date('d M Y', strtotime($e['event_date'])) ?></td>
            <td><div class="cap-bar"><div class="cap-fill <?= $pct>=100?'full':($pct>=75?'warn':'') ?>" style="width:<?= min($pct,100) ?>%"></div></div><?= $e['reg_count'] ?>/<?= $e['max_participants'] ?></td>
            <td><span class="status-badge s-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
            <td><div class="actions"><a href="edit-event.php?id=<?= $e['id'] ?>" class="action-btn action-edit">Edit</a><a href="view-event.php?id=<?= $e['id'] ?>" class="action-btn action-view">View</a><a href="delete-event.php?id=<?= $e['id'] ?>" class="action-btn action-delete" onclick="return confirm('Delete?')">Delete</a></div></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($campus_events)): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">No campus events yet. <a href="create-event.php" style="color:var(--accent)">Create one!</a></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Registered students for campus events -->
    <div class="reg-section">
      <div class="reg-section-title">📋 Registered Students (Campus Events)</div>
      <?php foreach ($campus_events as $ev):
        $regs = $conn->query("SELECT r.*, u.name, u.email, u.roll_number, u.department FROM registrations r JOIN users u ON r.user_id=u.id WHERE r.event_id={$ev['id']} AND r.status='registered'")->fetch_all(MYSQLI_ASSOC);
        if (empty($regs)) continue;
      ?>
      <div class="table-card" style="margin-bottom:16px">
        <div class="table-header">
          <div class="table-title"><?= htmlspecialchars($ev['title']) ?> <span style="font-size:12px;color:var(--muted);font-weight:400">(<?= count($regs) ?> registered)</span></div>
        </div>
        <table>
          <thead><tr><th>#</th><th>Name</th><th>Roll No.</th><th>Dept</th><th>Phone</th><th>Year</th><th>Team</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($regs as $i => $r): ?>
            <tr>
              <td style="color:var(--muted)"><?= $i+1 ?></td>
              <td><div style="font-weight:600"><?= htmlspecialchars($r['name']) ?></div><div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['email']) ?></div></td>
              <td><?= htmlspecialchars($r['roll_number']) ?: '—' ?></td>
              <td><?= htmlspecialchars($r['department']) ?: '—' ?></td>
              <td><?= htmlspecialchars($r['phone'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['year_of_study'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['team_name'] ?? '—') ?></td>
              <td style="color:var(--muted)"><?= date('d M Y', strtotime($r['registered_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach; ?>
      <?php if(empty($campus_events)): ?><p style="color:var(--muted);padding:20px 0">No registrations yet.</p><?php endif; ?>
    </div>
  </div>

  <!-- ===== SECTION: OUTER EVENTS ===== -->
  <div class="section" id="section-outer-events">
    <div class="topbar">
      <h1>🌐 Outer Events</h1>
      <a href="create-outer-event.php" class="btn-primary btn-amber">➕ Post Outer Event</a>
    </div>
    <div class="stats">
      <div class="stat-card"><div class="stat-icon">🌐</div><div class="stat-val amber"><?= count($outer_events_list) ?></div><div class="stat-lbl">Total Outer Events</div></div>
      <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-val amber"><?= count(array_filter($outer_events_list,fn($e)=>$e['status']==='upcoming')) ?></div><div class="stat-lbl">Upcoming</div></div>
      <div class="stat-card"><div class="stat-icon">🏫</div><div class="stat-val amber"><?= count(array_unique(array_column($outer_events_list,'organizing_college'))) ?></div><div class="stat-lbl">Partner Colleges</div></div>
      <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-val amber"><?= array_sum(array_column($outer_events_list,'reg_count')) ?></div><div class="stat-lbl">Registrations</div></div>
    </div>

    <div class="table-card">
      <div class="table-header"><div class="table-title">All Outer Events</div><a href="create-outer-event.php" class="btn-primary btn-amber" style="padding:7px 14px;font-size:12px">➕ New</a></div>
      <table>
        <thead><tr><th>Event</th><th>College</th><th>Date</th><th>Registrations</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($outer_events_list as $e): ?>
          <tr>
            <td><div style="font-weight:600"><?= htmlspecialchars($e['title']) ?></div><div style="font-size:11px;color:var(--muted)"><?= $e['category'] ?></div></td>
            <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($e['organizing_college']) ?></td>
            <td><?= date('d M Y', strtotime($e['event_date'])) ?></td>
            <td><span style="color:var(--accent2);font-weight:600"><?= $e['reg_count'] ?></span> registered</td>
            <td><span class="status-badge s-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
            <td><div class="actions"><a href="edit-outer-event.php?id=<?= $e['id'] ?>" class="action-btn action-edit">Edit</a><a href="view-outer-registrations.php?id=<?= $e['id'] ?>" class="action-btn action-view">View</a><a href="delete-outer-event.php?id=<?= $e['id'] ?>" class="action-btn action-delete" onclick="return confirm('Delete?')">Delete</a></div></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($outer_events_list)): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">No outer events yet</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Outer event registrations -->
    <?php if($has_outer_reg): ?>
    <div class="reg-section">
      <div class="reg-section-title">📋 Registered Students (Outer Events)</div>
      <?php foreach ($outer_events_list as $ev):
        $regs = $conn->query("SELECT r.*, u.name AS user_name, u.email AS user_email FROM outer_registrations r JOIN users u ON r.user_id=u.id WHERE r.outer_event_id={$ev['id']} AND r.status='registered'")->fetch_all(MYSQLI_ASSOC);
        if (empty($regs)) continue;
      ?>
      <div class="table-card" style="margin-bottom:16px">
        <div class="table-header">
          <div class="table-title"><?= htmlspecialchars($ev['title']) ?> <span style="font-size:12px;color:var(--muted);font-weight:400">(<?= count($regs) ?> registered)</span></div>
        </div>
        <table>
          <thead><tr><th>#</th><th>Name</th><th>Roll No.</th><th>College</th><th>Phone</th><th>Year</th><th>Team</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($regs as $i => $r): ?>
            <tr>
              <td style="color:var(--muted)"><?= $i+1 ?></td>
              <td><div style="font-weight:600"><?= htmlspecialchars($r['full_name']) ?></div><div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['email']) ?></div></td>
              <td><?= htmlspecialchars($r['roll_number']) ?: '—' ?></td>
              <td><?= htmlspecialchars($r['college_name']) ?: '—' ?></td>
              <td><?= htmlspecialchars($r['phone']) ?: '—' ?></td>
              <td><?= htmlspecialchars($r['year_of_study']) ?: '—' ?></td>
              <td><?= htmlspecialchars($r['team_name']) ?: '—' ?></td>
              <td style="color:var(--muted)"><?= date('d M Y', strtotime($r['registered_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ===== SECTION: STUDENTS ===== -->
  <div class="section" id="section-students">
    <div class="topbar">
      <h1>👥 Students</h1>
      <input type="text" class="search-input" placeholder="🔍 Search by name, email, dept..." oninput="searchStudents(this.value)">
    </div>
    <div class="stats">
      <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-val"><?= count($students) ?></div><div class="stat-lbl">Total Students</div></div>
      <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-val"><?= count(array_filter($students,fn($s)=>$s['campus_count']>0)) ?></div><div class="stat-lbl">Active</div></div>
    </div>
    <div class="table-card">
      <div class="table-header"><div class="table-title">All Registered Students (<?= count($students) ?>)</div></div>
      <table>
        <thead><tr><th>#</th><th>Student</th><th>Roll No.</th><th>Department</th><th>Joined</th><th>Events</th></tr></thead>
        <tbody id="studentsTable">
          <?php foreach ($students as $i => $s): ?>
          <tr>
            <td style="color:var(--muted)"><?= $i+1 ?></td>
            <td><div style="display:flex;align-items:center;gap:10px"><div class="avatar"><?= strtoupper(substr($s['name'],0,1)) ?></div><div><div style="font-weight:600"><?= htmlspecialchars($s['name']) ?></div><div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($s['email']) ?></div></div></div></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($s['roll_number']) ?: '—' ?></td>
            <td><?= htmlspecialchars($s['department']) ?: '—' ?></td>
            <td style="color:var(--muted)"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
            <td><span style="background:rgba(59,130,246,0.1);color:var(--accent);border:1px solid rgba(59,130,246,0.2);font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600"><?= $s['campus_count'] ?> events</span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($students)): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">No students registered yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- end .main -->

<script>
// Show section
function showSection(name) {
  // Hide all sections
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.getElementById('section-' + name).classList.add('active');

  // Update sidebar active states
  document.querySelectorAll('.submenu-link').forEach(l => l.classList.remove('active'));
  document.querySelectorAll('.nav-direct').forEach(l => l.classList.remove('active'));

  if (name === 'students') {
    document.getElementById('nav-students').classList.add('active');
  } else {
    // Find and highlight the right submenu link
    document.querySelectorAll('.submenu-link').forEach(l => {
      if (l.getAttribute('onclick') && l.getAttribute('onclick').includes("'" + name + "'")) {
        l.classList.add('active');
      }
    });
  }
}

// Toggle submenu
function toggleMenu(btn, menuId) {
  const menu = document.getElementById(menuId);
  btn.classList.toggle('open');
  menu.classList.toggle('open');
}

// Search students
function searchStudents(val) {
  document.querySelectorAll('#studentsTable tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(val.toLowerCase()) ? '' : 'none';
  });
}
</script>
</body>
</html>