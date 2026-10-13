<?php

declare(strict_types=1);
$page_title = 'Withdrawal Form';
require __DIR__ . '/includes/layout_start.php';
$pdo = db();
$no = trim($_GET['withdrawal_no'] ?? '');
$record = null;
$items = [];
if ($no !== '') {
    $s = $pdo->prepare('SELECT w.*,c.client_name FROM withdrawals w JOIN clients c ON c.id=w.client_id WHERE w.withdrawal_no=?');
    $s->execute([$no]);
    $record = $s->fetch();
    if (!$record) {
        flash('warning', 'Withdrawal not found. A new form is ready.');
        header('Location: ' . APP_BASE_URL . '/frontend/withdrawal_form.php');
        exit;
    }
    $s = $pdo->prepare('SELECT * FROM withdrawal_items WHERE withdrawal_id=? ORDER BY line_no');
    $s->execute([$record['id']]);
    $items = $s->fetchAll();
}
$clients = $pdo->query('SELECT id,client_name FROM clients WHERE active=1 ORDER BY client_name')->fetchAll();
$products = get_products($pdo);
$nextNo = $record['withdrawal_no'] ?? next_withdrawal_no($pdo);
$values = $items ?: array_fill(0, 5, ['product_id' => '', 'product_name' => '', 'quantity' => '', 'remarks' => '']);
while (count($values) < 30) $values[] = ['product_id' => '', 'product_name' => '', 'quantity' => '', 'remarks' => ''];
?>
<div class="page-intro">
    <div>
        <h2><?= $record ? 'Edit Withdrawal' : 'Create Withdrawal' ?></h2>
        <p>Withdrawal data is the source record. Invoice-only edits are saved separately.</p>
    </div>
    <div class="toolbar"><a href="withdrawals.php" class="btn btn-light"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to register</a><?php if ($record): ?><a href="withdrawal_print.php?withdrawal_no=<?= e($nextNo) ?>" target="_blank" class="btn btn-outline-secondary">Print form</a><?php endif; ?></div>
