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
$no = trim($_POST['withdrawal_no'] ?? '');
$clientId = (int)($_POST['client_id'] ?? 0);
$date = trim($_POST['form_date'] ?? '');
$received = trim($_POST['time_received'] ?? '');
$pickup = trim($_POST['pick_up_time'] ?? '');
$po = trim($_POST['po_no'] ?? '');
$note = trim($_POST['internal_note'] ?? '');
$postedItems = $_POST['items'] ?? [];
try {
    if ($no === '' || $clientId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new RuntimeException('Withdrawal number, client, and a valid form date are required.');
    $c = $pdo->prepare('SELECT id FROM clients WHERE id=? AND active=1');
    $c->execute([$clientId]);
    if (!$c->fetch()) throw new RuntimeException('Please select a valid active client.');
    $valid = [];
    foreach ($postedItems as $idx => $it) {
        $name = trim((string)($it['product_name'] ?? ''));
        $qty = trim((string)($it['quantity'] ?? ''));
        $remarks = trim((string)($it['remarks'] ?? ''));
        if ($name === '' && $qty === '' && $remarks === '') continue;
        if ($name === '' || $qty === '' || !is_numeric($qty) || (float)$qty <= 0) throw new RuntimeException('Every item row must have a product and a quantity greater than zero.');
        $ps = $pdo->prepare('SELECT id,product_name FROM products WHERE active=1 AND product_name=? LIMIT 1');
        $ps->execute([$name]);
        $product = $ps->fetch();
        if (!$product) throw new RuntimeException('Product "' . $name . '" is not in the active product list. Add it to Products & Sites first.');
        $valid[] = ['product' => $product, 'quantity' => (float)$qty, 'remarks' => $remarks];
        if (count($valid) > 30) throw new RuntimeException('A withdrawal may contain no more than 30 item lines.');
    }
    if (!$valid) throw new RuntimeException('Enter at least one product before saving.');
    $pdo->beginTransaction();
    $id = null;
    $find = $pdo->prepare('SELECT id,status FROM withdrawals WHERE withdrawal_no=? FOR UPDATE');
    $find->execute([$no]);
    $old = $find->fetch();
    if ($old) {
        $id = (int)$old['id'];
        if ($old['status'] === 'Invoiced') throw new RuntimeException('This withdrawal is already invoiced and cannot be edited from the normal withdrawal form.');
        $s = $pdo->prepare("UPDATE withdrawals SET time_received=?,pick_up_time=?,client_id=?,form_date=?,po_no=?,internal_note=?,updated_by=?,status=IF(status='Draft','Ready for Invoicing',status) WHERE id=?");
        $s->execute([$received ?: null, $pickup ?: null, $clientId, $date, $po ?: null, $note ?: null, $_SESSION['user']['id'], $id]);
        $pdo->prepare('DELETE FROM withdrawal_items WHERE withdrawal_id=?')->execute([$id]);
        $action = 'UPDATE';
    } else {
        $s = $pdo->prepare('INSERT INTO withdrawals(withdrawal_no,time_received,pick_up_time,client_id,form_date,po_no,internal_note,status,created_by,updated_by) VALUES(?,?,?,?,?,?,?,"Ready for Invoicing",?,?)');
        $s->execute([$no, $received ?: null, $pickup ?: null, $clientId, $date, $po ?: null, $note ?: null, $_SESSION['user']['id'], $_SESSION['user']['id']]);
        $id = (int)$pdo->lastInsertId();
        $action = 'CREATE';
    }
    $ins = $pdo->prepare('INSERT INTO withdrawal_items(withdrawal_id,line_no,product_id,product_name,quantity,remarks) VALUES(?,?,?,?,?,?)');
    foreach ($valid as $i => $it) $ins->execute([$id, $i + 1, $it['product']['id'], $it['product']['product_name'], $it['quantity'], $it['remarks'] ?: null]);
    log_activity($pdo, $action, 'withdrawal', $id, 'Saved withdrawal ' . $no . ' with ' . count($valid) . ' line(s)');
    $pdo->commit();
    flash('success', 'Withdrawal ' . $no . ' was saved successfully.');
    header('Location: ' . APP_BASE_URL . '/frontend/withdrawal_form.php?withdrawal_no=' . rawurlencode($no));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('danger', $e instanceof PDOException ? 'The withdrawal could not be saved. Check the database connection and constraints.' : $e->getMessage());
    header('Location: ' . APP_BASE_URL . '/frontend/withdrawal_form.php' . ($no !== '' ? '?withdrawal_no=' . rawurlencode($no) : ''));
    exit;
}
