<?php
session_start();
// Assuming user data is stored in the session after login
if (isset($_SESSION['user_name'])) {
    echo json_encode(['user_name' => $_SESSION['user_name']]);
} else {
    echo json_encode(['user_name' => null]);
}
?>