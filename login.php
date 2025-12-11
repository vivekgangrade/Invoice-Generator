<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$database = "invoice";

// Create database connection
$conn = new mysqli($servername, $username, $password);

// Create database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS $database");
$conn = new mysqli($servername, $username, $password, $database);

// Create `login` table with `name` column if not exists
$conn->query("CREATE TABLE IF NOT EXISTS login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $action = $_POST['action'];

    if (empty($email) || empty($password)) {
        die("Email and password required.");
    }

    if ($action === "register") {
        $name = trim($_POST['name'] ?? '');

        if (empty($name)) {
            die("Name is required.");
        }

        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM login WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            die("Email already registered.");
        }

        $check->close();

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO login (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashed);

        if ($stmt->execute()) {
            echo "Account created successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    if ($action === "login") {
        $stmt = $conn->prepare("SELECT name, password FROM login WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($name, $hashed_password);
        $stmt->fetch();
        $stmt->close();

        if ($hashed_password && password_verify($password, $hashed_password)) {
            $_SESSION['user'] = $email;
            $_SESSION['user_name'] = $name;
            echo "success";
        } else {
            echo "Invalid credentials.";
        }
    }
}

$conn->close();
?>
