<?php
// admin/delete-event.php
require_once '../config.php';
requireLogin();
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $conn->query("DELETE FROM registrations WHERE event_id = $id");
    $conn->query("DELETE FROM events WHERE id = $id");
}
header("Location: dashboard.php");
exit();