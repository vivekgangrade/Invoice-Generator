<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "invoice";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    die("Connection failed: " . $conn->connect_error);
}

$id = $_POST['id'];
$item_index = $_POST['item_index'];
$quantity = $_POST['quantity'];
$price = $_POST['price'];
$discount = isset($_POST['discount']) ? (float)$_POST['discount'] : 0;

$sql = "SELECT items FROM invoices WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    http_response_code(404);
    echo "Invoice not found";
    exit;
}

$items = json_decode($row['items'], true);

// Update item
$items[$item_index]['quantity'] = (int)$quantity;
$items[$item_index]['price'] = (float)$price;
$items[$item_index]['discount'] = $discount;

// Recalculate subtotal and discount
$subtotal = 0;
$total_discount = 0;

foreach ($items as $it) {
    $item_total = $it['quantity'] * $it['price'];
    $item_discount = isset($it['discount']) ? $it['discount'] : 0;
    $subtotal += $item_total;
    $total_discount += $item_discount;
}

$subtotal_after_discount = $subtotal - $total_discount;
$taxAmount = $subtotal_after_discount * 0.18; // 18% tax
$total = $subtotal_after_discount + $taxAmount;

$items_json = json_encode($items);

// Save updated data to DB
$update = $conn->prepare("UPDATE invoices SET items = ?, subtotal = ?, discount = ?, taxAmount = ?, total = ? WHERE id = ?");
$update->bind_param("sddddi", $items_json, $subtotal, $total_discount, $taxAmount, $total, $id);
$update->execute();

echo "Update successful";
$conn->close();
?>
