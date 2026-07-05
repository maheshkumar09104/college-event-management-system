<?php
// run-setup.php — ONE-TIME USE: creates all tables in Aiven database
// DELETE THIS FILE after running it once successfully.

require_once 'config.php';

$sql = file_get_contents(__DIR__ . '/database.sql');

if ($sql === false) {
    die("Could not read database.sql — make sure it's uploaded in the same folder.");
}

// Split on semicolons that end a statement (simple splitter, works for this schema)
$statements = array_filter(array_map('trim', explode(';', $sql)));

$success = true;
$log = [];

foreach ($statements as $stmt) {
    if ($stmt === '') continue;
    if (mysqli_query($conn, $stmt)) {
        $log[] = "OK: " . substr($stmt, 0, 60) . "...";
    } else {
        $success = false;
        $log[] = "ERROR: " . mysqli_error($conn) . " -- in statement: " . substr($stmt, 0, 80);
    }
}

echo "<pre>";
echo $success ? "✅ All tables created successfully!\n\n" : "⚠️ Some statements failed. See details below:\n\n";
foreach ($log as $line) {
    echo htmlspecialchars($line) . "\n";
}
echo "</pre>";
echo "<p><strong>Important:</strong> Delete this file (run-setup.php) from your project now, then push again.</p>";
?>
