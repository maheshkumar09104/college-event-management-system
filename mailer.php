<?php
// mailer.php — Email notification helper
// Uses PHP's built-in mail() function (works on most hosts)
// For XAMPP local testing, install hMailServer or use SMTP via PHPMailer

function sendRegistrationEmail($to_email, $to_name, $event, $qr_url) {
    $site = SITE_NAME;
    $subject = "✅ Registration Confirmed – " . $event['title'];

    $event_date  = date('l, d F Y', strtotime($event['event_date']));
    $event_time  = date('h:i A', strtotime($event['event_time']));
    $venue       = htmlspecialchars($event['venue']);
    $title       = htmlspecialchars($event['title']);
    $category    = htmlspecialchars($event['category']);
    $color       = $event['banner_color'];
    $deadline    = $event['registration_deadline'] ? date('d M Y', strtotime($event['registration_deadline'])) : 'N/A';

    $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#111827;border-radius:16px;overflow:hidden;border:1px solid #1f2d3d;">

        <!-- Banner -->
        <tr><td style="background:{$color};height:8px;font-size:0">&nbsp;</td></tr>

        <!-- Header -->
        <tr><td style="padding:32px 36px 20px;">
          <p style="margin:0 0 6px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;">$site</p>
          <h1 style="margin:0;font-size:22px;font-weight:800;color:#f1f5f9;">🎉 You're Registered!</h1>
        </td></tr>

        <!-- Body -->
        <tr><td style="padding:0 36px 28px;">
          <p style="color:#94a3b8;font-size:15px;line-height:1.6;margin:0 0 24px;">
            Hi <strong style="color:#f1f5f9;">$to_name</strong>,<br>
            Your registration for <strong style="color:#f1f5f9;">$title</strong> is confirmed. Show the QR code below at the venue for entry.
          </p>

          <!-- Event Details Box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1829;border:1px solid #1f2d3d;border-radius:12px;margin-bottom:28px;">
            <tr><td style="padding:20px 24px;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="padding:6px 0;color:#64748b;font-size:13px;width:120px;">📅 Date</td>
                  <td style="padding:6px 0;color:#f1f5f9;font-size:13px;font-weight:600;">$event_date</td>
                </tr>
                <tr>
                  <td style="padding:6px 0;color:#64748b;font-size:13px;">⏰ Time</td>
                  <td style="padding:6px 0;color:#f1f5f9;font-size:13px;font-weight:600;">$event_time</td>
                </tr>
                <tr>
                  <td style="padding:6px 0;color:#64748b;font-size:13px;">📍 Venue</td>
                  <td style="padding:6px 0;color:#f1f5f9;font-size:13px;font-weight:600;">$venue</td>
                </tr>
                <tr>
                  <td style="padding:6px 0;color:#64748b;font-size:13px;">🏷 Category</td>
                  <td style="padding:6px 0;color:#f1f5f9;font-size:13px;font-weight:600;">$category</td>
                </tr>
                <tr>
                  <td style="padding:6px 0;color:#64748b;font-size:13px;">⏳ Deadline</td>
                  <td style="padding:6px 0;color:#f1f5f9;font-size:13px;font-weight:600;">$deadline</td>
                </tr>
              </table>
            </td></tr>
          </table>

          <!-- QR Code -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1829;border:1px solid #1f2d3d;border-radius:12px;margin-bottom:28px;">
            <tr><td align="center" style="padding:28px;">
              <p style="margin:0 0 16px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.08em;">Your Entry QR Code</p>
              <img src="{$qr_url}" width="180" height="180" alt="QR Code" style="display:block;border-radius:8px;border:4px solid #1f2d3d;">
              <p style="margin:12px 0 0;color:#64748b;font-size:12px;">Scan this at the venue for entry</p>
            </td></tr>
          </table>

          <!-- CTA Button -->
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center">
              <a href="{$qr_url}" style="display:inline-block;background:#3b82f6;color:#fff;padding:13px 32px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;">View My Ticket →</a>
            </td></tr>
          </table>
        </td></tr>

        <!-- Footer -->
        <tr><td style="padding:20px 36px;border-top:1px solid #1f2d3d;">
          <p style="margin:0;color:#475569;font-size:12px;text-align:center;">
            © 2025 $site · This is an automated confirmation email.
          </p>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $site <noreply@campusevents.edu>\r\n";
    $headers .= "Reply-To: noreply@campusevents.edu\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($to_email, $subject, $body, $headers);
}


function sendCancellationEmail($to_email, $to_name, $event) {
    $site    = SITE_NAME;
    $subject = "❌ Registration Cancelled – " . $event['title'];
    $title   = htmlspecialchars($event['title']);
    $date    = date('d M Y', strtotime($event['event_date']));

    $body = <<<HTML
<!DOCTYPE html><html><body style="background:#0b0f1a;font-family:'Segoe UI',Arial,sans-serif;padding:40px 20px;">
  <table width="560" align="center" style="background:#111827;border-radius:16px;border:1px solid #1f2d3d;overflow:hidden;">
    <tr><td style="background:#ef4444;height:8px;font-size:0">&nbsp;</td></tr>
    <tr><td style="padding:36px;">
      <h2 style="color:#f1f5f9;margin:0 0 12px;">Registration Cancelled</h2>
      <p style="color:#94a3b8;font-size:15px;line-height:1.6;">
        Hi <strong style="color:#f1f5f9;">$to_name</strong>,<br><br>
        Your registration for <strong style="color:#f1f5f9;">$title</strong> (scheduled on $date) has been cancelled successfully.
      </p>
      <p style="color:#64748b;font-size:13px;margin-top:24px;">You can re-register anytime before the deadline if you change your mind.</p>
    </td></tr>
    <tr><td style="padding:16px 36px;border-top:1px solid #1f2d3d;">
      <p style="margin:0;color:#475569;font-size:12px;text-align:center;">© 2025 $site</p>
    </td></tr>
  </table>
</body></html>
HTML;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $site <noreply@campusevents.edu>\r\n";

    return mail($to_email, $subject, $body, $headers);
}
