<?php
// Load DOMPDF
require __DIR__ . '/pdf/dompdf/vendor/autoload.php';
use Dompdf\Dompdf;

// Check invoice ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Invoice ID not specified");
}

$invoiceId = (int)$_GET['id'];

// DB connection
$conn = new mysqli("localhost", "root", "", "invoice");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch invoice
$stmt = $conn->prepare("SELECT id, invoice_date, client_name, client_address, items, subtotal, discount, taxAmount, total FROM invoices WHERE id = ?");
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: Invoice not found");
}

$row = $result->fetch_assoc();
$items = json_decode($row['items'], true);

// Build HTML
$html = '<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #333; text-align: center; margin-bottom: 20px; }
        .invoice-info { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f2f2f2; text-align: left; padding: 8px; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background-color: #f9f9f9; }
    </style>
    <title>Invoice #'.$row['id'].'</title>
</head>
<body>
    <h2>INVOICE</h2>
    
    <div class="invoice-info">
        <p><strong>Invoice #:</strong> '.$row['id'].'</p>
        <p><strong>Date:</strong> '.$row['invoice_date'].'</p>
        <p><strong>Client:</strong> '.htmlspecialchars($row['client_name']).'</p>
        <p><strong>Address:</strong> '.htmlspecialchars($row['client_address']).'</p>
    </div>

    <table>
        <tr>
            <th>Item</th>
            <th>Quantity</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">Amount</th>
        </tr>';

foreach ($items as $item) {
    $amount = $item['quantity'] * $item['price'];
    $html .= '
        <tr>
            <td>'.htmlspecialchars($item['name']).'</td>
            <td>'.htmlspecialchars($item['quantity']).'</td>
            <td class="text-right">$'.number_format($item['price'], 2).'</td>
            <td class="text-right">$'.number_format($amount, 2).'</td>
        </tr>';
}

$html .= '
        <tr class="total-row">
            <td colspan="3" class="text-right">Subtotal:</td>
            <td class="text-right">$'.number_format($row['subtotal'], 2).'</td>
        </tr>
        <tr class="total-row">
            <td colspan="3" class="text-right">Discount:</td>
            <td class="text-right">-$'.number_format($row['discount'], 2).'</td>
        </tr>
        <tr class="total-row">
            <td colspan="3" class="text-right">Tax (18%):</td>
            <td class="text-right">$'.number_format($row['taxAmount'], 2).'</td>
        </tr>
        <tr class="total-row">
            <td colspan="3" class="text-right">Total Amount:</td>
            <td class="text-right">$'.number_format($row['total'], 2).'</td>
        </tr>
    </table>
</body>
</html>';

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output PDF
$dompdf->stream("invoice_".$row['id'].".pdf", ["Attachment" => true]);

$conn->close();
?>
