<?php
// admin/delete-outer-event.php
require_once '../config.php';
requireLogin();
requireAdmin();
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $event = $conn->query("SELECT poster_image FROM outer_events WHERE id=$id")->fetch_assoc();
    if ($event && $event['poster_image']) {
        @unlink('../uploads/posters/' . $event['poster_image']);
    }
    $conn->query("DELETE FROM outer_events WHERE id=$id");
}
header("Location: outer-events.php");
exit();