<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: dashboard.php"); exit(); }
$event = $conn->query("SELECT * FROM events WHERE id=$id")->fetch_assoc();
if (!$event) { header("Location: dashboard.php"); exit(); }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = clean($_POST['title']);
    $description  = clean($_POST['description']);
    $category     = clean($_POST['category']);
    $venue        = clean($_POST['venue']);
    $event_date   = clean($_POST['event_date']);
    $event_time   = clean($_POST['event_time']);
    $max_part     = (int)$_POST['max_participants'];
    $deadline     = clean($_POST['registration_deadline']);
    $banner_color = clean($_POST['banner_color']);
    $status       = clean($_POST['status']);
    $reg_link     = clean($_POST['registration_link']);
    $contact_name = clean($_POST['contact_name']);
    $contact_email= clean($_POST['contact_email']);
    $contact_phone= clean($_POST['contact_phone']);
    $poster_name  = $event['poster_image'];

    if (!empty($_FILES['poster']['name'])) {
        $upload_dir = '../uploads/posters/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed) && $_FILES['poster']['size'] <= 5*1024*1024) {
            $poster_name = uniqid('poster_') . '.' . $ext;
            move_uploaded_file($_FILES['poster']['tmp_name'], $upload_dir . $poster_name);
        }
    }

    $stmt = $conn->prepare("UPDATE events SET title=?,description=?,category=?,venue=?,event_date=?,event_time=?,max_participants=?,registration_deadline=?,banner_color=?,poster_image=?,registration_link=?,contact_name=?,contact_email=?,contact_phone=?,status=? WHERE id=?");
    $stmt->bind_param("ssssssissssssssi", $title,$description,$category,$venue,$event_date,$event_time,$max_part,$deadline,$banner_color,$poster_name,$reg_link,$contact_name,$contact_email,$contact_phone,$status,$id);
    if ($stmt->execute()) {
        $success = "Event updated successfully!";
        $event = $conn->query("SELECT * FROM events WHERE id=$id")->fetch_assoc();
    } else {
        $error = "Update failed: " . $conn->error;
    }
}

