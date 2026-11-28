<?php
require 'include/db.php';

$username = 'admin';
$fullName = 'Issa Semauya';               // your real name
$password = 'issa@255';  // 12+ characters

$stmt = db()->prepare("INSERT INTO users (role, username, full_name, password) VALUES ('admin', ?, ?, ?)");
$stmt->execute([$username, $fullName, password_hash($password, PASSWORD_DEFAULT)]);

echo "Admin user created successfully.";