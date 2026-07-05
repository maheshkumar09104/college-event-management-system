<?php
$conn = new mysqli('127.0.0.1', 'root', '', '', 3306);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

$sql = file_get_contents(__DIR__ . '/database.sql');

if ($conn->multi_query($sql)) {
    echo "✅ Database imported successfully! <a href='index.php'>Go to site →</a>";
} else {
    echo "❌ Error: " . $conn->error;
}
?>
```

**Step 2** — Save the file

**Step 3** — Open your browser and go to:
```
http://localhost/college_events/setup.php
```

This will create the database and import all tables automatically!

---

**Step 4** — After you see ✅ success, open:
```
http://localhost/college_events/