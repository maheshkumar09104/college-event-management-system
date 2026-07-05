<?php
// ticket.php — QR Code Ticket for registered students
require_once 'config.php';
requireLogin();

$user_id  = $_SESSION['user_id'];
$event_id = (int)($_GET['event_id'] ?? 0);

if (!$event_id) {
    header("Location: dashboard.php");
    exit();
}

// Verify user is registered for this event
$reg = $conn->query("
    SELECT r.*, e.*, u.name AS student_name, u.email, u.department, u.roll_number
    FROM registrations r
    JOIN events e ON r.event_id = e.id
    JOIN users u ON r.user_id = u.id
    WHERE r.user_id = $user_id AND r.event_id = $event_id AND r.status = 'registered'
")->fetch_assoc();

if (!$reg) {
    header("Location: dashboard.php");
    exit();
}

// Generate a unique ticket ID
$ticket_id = strtoupper(substr(md5($user_id . '-' . $event_id . '-' . $reg['registered_at']), 0, 10));

// QR code data: ticket URL or ticket ID string
$qr_data  = urlencode("TICKET:{$ticket_id}|EVENT:{$reg['title']}|STUDENT:{$reg['student_name']}|DATE:{$reg['event_date']}");
$qr_url   = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={$qr_data}&bgcolor=0d1829&color=f1f5f9&margin=10";
$qr_large = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data={$qr_data}&bgcolor=0d1829&color=f1f5f9&margin=14";

$event_date = date('l, d F Y', strtotime($reg['event_date']));
$event_time = date('h:i A', strtotime($reg['event_time']));
$reg_date   = date('d M Y, h:i A', strtotime($reg['registered_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Ticket – <?= htmlspecialchars($reg['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #060a14;
    --card: #111827;
    --card2: #0d1829;
    --border: #1f2d3d;
    --accent: #3b82f6;
    --text: #f1f5f9;
    --muted: #64748b;
    --green: #10b981;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 32px 16px 60px;
  }

  .bg-glow {
    position: fixed; inset: 0; z-index: 0;
    background:
      radial-gradient(ellipse 70% 50% at 20% 20%, rgba(59,130,246,0.08), transparent),
      radial-gradient(ellipse 60% 70% at 80% 80%, rgba(16,185,129,0.06), transparent);
    pointer-events: none;
  }

  nav {
    width: 100%;
    max-width: 700px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 36px;
    position: relative; z-index: 1;
  }

  .nav-logo { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; }
  .nav-logo span { color: var(--accent); }

  .back-btn {
    font-size: 13px; color: var(--muted); text-decoration: none;
    padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px;
    transition: all 0.2s;
  }
  .back-btn:hover { color: var(--text); border-color: var(--accent); }

  /* ===== TICKET ===== */
  .ticket-wrap {
    position: relative; z-index: 1;
    width: min(620px, 100%);
    animation: slideUp 0.5s ease both;
  }

  @keyframes slideUp {
    from { opacity:0; transform: translateY(24px); }
    to   { opacity:1; transform: translateY(0); }
  }

  .ticket {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 32px 80px rgba(0,0,0,0.5);
  }

  /* Top banner */
  .ticket-banner {
    height: 10px;
    background: <?= $reg['banner_color'] ?>;
  }

  /* Header section */
  .ticket-header {
    padding: 28px 32px 24px;
    border-bottom: 1px dashed var(--border);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
  }

  .ticket-event-name {
    font-family: 'Syne', sans-serif;
    font-size: 22px;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 8px;
  }

  .ticket-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .chip {
    font-size: 11px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
    letter-spacing: 0.04em;
  }

  .chip-category {
    background: rgba(59,130,246,0.12);
    color: var(--accent);
    border: 1px solid rgba(59,130,246,0.25);
  }

  .chip-valid {
    background: rgba(16,185,129,0.1);
    color: var(--green);
    border: 1px solid rgba(16,185,129,0.2);
  }

  .ticket-id-block {
    text-align: right;
    flex-shrink: 0;
  }

  .ticket-id-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--muted);
    margin-bottom: 4px;
  }

  .ticket-id {
    font-family: 'DM Mono', monospace;
    font-size: 18px;
    font-weight: 500;
    color: var(--accent);
    letter-spacing: 2px;
  }

  /* Middle: details + QR */
  .ticket-body {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0;
  }

  .ticket-details {
    padding: 26px 32px;
    border-right: 1px dashed var(--border);
  }

  .detail-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
  }

  .detail-icon {
    font-size: 18px;
    width: 24px;
    flex-shrink: 0;
    margin-top: 1px;
  }

  .detail-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
    margin-bottom: 3px;
  }

  .detail-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    line-height: 1.3;
  }

  /* QR section */
  .ticket-qr {
    padding: 26px 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    background: var(--card2);
  }

  .qr-img {
    width: 160px;
    height: 160px;
    border-radius: 10px;
    border: 3px solid var(--border);
    display: block;
  }

  .qr-label {
    font-size: 11px;
    color: var(--muted);
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  /* Divider notch effect */
  .ticket-divider {
    position: relative;
    height: 1px;
    background: var(--border);
    border-style: dashed;
  }

  .ticket-divider::before,
  .ticket-divider::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--bg);
    top: -10px;
    border: 1px solid var(--border);
  }

  .ticket-divider::before { left: -10px; }
  .ticket-divider::after  { right: -10px; }

  /* Footer */
  .ticket-footer {
    padding: 18px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
  }

  .student-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
  }

  .student-name {
    font-family: 'Syne', sans-serif;
    font-size: 15px;
    font-weight: 700;
  }

  .student-meta {
    font-size: 12px;
    color: var(--muted);
  }

  .reg-timestamp {
    font-size: 12px;
    color: var(--muted);
    text-align: right;
  }

  /* Actions */
  .actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    position: relative; z-index: 1;
    width: min(620px, 100%);
  }

  .btn {
    flex: 1;
    padding: 13px;
    border-radius: 12px;
    font-family: 'Syne', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    text-decoration: none;
    text-align: center;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }

  .btn-outline { background: transparent; color: var(--muted); border: 1px solid var(--border); }
  .btn-outline:hover { color: var(--text); border-color: var(--accent); }

  .print-note {
    margin-top: 16px;
    font-size: 12px;
    color: var(--muted);
    text-align: center;
    position: relative; z-index: 1;
  }

  @media print {
    body { background: #fff; padding: 0; }
    .bg-glow, nav, .actions, .print-note { display: none; }
    .ticket { box-shadow: none; border: 1px solid #ccc; }
    .ticket-banner { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .ticket-id, .detail-value, .student-name { color: #111 !important; }
    .detail-label, .qr-label, .student-meta, .reg-timestamp { color: #666 !important; }
    .ticket-body { grid-template-columns: 1fr auto; }
    .ticket-qr { background: #f8f8f8; }
  }

  @media (max-width: 480px) {
    .ticket-header { flex-direction: column; }
    .ticket-id-block { text-align: left; }
    .ticket-body { grid-template-columns: 1fr; }
    .ticket-details { border-right: none; border-bottom: 1px dashed var(--border); }
    .ticket-qr { flex-direction: row; padding: 20px; }
    .ticket-footer { flex-direction: column; }
    .reg-timestamp { text-align: left; }
    .actions { flex-direction: column; }
  }
</style>
</head>
<body>

<div class="bg-glow"></div>

<?php if (isset($_GET['registered'])): ?>
<div id="successToast" style="position:fixed;top:24px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;padding:14px 28px;border-radius:12px;font-family:'Syne',sans-serif;font-weight:700;font-size:14px;z-index:999;box-shadow:0 8px 32px rgba(0,0,0,0.4);animation:fadeInDown 0.4s ease both">
  ✅ Registered! A confirmation email has been sent.
</div>
<style>
  @keyframes fadeInDown { from{opacity:0;transform:translate(-50%,-16px)} to{opacity:1;transform:translate(-50%,0)} }
</style>
<script>setTimeout(() => { const t = document.getElementById('successToast'); if(t) t.style.opacity='0'; t.style.transition='opacity 0.5s'; }, 4000);</script>
<?php endif; ?>

<nav>
  <div class="nav-logo">Campus<span>Events</span></div>
  <a href="my-events.php" class="back-btn">← My Events</a>
</nav>

<!-- TICKET -->
<div class="ticket-wrap">
  <div class="ticket">

    <div class="ticket-banner"></div>

    <div class="ticket-header">
      <div>
        <div class="ticket-event-name"><?= htmlspecialchars($reg['title']) ?></div>
        <div class="ticket-meta">
          <span class="chip chip-category"><?= $reg['category'] ?></span>
          <span class="chip chip-valid">✓ Valid Ticket</span>
        </div>
      </div>
      <div class="ticket-id-block">
        <div class="ticket-id-label">Ticket ID</div>
        <div class="ticket-id"><?= $ticket_id ?></div>
      </div>
    </div>

    <div class="ticket-body">
      <div class="ticket-details">
        <div class="detail-row">
          <span class="detail-icon">📅</span>
          <div>
            <div class="detail-label">Date</div>
            <div class="detail-value"><?= $event_date ?></div>
          </div>
        </div>
        <div class="detail-row">
          <span class="detail-icon">⏰</span>
          <div>
            <div class="detail-label">Time</div>
            <div class="detail-value"><?= $event_time ?></div>
          </div>
        </div>
        <div class="detail-row">
          <span class="detail-icon">📍</span>
          <div>
            <div class="detail-label">Venue</div>
            <div class="detail-value"><?= htmlspecialchars($reg['venue']) ?></div>
          </div>
        </div>
        <div class="detail-row">
          <span class="detail-icon">🏛</span>
          <div>
            <div class="detail-label">Department</div>
            <div class="detail-value"><?= htmlspecialchars($reg['department']) ?: 'N/A' ?></div>
          </div>
        </div>
      </div>

      <div class="ticket-qr">
        <img src="<?= $qr_url ?>" alt="QR Code" class="qr-img" id="qrImg">
        <div class="qr-label">Scan for Entry</div>
      </div>
    </div>

    <div class="ticket-divider"></div>

    <div class="ticket-footer">
      <div class="student-info">
        <div class="student-name"><?= htmlspecialchars($reg['student_name']) ?></div>
        <div class="student-meta">
          <?= htmlspecialchars($reg['email']) ?>
          <?php if ($reg['roll_number']): ?> · <?= htmlspecialchars($reg['roll_number']) ?><?php endif; ?>
        </div>
      </div>
      <div class="reg-timestamp">
        Registered on<br><?= $reg_date ?>
      </div>
    </div>

  </div>
</div>

<!-- ACTIONS -->
<div class="actions">
  <button class="btn btn-primary" onclick="window.print()">🖨 Print Ticket</button>
  <button class="btn btn-outline" onclick="downloadQR()">⬇ Download QR</button>
  <a href="my-events.php" class="btn btn-outline">← Back</a>
</div>

<div class="print-note">💡 Tip: Print this ticket or save a screenshot to show at the venue.</div>

<script>
function downloadQR() {
  const link = document.createElement('a');
  link.href = '<?= $qr_large ?>';
  link.download = 'ticket-qr-<?= $ticket_id ?>.png';
  link.target = '_blank';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
</script>

</body>
</html>
