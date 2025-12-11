<?php
session_start();

// DB connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "invoice";

// Connect to DB
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Make sure the user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.html");
    exit();
}

$email = $_SESSION['user'];

// Delete the user
$stmt = $conn->prepare("DELETE FROM login WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

// Close session and redirect
if ($stmt->affected_rows > 0) {
    $stmt->close();
    $conn->close();
    session_destroy();
    header("Location: goodbye.html");
    exit();
} else {
    echo "Error: Unable to delete account.";
}
?>
