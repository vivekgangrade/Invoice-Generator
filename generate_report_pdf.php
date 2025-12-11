<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;

// DB connection
$conn = new mysqli("localhost", "root", "", "invoice");
$result = $conn->query("SELECT * FROM invoices");

$html = '<h2 style="text-align:center;">Sales Report</h2>';
$html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
$html .= '<tr>
  <th>Date</th>
  <th>Client</th>
  <th>Item</th>
  <th>Qty</th>
  <th>Revenue</th>
  <th>Tax</th>
  <th>Discount</th>
  <th>Total</th>
</tr>';

while ($row = $result->fetch_assoc()) {
    $items = json_decode($row['items'], true);
    $invoiceDate = $row['invoice_date'];
    $client = $row['client_name'];
    $tax = $row['taxAmount'];
    $discount = $row['discount'];
    $invoiceTotal = $row['total'];
    
    $firstRow = true;

    foreach ($items as $item) {
        $revenue = $item['quantity'] * $item['price'];

        $html .= "<tr>
          <td>{$invoiceDate}</td>
          <td>{$client}</td>
          <td>{$item['name']}</td>
          <td>{$item['quantity']}</td>
          <td>₹" . number_format($revenue, 2) . "</td>";

        if ($firstRow) {
            $html .= "
              <td rowspan='" . count($items) . "'>₹" . number_format($tax, 2) . "</td>
              <td rowspan='" . count($items) . "'>₹" . number_format($discount, 2) . "</td>
              <td rowspan='" . count($items) . "'>₹" . number_format($invoiceTotal, 2) . "</td>";
            $firstRow = false;
        } else {
            $html .= "<td></td><td></td><td></td>";
        }

        $html .= "</tr>";
    }
}

$html .= '</table>';

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("sales_report.pdf", ["Attachment" => true]);
