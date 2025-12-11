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
if (count($items) <= 1) {
    // Only one item, delete the whole invoice
    $deleteStmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
    $deleteStmt->bind_param("i", $id);
    $deleteStmt->execute();
    echo "Invoice deleted";
} else {
    // Remove the item and update
    array_splice($items, $item_index, 1);
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['quantity'] * $item['price'];
    }
    $taxAmount = $subtotal * 0.18;
    $total = $subtotal + $taxAmount;
    $items_json = json_encode($items);

    $update = $conn->prepare("UPDATE invoices SET items = ?, subtotal = ?, taxAmount = ?, total = ? WHERE id = ?");
    $update->bind_param("sdddi", $items_json, $subtotal, $taxAmount, $total, $id);
    $update->execute();

    echo "Item deleted";
}

$conn->close();
