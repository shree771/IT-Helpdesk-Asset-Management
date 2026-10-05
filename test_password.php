<?php

require_once "config/database.php";

$email = "admin@ithelpdesk.com";
$password = "Admin@123";

$sql = "SELECT password FROM users WHERE email = ? LIMIT 1";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $email);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    if (password_verify($password, $user["password"])) {

        echo "PASSWORD TEST: SUCCESS";

    } else {

        echo "PASSWORD TEST: FAILED";
    }

} else {

    echo "USER NOT FOUND";
}

$stmt->close();
$conn->close();

?>