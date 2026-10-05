<?php

require_once "config/database.php";

$email = "admin@ithelpdesk.com";
$role = "admin";

$sql = "SELECT user_id, username, email, password, role, status
        FROM users
        WHERE email = ? AND role = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

$stmt->bind_param("ss", $email, $role);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    echo "USER FOUND<br><br>";

    echo "User ID: " . $user["user_id"] . "<br>";
    echo "Username: " . $user["username"] . "<br>";
    echo "Email: " . $user["email"] . "<br>";
    echo "Role: " . $user["role"] . "<br>";
    echo "Status: " . $user["status"] . "<br><br>";

    if ($user["status"] === "active") {
        echo "STATUS TEST: SUCCESS<br>";
    } else {
        echo "STATUS TEST: FAILED<br>";
    }

} else {

    echo "USER NOT FOUND";
}

$stmt->close();
$conn->close();

?>