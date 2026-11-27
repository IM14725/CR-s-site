<?php
require 'include/db.php';

$username = 'admin';
$password = 'issa@255';

$stmt = db()->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);

echo "Admin user created successfully.";