<?php
session_start();
session_destroy(); // Clear all session data

// Redirect to login page
header("Location: login.html");
exit();
