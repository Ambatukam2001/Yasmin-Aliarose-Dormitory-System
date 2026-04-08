<?php
include 'db.php';

if ($conn) {
    echo "✅ Connected to Railway MySQL successfully!";
} else {
    echo "❌ Connection failed!";
}
?>
