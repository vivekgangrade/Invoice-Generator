<?php
session_start();

// DB connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "invoice";

$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database and tables
$conn->query("CREATE DATABASE IF NOT EXISTS $database");
$conn->select_db($database);

$conn->query("CREATE TABLE IF NOT EXISTS login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255),
    client_address TEXT,
    client_pincode VARCHAR(10),
    client_city VARCHAR(100),
    client_state VARCHAR(100),
    client_phone VARCHAR(20),
    invoice_date DATE,
    items TEXT,
    subtotal DECIMAL(10,2),
    taxAmount DECIMAL(10,2),
    discount DECIMAL(10,2),
    total DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Load DOMPDF
require_once 'pdf/dompdf/autoload.inc.php';
use Dompdf\Dompdf;

// Handle POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Register/Login
    if (isset($_POST['action'])) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (empty($email) || empty($password)) {
            die("Error: Email or password is missing.");
        }

        if ($_POST['action'] === 'register') {
            $check_stmt = $conn->prepare("SELECT id FROM login WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                die("Error: Email already registered.");
            }
            $check_stmt->close();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO login (email, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $email, $hashed_password);

            if ($stmt->execute()) {
                echo "Account created successfully!";
                header("Refresh:3; url=login.html");
                exit();
            } else {
                die("Insert error: " . $stmt->error);
            }
        }

        if ($_POST['action'] === 'login') {
            $stmt = $conn->prepare("SELECT password FROM login WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($hashed_password);
            $stmt->fetch();
            $stmt->close();

            if ($hashed_password && password_verify($password, $hashed_password)) {
                $_SESSION['user'] = $email;
                echo "success";
            } else {
                echo "Invalid email or password.";
            }
        }
    }

    // Invoice Submission
    elseif (isset($_POST['client_name'], $_POST['invoice_date'])) {
        $client_name = $_POST['client_name'];
        $client_address = $_POST['client_address'];
        $client_pincode = $_POST['client_pincode'];
        $client_city = $_POST['client_city'];
        $client_state = $_POST['client_state'];
        $client_phone = $_POST['client_phone'];
        $invoice_date = $_POST['invoice_date'];
        $items = json_decode($_POST['items'], true);
        $items_json = json_encode($items);

        $subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0.0;
        $taxAmount = isset($_POST['taxAmount']) ? (float)$_POST['taxAmount'] : 0.0;
        $discount = isset($_POST['discount']) ? (float)$_POST['discount'] : 0.0;
        $total = isset($_POST['total']) ? (float)$_POST['total'] : 0.0;

        $stmt = $conn->prepare("INSERT INTO invoices (client_name, client_address, client_pincode, client_city, client_state, client_phone, invoice_date, items, subtotal, taxAmount, discount, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            die("Prepare failed at invoice insert: " . $conn->error);
        }

        $stmt->bind_param("ssssssssdddd", $client_name, $client_address, $client_pincode, $client_city, $client_state, $client_phone, $invoice_date, $items_json, $subtotal, $taxAmount, $discount, $total);
        if (!$stmt->execute()) {
            die("Insert execution failed: " . $stmt->error);
        }

        $invoiceId = $conn->insert_id;

        // Build the PDF
        $html = "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Invoice</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #fff;
            color: #333;
            margin: 0;
            padding: 0;
            font-size: 12px;
        }

        .invoice-box {
            width: 100%;
            padding: 10px 20px;
            box-sizing: border-box;
        }

        h1 {
            text-align: center;
            font-size: 24px;
            margin: 0 0 15px 0;
            color: #1f2937;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 30px;
        }

        .meta div {
            line-height: 1.5;
        }

        .section-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 13px;
            color: #555;
        }

        .invoice-id {
            display: inline-block;
            background-color: #e6f4ff;
            color: #007acc;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        table thead {
            background-color: #1f2937;
            color: #ffffff;
        }

        th, td {
            padding: 8px 6px;
            border: 1px solid #ddd;
            text-align: left;
        }

        td.text-right {
            text-align: right;
        }

        tbody tr:nth-child(even) {
            background-color: #f6f6f6;
        }

        .totals {
            margin-top: 20px;
            width: 100%;
            max-width: 320px;
            margin-left: auto;
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 12px 14px;
        }

        .totals p {
            display: flex;
            justify-content: space-between;
            margin: 6px 0;
            font-size: 12px;
        }

        .totals .total {
            font-size: 14px;
            font-weight: bold;
            color: #16a34a;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 10px;
            color: #777;
        }

        .clearfix::after {
            content: '';
            display: block;
            clear: both;
        }
    </style>
</head>
<body>
    <div class='invoice-box'>
        <h1>Invoice</h1>
        <div class='meta'>
            <div>
                <div class='section-title'>Client Info</div>
                <p><strong>Name:</strong> $client_name</p>
                <p><strong>Address:</strong> $client_address</p>
                <p><strong>City:</strong> $client_city, $client_state - $client_pincode</p>
                <p><strong>Phone:</strong> $client_phone</p>
            </div>
            <div>
                <div class='section-title'>Invoice Details</div>
                <p><strong>Date:</strong> $invoice_date</p>
                <p><strong>Invoice #:</strong> <span class='invoice-id'>#$invoiceId</span></p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th class='text-right'>Price</th>
                    <th class='text-right'>Line Total</th>
                </tr>
            </thead>
            <tbody>";
foreach ($items as $item) {
    $lineTotal = $item['quantity'] * $item['price'];
    $html .= "<tr>
        <td>" . htmlspecialchars($item['name']) . "</td>
        <td>" . htmlspecialchars($item['quantity']) . "</td>
        <td class='text-right'>$" . number_format($item['price'], 2) . "</td>
        <td class='text-right'>$" . number_format($lineTotal, 2) . "</td>
    </tr>";
}
$html .= "</tbody>
        </table>

        <div class='totals'>
            <p><span>Subtotal:</span><span>$" . number_format($subtotal, 2) . "</span></p>
            <p><span>Tax:</span><span>$" . number_format($taxAmount, 2) . "</span></p>
            <p><span>Discount:</span><span>-$" . number_format($discount, 2) . "</span></p>
            <p class='total'><span>Total:</span><span>$" . number_format($total, 2) . "</span></p>
        </div>

        <div class='footer'>
            Thank you for your business!
        </div>
    </div>
</body>
</html>";


        // Generate PDF
        $pdf = new Dompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->stream("invoice_$invoiceId.pdf", ["Attachment" => false]);
        exit;
    }
}

$conn->close();
?>
