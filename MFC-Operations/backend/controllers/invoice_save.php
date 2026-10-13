<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/frontend/withdrawals.php');
    exit;
}
verify_csrf();
$pdo = db();
$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$no = trim($_POST['withdrawal_no'] ?? '');
try {
    $pdo->beginTransaction();
    $s = $pdo->prepare('SELECT i.*,w.withdrawal_no FROM invoices i JOIN withdrawals w ON w.id=i.withdrawal_id WHERE i.id=? FOR UPDATE');
    $s->execute([$invoiceId]);
    $inv = $s->fetch();
    if (!$inv || $inv['withdrawal_no'] !== $no) throw new RuntimeException('Invoice draft was not found.');
    if ($inv['status'] === 'Invoiced') throw new RuntimeException('A confirmed QuickBooks invoice cannot be edited here.');
    $date = trim($_POST['invoice_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new RuntimeException('A valid invoice date is required.');
    $raw = $_POST['items'] ?? [];
    $valid = [];
    foreach ($raw as $it) {
        $name = trim((string)($it['product_name'] ?? ''));
        $qty = trim((string)($it['quantity'] ?? ''));
        $price = trim((string)($it['price_each'] ?? ''));
        if ($name === '' && $qty === '' && $price === '') continue;
        if ($name === '' || $qty === '' || !is_numeric($qty) || (float)$qty <= 0 || $price === '' || !is_numeric($price) || (float)$price < 0) throw new RuntimeException('Every invoice line needs a product, positive quantity, and non-negative price.');
        $ps = $pdo->prepare('SELECT id,item_code,description,u_m FROM products WHERE active=1 AND product_name=?');
        $ps->execute([$name]);
        $prod = $ps->fetch();
        if (!$prod) throw new RuntimeException('Product "' . $name . '" is not in the active product list.');
        $site = (int)($it['site_id'] ?? 0);
        $ss = $pdo->prepare('SELECT id FROM sites WHERE id=? AND active=1');
        $ss->execute([$site]);
        if (!$ss->fetch()) throw new RuntimeException('Select a valid site for every invoice line.');
        $source = ($it['source_type'] ?? '') === 'ADDITIONAL' ? 'ADDITIONAL' : 'WITHDRAWAL';
        $valid[] = ['id' => (int)($it['id'] ?? 0), 'withdrawal_item_id' => ($source === 'WITHDRAWAL' && ($it['withdrawal_item_id'] ?? '') !== '' ? (int)$it['withdrawal_item_id'] : null), 'source' => $source, 'product' => $prod, 'name' => $name, 'site' => $site, 'qty' => (float)$qty, 'lot' => trim((string)($it['lot_number'] ?? '')), 'item_code' => trim((string)($it['item_code'] ?? '')), 'description' => trim((string)($it['description'] ?? '')), 'u_m' => trim((string)($it['u_m'] ?? '')), 'price' => (float)$price, 'tax' => in_array(($it['tax_code'] ?? 'Non'), ['Non', 'Tax', 'Exempt'], true) ? $it['tax_code'] : 'Non', 'expiration' => trim((string)($it['expiration_date'] ?? ''))];
    }
    if (!$valid) throw new RuntimeException('At least one invoice line is required.');
    $s = $pdo->prepare('UPDATE invoices SET invoice_date=?,po_no=?,status=?,updated_by=? WHERE id=?');
    $s->execute([$date, trim($_POST['po_no'] ?? '') ?: null, 'Draft', $_SESSION['user']['id'], $invoiceId]);
    $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id=?')->execute([$invoiceId]);
    $ins = $pdo->prepare('INSERT INTO invoice_items(invoice_id,withdrawal_item_id,source_type,product_id,site_id,product_name,quantity,lot_number,item_code,description,u_m,price_each,amount,tax_code,expiration_date,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($valid as $it) {
        $ins->execute([$invoiceId, $it['withdrawal_item_id'], $it['source'], $it['product']['id'], $it['site'], $it['name'], $it['qty'], $it['lot'] ?: null, $it['item_code'] ?: $it['product']['item_code'], $it['description'] ?: $it['product']['description'], $it['u_m'] ?: $it['product']['u_m'], $it['price'], round($it['qty'] * $it['price'], 2), $it['tax'], $it['expiration'] ?: null, $_SESSION['user']['id'], $_SESSION['user']['id']]);
    }
    log_activity($pdo, 'SAVE_DRAFT', 'invoice', $invoiceId, 'Saved invoice draft for ' . $no . ' with ' . count($valid) . ' line(s)');
    $pdo->commit();
    flash('success', 'Invoice draft saved. The original withdrawal was not changed.');
    header('Location: ' . APP_BASE_URL . '/frontend/invoice_preparation.php?withdrawal_no=' . rawurlencode($no));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('danger', $e instanceof PDOException ? 'Unable to save invoice draft. Please check the database connection.' : $e->getMessage());
    header('Location: ' . APP_BASE_URL . '/frontend/invoice_preparation.php?withdrawal_no=' . rawurlencode($no));
    exit;
}