</div>
<form method="post" action="<?= e(APP_BASE_URL) ?>/backend/controllers/withdrawal_save.php" id="withdrawalForm"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="withdrawal_no" value="<?= e($nextNo) ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Withdrawal Details</h3>
                <p>Header information for the original withdrawal record</p>
            </div><span class="status-pill <?= e(status_class($record['status'] ?? 'Draft')) ?>"><?= e($record['status'] ?? 'New draft') ?></span>
        </div>
        <div class="panel-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Withdrawal No.</label><input class="form-control" value="<?= e($nextNo) ?>" readonly aria-readonly="true"></div>
                <div class="col-md-4"><label class="form-label">Form Date <span class="required-mark">*</span></label><input class="form-control" type="date" name="form_date" value="<?= e($record['form_date'] ?? date('Y-m-d')) ?>" required></div>
                <div class="col-md-4"><label class="form-label">Client Name <span class="required-mark">*</span></label><select class="form-select" name="client_id" required>
                        <option value="">Select client</option><?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= ((string)($record['client_id'] ?? '') === (string)$c['id'] ? 'selected' : '') ?>><?= e($c['client_name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-3"><label class="form-label">Time Received</label><input class="form-control" type="time" name="time_received" value="<?= e(substr((string)($record['time_received'] ?? ''), 0, 5)) ?>"></div>
                <div class="col-md-3"><label class="form-label">Pick Up Time</label><input class="form-control" type="time" name="pick_up_time" value="<?= e(substr((string)($record['pick_up_time'] ?? ''), 0, 5)) ?>"></div>
                <div class="col-md-6"><label class="form-label">PO No.</label><input class="form-control" name="po_no" value="<?= e($record['po_no'] ?? '') ?>" placeholder="Enter purchase order number"></div>
            </div>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Withdrawal Items</h3>
                <p>Enter products and quantities. Blank rows are ignored.</p>
            </div><button type="button" class="btn btn-light" id="addLine"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add line</button>
        </div>
        <div class="table-responsive">
            <table class="table" id="itemsTable" data-pagination-exempt>
                <thead>
                    <tr>
                        <th style="width:65px">Line No.</th>
                        <th>Product</th>
                        <th style="width:150px">Quantity</th>
                        <th>Remarks</th>
                        <th style="width:70px">Remove</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($values as $i => $it): ?><tr>
                            <td class="line-no"><?= $i + 1 ?></td>
                            <td><input class="form-control table-input product-name" name="items[<?= $i ?>][product_name]" list="productList" value="<?= e($it['product_name'] ?? '') ?>" placeholder="Type product name" autocomplete="off"><input type="hidden" name="items[<?= $i ?>][product_id]" class="product-id" value="<?= e($it['product_id'] ?? '') ?>"></td>
                            <td><input class="form-control table-input" name="items[<?= $i ?>][quantity]" type="number" min="0.001" step="0.001" value="<?= e($it['quantity'] ?? '') ?>" placeholder="Qty"></td>
                            <td><input class="form-control table-input" name="items[<?= $i ?>][remarks]" value="<?= e($it['remarks'] ?? '') ?>" placeholder="Optional remarks"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-line" aria-label="Remove item line" title="Remove item line"><i class="bi bi-trash3" aria-hidden="true"></i></button></td>
                        </tr><?php endforeach; ?>
                </tbody>
            </table>
        </div><datalist id="productList"><?php foreach ($products as $pr): ?><option value="<?= e($pr['product_name']) ?>"><?php endforeach; ?></datalist>
        <div class="panel-body"><label class="form-label">Internal note (optional)</label><textarea class="form-control" rows="2" name="internal_note" placeholder="Notes about this withdrawal"><?= e($record['internal_note'] ?? '') ?></textarea></div>
    </section>
    <div class="page-bottom-actions"><span class="muted text-small">Changes are saved to the source withdrawal only when you click Save.</span>
        <div class="right"><a href="withdrawals.php" class="btn btn-light">Cancel</a><button class="btn btn-primary" type="submit">Save Withdrawal</button><?php if ($record): ?><a href="invoice_preparation.php?withdrawal_no=<?= e($nextNo) ?>" class="btn btn-teal">Prepare Invoice <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endif; ?></div>
    </div>
</form>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tbody = document.querySelector('#itemsTable tbody');

        function renumber() {
            [...tbody.rows].forEach((r, i) => {
                r.querySelector('.line-no').textContent = i + 1;
                r.querySelectorAll('input').forEach(el => el.name = el.name.replace(/items\[\d+\]/, 'items[' + i + ']'));
            });
        }
        document.querySelector('#addLine').addEventListener('click', () => {
            if (tbody.rows.length >= 30) {
                Swal.fire({
                    icon: 'info',
                    title: 'Line limit reached',
                    text: 'A withdrawal can contain up to 30 item lines.'
                });
                return;
            }
            let r = tbody.rows[tbody.rows.length - 1].cloneNode(true);
            r.querySelectorAll('input').forEach(el => el.value = '');
            tbody.appendChild(r);
            renumber();
        });
        tbody.addEventListener('click', async e => {
            const button = e.target.closest('.remove-line');
            if (!button) return;
            const row = button.closest('tr');
            const hasProduct = row.querySelector('.product-name')?.value.trim() !== '';
            if (hasProduct) {
                const result = await Swal.fire({
                    title: 'Remove this item line?',
                    text: 'The line will be removed from this form.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Remove line',
                    cancelButtonText: 'Keep line',
                    confirmButtonColor: '#b42318',
                    reverseButtons: true
                });
                if (!result.isConfirmed) return;
            }
            if (tbody.rows.length > 1) {
                row.remove();
                renumber();
            } else {
                row.querySelectorAll('input').forEach(el => el.value = '');
            }
        });
        tbody.addEventListener('change', e => {
            if (e.target.classList.contains('product-name')) {
                let opt = [...document.querySelectorAll('#productList option')].find(o => o.value === e.target.value);
                let row = e.target.closest('tr');
                let p = [...document.querySelectorAll('#productList option')].find(o => o.value === e.target.value);
                row.querySelector('.product-id').value = '';
            }
        });
    });
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
