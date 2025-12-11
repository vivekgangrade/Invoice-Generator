<?php
header('Content-Type: application/json');

// DB connection
$conn = new mysqli("localhost", "root", "", "invoice");

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed"]));
}

// Fetch Total Sales
$sales_result = $conn->query("SELECT SUM(total) AS total_sales FROM invoices");
$sales = $sales_result->fetch_assoc()['total_sales'] ?? 0;

// Fetch Total Users
$user_result = $conn->query("SELECT COUNT(*) AS new_users FROM login");
$users = $user_result->fetch_assoc()['new_users'] ?? 0;

// Fetch Total Orders
$order_result = $conn->query("SELECT COUNT(*) AS orders FROM invoices");
$orders = $order_result->fetch_assoc()['orders'] ?? 0;

// Fetch Sales Overview: monthly total sales (last 6 months)
$monthly_sales = [];
$month_names = [];

$monthly_query = "
  SELECT DATE_FORMAT(created_at, '%b') AS month, 
         SUM(total) AS total
  FROM invoices
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  GROUP BY MONTH(created_at)
  ORDER BY MONTH(created_at)
";

$monthly_result = $conn->query($monthly_query);
while ($row = $monthly_result->fetch_assoc()) {
    $month_names[] = $row['month'];
    $monthly_sales[] = (float)$row['total'];
}

// Return JSON
echo json_encode([
  "total_sales" => $sales,
  "new_users" => $users,
  "orders" => $orders,
  "sales_labels" => $month_names,
  "sales_overview" => $monthly_sales
]);

$conn->close();
?>
