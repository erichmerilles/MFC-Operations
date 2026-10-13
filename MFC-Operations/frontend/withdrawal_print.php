<?php

//declare(strict_types=1);
require_once __DIR__ . '/../backend/config/app.php';
require_login();

$no = trim((string)($_GET['withdrawal_no'] ?? ''));
if ($no === '') {
    http_response_code(400);
    exit('Withdrawal number is required.');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT w.*, c.client_name FROM withdrawals w JOIN clients c ON c.id = w.client_id WHERE w.withdrawal_no = ? LIMIT 1');
$stmt->execute([$no]);
$withdrawal = $stmt->fetch();
if (!$withdrawal) {
    http_response_code(404);
    exit('Withdrawal not found.');
}

$stmt = $pdo->prepare('SELECT wi.*, p.u_m, p.kg_per_box FROM withdrawal_items wi LEFT JOIN products p ON p.id = wi.product_id WHERE wi.withdrawal_id = ? ORDER BY wi.line_no');
$stmt->execute([(int)$withdrawal['id']]);
$items = $stmt->fetchAll();

function print_qty($value): string
{
    return rtrim(rtrim(number_format((float)$value, 3, '.', ''), '0'), '.');
}
function print_time(?string $value): string
{
    if (!$value) return '';
    $timestamp = strtotime($value);
    return $timestamp === false ? '' : date('h:i A', $timestamp);
}
$displayDate = strtoupper(date('F d, Y', strtotime((string)$withdrawal['form_date'])));
$logoUrl = APP_BASE_URL . '/frontend/assets/images/company-logo.png';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Withdrawal Form - <?= e($no) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 9mm 10mm 9mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            font-size: 9px;
        }

        .print-actions {
            max-width: 190mm;
            margin: 0 auto 10px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .print-actions button {
            border: 1px solid #aab3bd;
            border-radius: 4px;
            background: #fff;
            padding: 7px 13px;
            cursor: pointer;
        }

        .sheet {
            width: 190mm;
            max-width: 100%;
            margin: 0 auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header-table td,
        .items-table td,
        .items-table th,
        .footer-table td {
            border: 1px solid #222;
        }

        .header-table {
            height: 32mm;
        }

        .company-cell {
            width: 64%;
            padding: 3px 5px;
            vertical-align: middle;
        }

        .company-logo {
            display: block;
            width: 100%;
            max-height: 18mm;
            object-fit: contain;
            object-position: left center;
        }

        .company-details {
            font-size: 7.2px;
            line-height: 1.25;
            padding-left: 76px;
            margin-top: -1px;
        }

        .form-title {
            font-size: 12px;
            font-weight: 700;
            text-align: left;
            padding: 3px 4px;
        }

        .meta-label {
            width: 44%;
        }

        .meta-cell {
            padding: 3px 4px;
            height: 7mm;
            font-size: 9px;
        }

        .meta-label strong {
            font-weight: 400;
        }

        .client-table {
            margin-top: 0;
        }

        .client-label {
            width: 64%;
            height: 6mm;
            font-weight: 700;
            padding: 3px 4px;
        }

        .client-value {
            text-align: center;
            height: 8mm;
            padding: 3px 4px;
        }

        .date-value {
            text-align: center;
            font-weight: 700;
            height: 8mm;
            padding: 3px 4px;
        }

        .spacer-band td {
            height: 5mm;
        }

        .orange-band {
            background: #f4b183;
        }

        .po-row td {
            height: 9mm;
        }

        .po-label-cell {
            width: 5%;
        }

        .po-value {
            color: #f00;
            font-weight: 700;
            text-align: center;
            font-size: 10px;
        }

        .items-table {
            margin-top: 0;
        }

        .items-table .line-col {
            width: 5%;
        }

        .items-table .product-col {
            width: 43%;
        }

        .items-table .qty-col {
            width: 20%;
        }

        .items-table .kg-col {
            width: 15%;
        }

        .items-table .remarks-col {
            width: 17%;
        }

        .items-table th {
            height: 9mm;
            padding: 2px 3px;
            text-align: center;
            font-size: 8.5px;
            font-weight: 700;
        }

        .items-table td {
            height: 5.45mm;
            padding: 1px 3px;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }

        .line-cell,
        .qty-cell,
        .kg-cell {
            text-align: center;
        }

        .product-cell {
            text-align: left;
        }

        .remarks-cell {
            color: #f00;
            font-size: 8px;
        }

        .nothing-follows {
            font-weight: 700;
            white-space: nowrap;
            letter-spacing: .1px;
        }

        .footer-table {
            margin-top: 0;
        }

        .footer-table td {
            height: 6.2mm;
            padding: 2px 3px;
        }

        .footer-left {
            width: 64%;
            font-weight: 700;
        }

        .footer-right {
            width: 36%;
        }

        .approved {
            height: 10mm !important;
            font-weight: 700;
        }

        .footer-note {
            border: 0 !important;
            font-style: italic;
            font-weight: 400;
        }

        @media screen {
            body {
                background: #edf0f4;
                padding: 16px;
            }

            .sheet {
                background: white;
                padding: 0;
                box-shadow: 0 2px 14px #0001;
            }
        }

        @media print {
            .print-actions {
                display: none !important;
            }

            .sheet {
                width: 100%;
                max-width: none;
                box-shadow: none;
            }

            body {
                font-size: 9px;
            }
        }
    </style>
</head>

<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Print</button><button type="button" onclick="window.close()">Close</button></div>
    <main class="sheet">
        <table class="header-table" aria-label="Withdrawal header">
            <tr>
                <td class="company-cell" rowspan="4">
                    <img class="company-logo" src="<?= e($logoUrl) ?>" alt="Hightower Storage and Trading Centre, Inc.">
                    <!--<div class="company-details">
                        Address: 502-A Carlos Palanca Sr. St., Quiapo, Manila<br>
                        Tel No.: (02) 5310-1181 | (02) 5310-1182 | (02) 5310-1163<br>
                        Email: htstorage2012@gmail.com
                    </div>-->
                </td>
                <td colspan="2" class="form-title">WITHDRAWAL FORM</td>
            </tr>
            <tr>
                <td class="meta-cell meta-label">No.</td>
                <td class="meta-cell"><?= e($withdrawal['withdrawal_no']) ?></td>
            </tr>
            <tr>
                <td class="meta-cell meta-label">Time Received:</td>
                <td class="meta-cell"><?= e(print_time($withdrawal['time_received'])) ?></td>
            </tr>
            <tr>
                <td class="meta-cell meta-label">Pick Up Time:</td>
                <td class="meta-cell" style="color:#f00;font-weight:700"><?= e(print_time($withdrawal['pick_up_time'])) ?></td>
            </tr>
        </table>

        <table class="client-table" aria-label="Client and date">
            <tr>
                <td class="client-label">CLIENT'S NAME:</td>
                <td style="width:36%"></td>
            </tr>
            <tr>
                <td class="client-value"><?= e($withdrawal['client_name']) ?></td>
                <td class="date-value"><?= e($displayDate) ?></td>
            </tr>
        </table>

        <table class="items-table" aria-label="Withdrawal items">
            <colgroup>
                <col class="line-col">
                <col class="product-col">
                <col class="qty-col">
                <col class="kg-col">
                <col class="remarks-col">
            </colgroup>
            <tbody>
                <tr class="spacer-band">
                    <td class="orange-band" colspan="2"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="po-row">
                    <td></td>
                    <td class="po-value"><?= e($withdrawal['po_no'] ?? '') ?></td>
                    <th>QUANTITY<br>PER UOM</th>
                    <th></th>
                    <th></th>
                </tr>
                <?php for ($i = 0; $i < 30; $i++):
                    $item = $items[$i] ?? null;
                    $isMarker = $i === count($items) && count($items) < 30;
                    $qtyText = '';
                    $kgText = '';
                    if ($item) {
                        $qtyText = print_qty($item['quantity']);
                        if (trim((string)($item['u_m'] ?? '')) !== '') $qtyText .= ' ' . trim((string)$item['u_m']);
                        if ($item['kg_per_box'] !== null && $item['kg_per_box'] !== '') {
                            $kgText = print_qty((float)$item['quantity'] * (float)$item['kg_per_box']) . ' KGS';
                        }
                    }
                    $remark = $item['remarks'] ?? '';
                ?>
                    <tr>
                        <td class="line-cell"><?= $i + 1 ?></td>
                        <td class="product-cell"><?php if ($item): ?><?= e($item['product_name']) ?><?php elseif ($isMarker): ?><span class="nothing-follows">*************NOTHING FOLLOWS***************************</span><?php endif; ?></td>
                        <td class="qty-cell"><?= e($qtyText) ?></td>
                        <td class="kg-cell"><?= e($kgText) ?></td>
                        <td class="remarks-cell"><?= e($remark) ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <table class="footer-table" aria-label="Approval and sign-off">
            <tr>
                <td class="footer-left">PREPARED/ REQUEST BY:</td>
                <td class="footer-right approved">APPROVED BY:</td>
            </tr>
            <tr>
                <td class="footer-left"></td>
                <td class="footer-right" rowspan="4"></td>
            </tr>
            <tr>
                <td class="footer-left"><em>FOR HSCTI USE ONLY</em></td>
            </tr>
            <tr>
                <td class="footer-left">PREPARED/ PICKED BY:</td>
            </tr>
            <tr>
                <td class="footer-left footer-note">Updated HSCTI Form 2021</td>
            </tr>
        </table>
    </main>
</body>

</html>