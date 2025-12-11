<?php
session_start();
if (!isset($_SESSION['user'])) {
    die("Unauthorized access.");
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "invoice";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$new_pass = trim($_POST['new_password']);
$confirm_pass = trim($_POST['confirm_password']);

if (empty($new_pass) || empty($confirm_pass)) {
    die("Password fields cannot be empty.");
}

if ($new_pass !== $confirm_pass) {
    die("Passwords do not match.");
}

$hashed = password_hash($new_pass, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE login SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $hashed, $_SESSION['user']);

if ($stmt->execute()) {
    echo "Password updated successfully!";
} else {
    echo "Error updating password.";
}

$stmt->close();
$conn->close();
?>