$categories = ['Technical','Cultural','Sports','Academic','Workshop','Seminar','Other'];
$colors = ['#3b82f6','#e91e8c','#ff6d00','#00897b','#9333ea','#f59e0b','#ef4444','#06b6d4'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Event – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --bg: #060a14; --sidebar: #0d1424; --card: #111827; --border: #1a2840; --accent: #3b82f6; --text: #f1f5f9; --muted: #64748b; --green: #10b981; --red: #ef4444; }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
  .sidebar { width: 240px; background: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 28px 0; position: fixed; height: 100vh; overflow-y: auto; }
  .sidebar-logo { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; padding: 0 24px 20px; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
  .sidebar-logo span { color: var(--accent); }
  .sidebar-section { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); padding: 10px 24px 6px; }
  .sidebar-link { display: flex; align-items: center; gap: 10px; padding: 11px 24px; font-size: 14px; color: var(--muted); text-decoration: none; transition: all 0.2s; }
  .sidebar-link:hover, .sidebar-link.active { background: rgba(59,130,246,0.1); color: var(--text); border-right: 3px solid var(--accent); }
  .sidebar-divider { height: 1px; background: var(--border); margin: 12px 0; }
  .main { margin-left: 240px; flex: 1; padding: 36px 32px; }
  .topbar { display: flex; align-items: center; gap: 16px; margin-bottom: 28px; }
  .back-btn { color: var(--muted); text-decoration: none; font-size: 13px; padding: 8px 14px; border: 1px solid var(--border); border-radius: 8px; }
  h1 { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; }
  .form-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 36px; max-width: 800px; }
  .section-title { font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 16px; margin-top: 28px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
  .section-title:first-child { margin-top: 0; }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
  .field { margin-bottom: 16px; }
  label { display: block; font-size: 12px; font-weight: 500; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); margin-bottom: 7px; }
  input, select, textarea { width: 100%; background: rgba(255,255,255,0.04); border: 1px solid var(--border); color: var(--text); padding: 12px 14px; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; transition: border 0.2s; appearance: none; }
  input:focus, select:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
  select option { background: #1e293b; }
  textarea { resize: vertical; min-height: 90px; }
  .color-picker { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 6px; }
  .color-dot { width: 32px; height: 32px; border-radius: 50%; cursor: pointer; border: 3px solid transparent; transition: all 0.2s; }
  .color-dot.selected { border-color: #fff; transform: scale(1.2); }
  .color-dot input[type="radio"] { display: none; }
  .poster-upload { border: 2px dashed var(--border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; position: relative; }
  .poster-upload:hover { border-color: var(--accent); }
  .poster-upload input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
  .poster-upload p { color: var(--muted); font-size: 13px; }
  .current-poster { width: 100%; max-height: 180px; object-fit: cover; border-radius: 10px; margin-bottom: 10px; }
  #posterPreview { width: 100%; max-height: 180px; object-fit: cover; border-radius: 10px; margin-top: 10px; display: none; }
  .alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; }
  .alert-error { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.3); color: var(--red); }
  .alert-success { background: rgba(52,211,153,0.1); border: 1px solid rgba(52,211,153,0.3); color: var(--green); }
  .form-actions { display: flex; gap: 12px; margin-top: 28px; }
  .btn { padding: 13px 28px; border-radius: 10px; font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: #2563eb; }
  .btn-outline { background: transparent; color: var(--muted); border: 1px solid var(--border); text-decoration: none; display: flex; align-items: center; }
  @media (max-width: 768px) { .grid-2, .grid-3 { grid-template-columns: 1fr; } .sidebar { display: none; } .main { margin-left: 0; padding: 24px 16px; } }
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo">Campus<span>Events</span></div>
  <div class="sidebar-section">Campus Events</div>
  <a href="dashboard.php" class="sidebar-link active"><span>🏠</span> Dashboard</a>
  <a href="create-event.php" class="sidebar-link"><span>➕</span> Create Event</a>
  <a href="students.php" class="sidebar-link"><span>👥</span> Students</a>
  <div class="sidebar-divider"></div>
  <div class="sidebar-section">Outer Events</div>
  <a href="outer-events.php" class="sidebar-link"><span>🌐</span> Outer Events</a>
  <a href="create-outer-event.php" class="sidebar-link"><span>➕</span> Post Outer Event</a>
  <div style="margin-top:auto;padding:20px 24px 0;border-top:1px solid var(--border)">
    <a href="../logout.php" class="sidebar-link" style="padding:10px 0"><span>🚪</span> Logout</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <a href="dashboard.php" class="back-btn">← Back</a>
    <h1>Edit Campus Event</h1>
  </div>

  <?php if ($error): ?><div class="alert alert-error">⚠️ <?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

  <div class="form-card">
    <form method="POST" enctype="multipart/form-data">
      <div class="section-title">📋 Basic Information</div>
      <div class="field"><label>Event Title *</label><input type="text" name="title" required value="<?= htmlspecialchars($event['title']) ?>"></div>
      <div class="field"><label>Description</label><textarea name="description"><?= htmlspecialchars($event['description']) ?></textarea></div>
      <div class="grid-2">
        <div class="field"><label>Category</label>
          <select name="category"><?php foreach($categories as $c): ?><option value="<?= $c ?>" <?= $event['category']===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>Status</label>
          <select name="status"><?php foreach(['upcoming','ongoing','completed','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $event['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
        </div>
      </div>

      <div class="section-title">📅 Date & Venue</div>
      <div class="grid-2">
        <div class="field"><label>Event Date</label><input type="date" name="event_date" value="<?= $event['event_date'] ?>"></div>
        <div class="field"><label>Event Time</label><input type="time" name="event_time" value="<?= $event['event_time'] ?>"></div>
      </div>
      <div class="field"><label>Venue</label><input type="text" name="venue" value="<?= htmlspecialchars($event['venue']) ?>"></div>

      <div class="section-title">📝 Registration Settings</div>
      <div class="grid-2">
        <div class="field"><label>Max Participants</label><input type="number" name="max_participants" min="1" value="<?= $event['max_participants'] ?>"></div>
        <div class="field"><label>Registration Deadline</label><input type="date" name="registration_deadline" value="<?= $event['registration_deadline'] ?>"></div>
      </div>
      <div class="field"><label>External Registration Link (optional)</label><input type="url" name="registration_link" placeholder="Leave empty for in-app registration" value="<?= htmlspecialchars($event['registration_link'] ?? '') ?>"></div>

      <div class="section-title">👤 Contact Details</div>
      <div class="grid-3">
        <div class="field"><label>Contact Person</label><input type="text" name="contact_name" value="<?= htmlspecialchars($event['contact_name'] ?? '') ?>"></div>
        <div class="field"><label>Contact Email</label><input type="email" name="contact_email" value="<?= htmlspecialchars($event['contact_email'] ?? '') ?>"></div>
        <div class="field"><label>Contact Phone</label><input type="tel" name="contact_phone" value="<?= htmlspecialchars($event['contact_phone'] ?? '') ?>"></div>
      </div>

      <div class="section-title">🎨 Banner Color</div>
      <div class="color-picker">
        <?php foreach ($colors as $c): ?>
        <label class="color-dot <?= $event['banner_color']===$c?'selected':'' ?>" style="background:<?= $c ?>">
          <input type="radio" name="banner_color" value="<?= $c ?>" <?= $event['banner_color']===$c?'checked':'' ?>>
        </label>
        <?php endforeach; ?>
      </div>

      <div class="section-title" style="margin-top:24px">🖼 Event Poster</div>
      <div class="poster-upload">
        <input type="file" name="poster" accept="image/*" onchange="previewPoster(this)">
        <?php if (!empty($event['poster_image'])): ?>
          <img src="../uploads/posters/<?= htmlspecialchars($event['poster_image']) ?>" class="current-poster" alt="Current Poster">
          <p>Current poster above. Click to replace.</p>
        <?php else: ?>
          <p>Click to upload a poster (JPG, PNG, WEBP, max 5MB)</p>
        <?php endif; ?>
        <img id="posterPreview" src="" alt="New Preview">
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Save Changes</button>
        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script>
document.querySelectorAll('.color-dot input').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('.color-dot').forEach(d => d.classList.remove('selected'));
    radio.parentElement.classList.add('selected');
  });
});
function previewPoster(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { const img = document.getElementById('posterPreview'); img.src = e.target.result; img.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>