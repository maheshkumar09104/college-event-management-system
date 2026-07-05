<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$students = $conn->query("
    SELECT u.*,
        (SELECT COUNT(*) FROM registrations WHERE user_id=u.id AND status='registered') AS reg_count
    FROM users u
    WHERE u.role = 'student'
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Students – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --bg: #060a14; --sidebar: #0d1424; --card: #111827; --border: #1a2840; --accent: #3b82f6; --text: #f1f5f9; --muted: #64748b; --green: #10b981; }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
  .sidebar { width: 240px; background: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 28px 0; position: fixed; height: 100vh; }
  .sidebar-logo { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; padding: 0 24px 28px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
  .sidebar-logo span { color: var(--accent); }
  .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 24px; font-size: 14px; color: var(--muted); text-decoration: none; transition: all 0.2s; }
  .sidebar-link:hover, .sidebar-link.active { background: rgba(59,130,246,0.1); color: var(--text); border-right: 3px solid var(--accent); }
  .main { margin-left: 240px; flex: 1; padding: 36px 32px; }
  h1 { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; margin-bottom: 6px; }
  p.sub { color: var(--muted); margin-bottom: 28px; font-size: 14px; }
  .search-bar { background: rgba(255,255,255,0.04); border: 1px solid var(--border); color: var(--text); padding: 11px 16px; border-radius: 10px; font-size: 14px; width: 300px; outline: none; margin-bottom: 20px; }
  .search-bar:focus { border-color: var(--accent); }
  .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
  table { width: 100%; border-collapse: collapse; }
  thead th { padding: 13px 18px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); background: rgba(0,0,0,0.2); border-bottom: 1px solid var(--border); }
  tbody tr { border-bottom: 1px solid var(--border); }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(59,130,246,0.04); }
  td { padding: 13px 18px; font-size: 14px; }
  .avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #7c3aed); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; color: #fff; flex-shrink: 0; }
  .name-cell { display: flex; align-items: center; gap: 12px; }
  .reg-badge { background: rgba(59,130,246,0.1); color: var(--accent); border: 1px solid rgba(59,130,246,0.2); font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 600; }
  @media (max-width: 768px) { .sidebar { display: none; } .main { margin-left: 0; padding: 24px 16px; } }
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo">Campus<span>Events</span></div>
  <a href="dashboard.php" class="sidebar-link"><span>🏠</span> Dashboard</a>
  <a href="create-event.php" class="sidebar-link"><span>➕</span> Create Event</a>
  <a href="students.php" class="sidebar-link active"><span>👥</span> Students</a>
  <div style="margin-top:auto;padding:20px 24px 0;border-top:1px solid var(--border)">
    <a href="../logout.php" class="sidebar-link" style="padding:10px 0"><span>🚪</span> Logout</a>
  </div>
</aside>

<div class="main">
  <h1>Students</h1>
  <p class="sub">All registered students (<?= count($students) ?> total)</p>
  <input type="text" class="search-bar" id="searchInput" placeholder="🔍  Search by name, email, or department..." oninput="searchStudents(this.value)">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th>Roll No.</th>
          <th>Department</th>
          <th>Joined</th>
          <th>Events Registered</th>
        </tr>
      </thead>
      <tbody id="studentsTable">
        <?php foreach ($students as $i => $s): ?>
        <tr>
          <td style="color:var(--muted)"><?= $i + 1 ?></td>
          <td>
            <div class="name-cell">
              <div class="avatar"><?= strtoupper(substr($s['name'], 0, 1)) ?></div>
              <div>
                <div style="font-weight:600"><?= htmlspecialchars($s['name']) ?></div>
                <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($s['email']) ?></div>
              </div>
            </div>
          </td>
          <td style="color:var(--muted)"><?= htmlspecialchars($s['roll_number']) ?: '—' ?></td>
          <td><?= htmlspecialchars($s['department']) ?: '—' ?></td>
          <td style="color:var(--muted)"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
          <td><span class="reg-badge"><?= $s['reg_count'] ?> events</span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">No students registered yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
function searchStudents(val) {
  const rows = document.querySelectorAll('#studentsTable tr');
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(val.toLowerCase()) ? '' : 'none';
  });
}
</script>
</body>
</html>