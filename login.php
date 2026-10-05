<?php

session_start();

require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = trim($_POST["role"] ?? "");

    // Check that all fields are filled
    if ($email === "" || $password === "" || $role === "") {

        $message = "Please fill in all fields.";

    } else {

        // Find the user
        $sql = "SELECT user_id, username, email, password, role, status
                FROM users
                WHERE email = ? AND role = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param("ss", $email, $role);

            $stmt->execute();

            $result = $stmt->get_result();

            // Check whether user exists
            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                // Check account status
                if ($user["status"] !== "active") {

                    $message = "Your account is inactive.";

                }

                // Check password
                elseif (!password_verify($password, $user["password"])) {

                    $message = "Incorrect password.";

                }

                // Login successful
                else {

                    // Generate a new session ID after successful authentication
                    session_regenerate_id(true);

                    // Store logged-in user information
                    $_SESSION["user_id"] = $user["user_id"];
                    $_SESSION["username"] = $user["username"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];

                    // Redirect according to the user's role
                    if ($user["role"] === "admin") {

                        header("Location: admin/dashboard.php");
                        exit;

                    } elseif ($user["role"] === "employee") {

                        // FIXED: employee folder is singular
                        header("Location: employee/dashboard.php");
                        exit;

                    } elseif ($user["role"] === "technician") {

                        header("Location: technician/dashboard.php");
                        exit;

                    } else {

                        $message = "Invalid user role.";

                    }
                }

            } else {

                $message = "Invalid email or role.";

            }

            $stmt->close();

        } else {

            $message = "Database query failed.";

        }
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Login - IT Help Desk
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body class="auth-page">

<header class="navbar">

    <div class="logo">
        <span>IT</span> Help Desk
    </div>

    <a
        href="index.html"
        class="back-home"
    >
        ← Back to Home
    </a>

</header>


<main class="auth-container">

    <div class="auth-card">

        <div class="auth-header">

            <div class="auth-icon">
                IT
            </div>

            <h1>
                Welcome Back
            </h1>

            <p>
                Sign in to access your IT Help Desk account
            </p>

        </div>


        <form
            method="POST"
            action="login.php"
        >

            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>


            <div class="form-group">

                <label for="role">
                    Login As
                </label>

                <select
                    id="role"
                    name="role"
                    required
                >

                    <option value="">
                        Select your role
                    </option>

                    <option value="employee">
                        Employee
                    </option>

                    <option value="technician">
                        Technician
                    </option>

                    <option value="admin">
                        Admin
                    </option>

                </select>

            </div>


            <div class="form-options">

                <label class="remember">

                    <input
                        type="checkbox"
                        id="remember"
                    >

                    Remember me

                </label>

                <a href="#">
                    Forgot Password?
                </a>

            </div>


            <button
                type="submit"
                class="auth-btn"
            >
                Login
            </button>

        </form>


        <?php if ($message !== ""): ?>

            <div id="loginMessage">

                <?php
                echo htmlspecialchars($message);
                ?>

            </div>

        <?php endif; ?>


        <div class="auth-footer">

            <p>

                Don't have an account?

                <a href="register.html">
                    Create an account
                </a>

            </p>

        </div>

    </div>

</main>

</body>

</html>