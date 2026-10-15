<?php

//declare(strict_types=1);
$page_title = 'Invoice Preparation';
require __DIR__ . '/includes/layout_start.php';

$pdo = db();
$no = trim($_GET['withdrawal_no'] ?? '');

$s = $pdo->prepare(
    'SELECT w.*, c.client_name
     FROM withdrawals w
     JOIN clients c ON c.id = w.client_id
     WHERE w.withdrawal_no = ?'
);
$s->execute([$no]);
$w = $s->fetch();

if (!$w) {
    echo '<div class="alert alert-warning">Select a saved withdrawal before preparing an invoice.</div>
      <a class="btn btn-primary mb-3" href="withdrawals.php">Choose withdrawal</a>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$s = $pdo->prepare('SELECT * FROM invoices WHERE withdrawal_id = ?');
$s->execute([$w['id']]);
$inv = $s->fetch();

if (!$inv) {
    $pdo->beginTransaction();

    $s = $pdo->prepare(
        'INSERT INTO invoices
         (withdrawal_id, customer_id, invoice_date, po_no, status, created_by, updated_by)
         VALUES (?, ?, ?, ?, "Draft", ?, ?)'
    );

    $s->execute([
        $w['id'],
        $w['client_id'],
        $w['form_date'],
        $w['po_no'],
        $_SESSION['user']['id'],
        $_SESSION['user']['id']
    ]);

    $iid = (int) $pdo->lastInsertId();

    $s = $pdo->prepare(
        'SELECT wi.*, p.item_code, p.description, p.u_m,
                p.default_price, p.default_site_id
         FROM withdrawal_items wi
         LEFT JOIN products p ON p.id = wi.product_id
         WHERE wi.withdrawal_id = ?
         ORDER BY wi.line_no'
    );

    $s->execute([$w['id']]);
    $copy = $s->fetchAll();

    $ins = $pdo->prepare(
        'INSERT INTO invoice_items
         (invoice_id, withdrawal_item_id, source_type, product_id, site_id,
          product_name, quantity, item_code, description, u_m, price_each,
          amount, created_by, updated_by)
         VALUES (?, ?, "WITHDRAWAL", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($copy as $it) {
        $price = (float) ($it['default_price'] ?? 0);

        $ins->execute([
            $iid,
            $it['id'],
            $it['product_id'],
            $it['default_site_id'],
            $it['product_name'],
            $it['quantity'],
            $it['item_code'],
            $it['description'],
            $it['u_m'],
            $price,
            $price * (float) $it['quantity'],
            $_SESSION['user']['id'],
            $_SESSION['user']['id']
        ]);
    }

    $pdo->prepare(
        'UPDATE withdrawals SET status = ?, updated_by = ? WHERE id = ?'
    )->execute([
        'Invoice Preparation',
        $_SESSION['user']['id'],
        $w['id']
    ]);

    $pdo->commit();

    $s = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $s->execute([$iid]);
    $inv = $s->fetch();
}

$s = $pdo->prepare(
    'SELECT ii.*, si.site_name
     FROM invoice_items ii
     LEFT JOIN sites si ON si.id = ii.site_id
     WHERE ii.invoice_id = ?
     ORDER BY ii.id'
);
$s->execute([$inv['id']]);
$lines = $s->fetchAll();

$products = get_products($pdo);
$sites = get_sites($pdo);
$locked = $inv['status'] === 'Invoiced';

$sum = 0;
foreach ($lines as $ln) {
    $sum += (float) $ln['amount'];
}
?>

<div class="page-intro">
    <div>
        <h2>Prepare QuickBooks Invoice</h2>
        <p>
            Invoice draft for withdrawal <?= e($no) ?>.
            Invoice edits never overwrite the original withdrawal.
        </p>
    </div>

    <div class="toolbar">
        <a href="withdrawals.php" class="btn btn-light">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Select withdrawal
        </a>
    </div>
</div>

<div class="notice warning">
    <strong>Data separation:</strong>
    additional invoice items are stored only in invoice_items.
    QuickBooks submission is not enabled in Phase 2.
</div>

<form
    method="post"
    action="<?= e(APP_BASE_URL) ?>/backend/controllers/invoice_save.php"
    id="invoiceForm">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="invoice_id" value="<?= (int) $inv['id'] ?>">
    <input type="hidden" name="withdrawal_no" value="<?= e($no) ?>">

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Invoice Header</h3>
                <p>Source withdrawal and invoice details</p>
            </div>

            <span class="status-pill <?= e(status_class($inv['status'])) ?>">
                <?= e($inv['status']) ?>
            </span>
        </div>

        <div class="panel-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Source Withdrawal No.</label>
                    <input class="form-control" value="<?= e($no) ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Customer</label>
                    <input class="form-control" value="<?= e($w['client_name']) ?>" readonly>
                    <input
                        type="hidden"
                        name="customer_id"
                        value="<?= (int) $w['client_id'] ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Invoice Date <span class="required-mark">*</span>
                    </label>
                    <input
                        class="form-control"
                        type="date"
                        name="invoice_date"
                        value="<?= e($inv['invoice_date']) ?>"
                        required
                        <?= $locked ? 'readonly' : '' ?>>
                </div>

                <div class="col-md-3">
                    <label class="form-label">PO No.</label>
                    <input
                        class="form-control"
                        name="po_no"
                        value="<?= e($inv['po_no'] ?? '') ?>"
                        <?= $locked ? 'readonly' : '' ?>>
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Invoice Lines</h3>
                <p>Site, quantity, optional lot/expiration, item details, price and tax.</p>
            </div>

            <?php if (!$locked): ?>
                <div class="toolbar">
                    <button
                        type="button"
                        class="btn btn-light"
                        id="addInvoiceLine">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        Add invoice-only item
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table" id="invoiceLines" data-pagination-exempt>
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Product</th>
                        <th>Site</th>
                        <th>Qty</th>
                        <th>Lot No.</th>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>U/M</th>
                        <th>Price Each</th>
                        <th>Amount</th>
                        <th>Tax</th>
                        <th>Expiration</th>
                        <?php if (!$locked): ?>
                            <th class="text-center">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($lines as $i => $ln): ?>
                        <tr>
                            <td>
                                <span class="status-pill <?= $ln['source_type'] === 'ADDITIONAL' ? 'status-info' : 'status-muted' ?>">
                                    <?= e(ucfirst(strtolower($ln['source_type']))) ?>
                                </span>

                                <input
                                    type="hidden"
                                    name="items[<?= $i ?>][id]"
                                    value="<?= (int) $ln['id'] ?>">
                                <input
                                    type="hidden"
                                    name="items[<?= $i ?>][source_type]"
                                    value="<?= e($ln['source_type']) ?>">
                                <input
                                    type="hidden"
                                    name="items[<?= $i ?>][withdrawal_item_id]"
                                    value="<?= e($ln['withdrawal_item_id'] ?? '') ?>">
                            </td>

                            <td>
                                <input
                                    class="form-control table-input"
                                    name="items[<?= $i ?>][product_name]"
                                    list="invoiceProductList"
                                    value="<?= e($ln['product_name']) ?>"
                                    required
                                    <?= $locked ? 'readonly' : '' ?>>
                            </td>

                            <td>
                                <select
                                    class="form-select table-input"
                                    name="items[<?= $i ?>][site_id]"
                                    required
                                    <?= $locked ? 'disabled' : '' ?>>
                                    <option value="">Select site</option>
                                    <?php foreach ($sites as $site): ?>
                                        <option
                                            value="<?= $site['id'] ?>"
                                            <?= ((string) $ln['site_id'] === (string) $site['id'] ? 'selected' : '') ?>>
                                            <?= e($site['site_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <td>
                                <input
                                    class="form-control table-input invoice-qty"
                                    type="number"
                                    min="0.001"
                                    step="0.001"
                                    name="items[<?= $i ?>][quantity]"
                                    value="<?= e($ln['quantity']) ?>"
                                    required
                                    <?= $locked ? 'readonly' : '' ?>>
                            </td>

                            <td>
                                <input
                                    class="form-control table-input"
                                    name="items[<?= $i ?>][lot_number]"
                                    value="<?= e($ln['lot_number'] ?? '') ?>"
                                    <?= $locked ? 'readonly' : '' ?>>
                            </td>

                            <td>
                                <input
                                    class="form-control table-input"
                                    name="items[<?= $i ?>][item_code]"
                                    value="<?= e($ln['item_code'] ?? '') ?>"
                                    <?= $locked ? 'readonly' : '' ?>>
                            </td>

                            <td>
                                <input
                                    class="form-control table-input"
                                    name="items[<?= $i ?>][description]"
                                    value="<?= e($ln['description'] ?? '') ?>"
                                    <?= $locked ? 'readonly' : '' ?>>
                            </td>

                            <td>
                                <input
                                    class="form-control table-input"
                                    name="items[<?= $i ?>][u_m]"
                                    value="<?= e($ln['u_m'] ?? '') ?>"
                                    <?= $locked ? 'readonly' : '' ?>>
                            </td>

                            <td>
                                <input
                                    class="form-control table-input invoice-price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    name="items[<?= $i ?>][price_each]"
                                    value="<?= e($ln['price_each']) ?>"
                                    required
                                    <?= $locked ? 'readonly' : '' ?>>
                            </td>

                            <td>
                                <input
                                    class="form-control table-input amount"
                                    value="<?= number_format((float) $ln['quantity'] * (float) $ln['price_each'], 2, '.', '') ?>"
                                    readonly>
                            </td>

                            <td>
                                <select
                                    class="form-select table-input"
                                    name="items[<?= $i ?>][tax_code]"
                                    <?= $locked ? 'disabled' : '' ?>>
                                    <?php foreach (['Non', 'Tax', 'Exempt'] as $tax): ?>
                                        <option <?= ($ln['tax_code'] === $tax ? 'selected' : '') ?>>
                                            <?= e($tax) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <td>
                                <input
                                    class="form-control table-input"
                                    type="date"
                                    name="items[<?= $i ?>][expiration_date]"
                                    value="<?= e($ln['expiration_date'] ?? '') ?>"
                                    <?= $locked ? 'readonly' : '' ?>>
                            </td>

                            <?php if (!$locked): ?>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger remove-invoice-line"
                                        aria-label="Remove invoice item"
                                        title="Remove invoice item">
                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <datalist id="invoiceProductList">
            <?php foreach ($products as $pr): ?>
                <option value="<?= e($pr['product_name']) ?>">
                <?php endforeach; ?>
        </datalist>

        <div class="panel-body d-flex justify-content-end">
            <div class="total-card">
                <div class="total-row">
                    <span>Invoice total</span>
                    <strong>
                        &#8369;
                        <span data-invoice-total><?= number_format($sum, 2) ?></span>
                    </strong>
                </div>

                <div class="form-text mt-2">
                    Tax treatment is stored as the selected tax code;
                    actual tax calculation follows QuickBooks configuration.
                </div>
            </div>
        </div>
    </section>

    <div class="page-bottom-actions">
        <span class="muted text-small">
            Status: <?= e($inv['status']) ?>.
            No invoice has been sent to QuickBooks.
        </span>

        <div class="right">
            <a
                href="withdrawal_form.php?withdrawal_no=<?= e($no) ?>"
                class="btn btn-light">
                View source withdrawal
            </a>

            <?php if (!$locked): ?>
                <button class="btn btn-primary" type="submit">
                    Save Invoice Draft
                </button>

                <button
                    class="btn btn-teal"
                    type="button"
                    disabled
                    title="Phase 2 integration not configured">
                    Send to QuickBooks Setup Required
                </button>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tbody = document.querySelector('#invoiceLines tbody');

        function renumber() {
            [...tbody.rows].forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((el) => {
                    el.name = el.name.replace(
                        /items\[\d+\]/,
                        'items[' + index + ']'
                    );
                });
            });
        }

        document.querySelector('#addInvoiceLine')?.addEventListener('click', () => {
            const row = tbody.rows[0]?.cloneNode(true);
            if (!row) return;

            row.querySelectorAll('input').forEach((el) => {
                el.value = '';

                if (el.name.endsWith('[source_type]')) {
                    el.value = 'ADDITIONAL';
                }

                if (el.name.endsWith('[withdrawal_item_id]')) {
                    el.value = '';
                }

                if (el.name.endsWith('[id]')) {
                    el.value = '';
                }
            });

            row.querySelector('td:first-child').innerHTML =
                '<span class="status-pill status-info">Additional</span>' +
                '<input type="hidden" name="items[0][id]" value="">' +
                '<input type="hidden" name="items[0][source_type]" value="ADDITIONAL">' +
                '<input type="hidden" name="items[0][withdrawal_item_id]" value="">';

            row.querySelectorAll('select').forEach((select) => {
                select.selectedIndex = 0;
            });

            tbody.appendChild(row);
            renumber();
        });

        tbody.addEventListener('click', async (event) => {
            const button = event.target.closest('.remove-invoice-line');
            if (!button) return;

            const result = await Swal.fire({
                title: 'Remove this invoice item?',
                text: 'The item will be removed from this draft.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Remove item',
                cancelButtonText: 'Keep item',
                confirmButtonColor: '#b42318',
                reverseButtons: true
            });

            if (result.isConfirmed && tbody.rows.length > 1) {
                button.closest('tr').remove();
                renumber();
            }
        });

        document.addEventListener('change', async (event) => {
            if (!event.target.matches("#invoiceLines input[name$='[product_name]']")) {
                return;
            }

            const row = event.target.closest('tr');

            try {
                const response = await fetch(
                    '../backend/api/products.php?q=' +
                    encodeURIComponent(event.target.value)
                );

                const list = await response.json();
                const product = list.find(
                    (item) => item.product_name === event.target.value
                );

                if (!product) return;

                const item = row.querySelector("input[name$='[item_code]']");
                const description = row.querySelector("input[name$='[description]']");
                const um = row.querySelector("input[name$='[u_m]']");
                const price = row.querySelector('.invoice-price');
                const site = row.querySelector("select[name$='[site_id]']");

                if (item) item.value = product.item_code || '';
                if (description) description.value = product.description || '';
                if (um) um.value = product.u_m || '';

                if (
                    price &&
                    (!price.value || Number(price.value) === 0) &&
                    product.default_price !== null
                ) {
                    price.value = Number(product.default_price).toFixed(2);
                }

                if (site && product.default_site_id) {
                    site.value = String(product.default_site_id);
                }

                const quantity = row.querySelector('.invoice-qty');

                if (quantity) {
                    const amount = row.querySelector('.amount');

                    if (amount) {
                        amount.value = (
                            Number(quantity.value || 0) *
                            Number(price?.value || 0)
                        ).toFixed(2);
                    }
                }
            } catch (error) {
                console.error('Product lookup failed', error);
            }
        });
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>