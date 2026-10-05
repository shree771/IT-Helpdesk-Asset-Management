<?php

require_once "config/database.php";

$username = "admin";
$email = "admin@ithelpdesk.com";
$password = "Admin@123";
$role = "admin";
$status = "active";

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (username, email, password, role, status)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssss",
    $username,
    $email,
    $hashedPassword,
    $role,
    $status
);

if ($stmt->execute()) {
    echo "Admin account created successfully.<br><br>";
    echo "Email: admin@ithelpdesk.com<br>";
    echo "Password: Admin@123";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

?>