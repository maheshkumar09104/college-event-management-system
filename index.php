<?php
require_once 'config.php';
if (isLoggedIn()) {
    header("Location: " . (isAdmin() ? "admin/dashboard.php" : "dashboard.php"));
} else {
    header("Location: login.php");
}
exit();

