<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$database = "invoice";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed."]);
    exit;
}

header('Content-Type: application/json');

// Include `discount` in the SQL query
$sql = "SELECT id, client_name, invoice_date, items, subtotal, discount, taxAmount, total FROM invoices ORDER BY created_at DESC";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $conn->error]);
    exit;
}

$reports = [];

while ($row = $result->fetch_assoc()) {
    $items = json_decode($row['items'], true);
    if (!$items) continue;

    foreach ($items as $index => $item) {
        $quantity = $item['quantity'];
        $price = $item['price'];
        $revenue = $quantity * $price;

        // Per-item discount fallback
        $item_discount = isset($item['discount']) ? $item['discount'] : 0;

        // Invoice-level discount
        $invoice_discount = isset($row['discount']) ? $row['discount'] : 0;

        // Approximate per-item CGST
        $item_count = count($items);
        $cgst = $row['taxAmount'] / 2 / $item_count;

        $reports[] = [
            "id" => $row['id'],
            "date" => $row['invoice_date'],
            "client_name" => $row['client_name'],
            "item_index" => $index,
            "item_name" => $item['name'],
            "quantity" => $quantity,
            "price" => $price,
            "revenue" => $revenue,
            "cgst" => $cgst,
            "discount" => $invoice_discount, // this is the invoice-level discount
            "item_discount" => $item_discount, // optional, per-item if used
            "total_with_tax" => $row['total']
        ];
    }
}

echo json_encode($reports);
$conn->close();
