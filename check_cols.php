<?php
require_once 'public/api/db.php';
$res = $conn->query('SHOW COLUMNS FROM bookings;');
echo "Bookings: ";
if ($res) {
    while($row=$res->fetch_assoc()){echo $row['Field'].' ';}
}
echo "\nBeds: ";
$res2 = $conn->query('SHOW COLUMNS FROM beds;');
if ($res2) {
    while($row=$res2->fetch_assoc()){echo $row['Field'].' ';}
}
echo "\nPayments: ";
$res2 = $conn->query('SHOW COLUMNS FROM payments;');
if ($res2) {
    while($row=$res2->fetch_assoc()){echo $row['Field'].' ';}
}
echo "\n";
