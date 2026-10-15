<?php
$page_title = 'Withdrawal Register';
require __DIR__ . '/includes/layout_start.php';
$pdo = db();
$q = trim($_GET['q'] ?? '');
$sql = "SELECT w.id,w.withdrawal_no,w.form_date,w.status,c.client_name,COUNT(wi.id) item_count FROM withdrawals w JOIN clients c ON c.id=w.client_id LEFT JOIN withdrawal_items wi ON wi.withdrawal_id=w.id WHERE (?='' OR w.withdrawal_no LIKE ? OR c.client_name LIKE ? OR w.status LIKE ?) GROUP BY w.id ORDER BY w.id DESC";
$s = $pdo->prepare($sql);
$like = '%' . $q . '%';
$s->execute([$q, $like, $like, $like]);
$records = $s->fetchAll();
?>
<div class="page-intro">
    <div>
        <h2>Withdrawal Register</h2>
        <p>Live records stored in MySQL.</p>
    </div>

    <div class="toolbar">
        <a href="<?= e(APP_BASE_URL) ?>/backend/exports/withdrawals.php"
            class="btn btn-light">
            Export CSV
        </a>

        <a href="withdrawal_form.php" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            New Withdrawal
        </a>
    </div>
</div>
<section class="panel">
    <div class="panel-header">
        <div>
            <h3>All Withdrawals</h3>
            <p><?= count($records) ?> record(s)</p>
        </div>
        <form class="toolbar" method="get" role="search"><label class="visually-hidden" for="withdrawalSearch">Search withdrawals</label><input class="form-control" id="withdrawalSearch" name="q" value="<?= e($q) ?>" placeholder="Search number, client, or status"><button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Search</button><?php if ($q !== ''): ?><a href="withdrawals.php" class="btn btn-light">Clear search</a><?php endif; ?></form>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Withdrawal No.</th>
                    <th>Client</th>
                    <th>Form Date</th>
                    <th>Item Lines</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$records): ?><tr>
                        <td colspan="6">
                            <div class="empty-state"><strong>No withdrawals found</strong>Create a withdrawal or change your search.</div>
                        </td>
                    </tr><?php endif; ?>
                <?php foreach ($records as $r): ?><tr>
                        <td><a class="record-no" href="withdrawal_form.php?withdrawal_no=<?= e($r['withdrawal_no']) ?>"><?= e($r['withdrawal_no']) ?></a></td>
                        <td><?= e($r['client_name']) ?></td>
                        <td><?= e(date('F d, Y', strtotime($r['form_date']))) ?></td>
                        <td><?= (int)$r['item_count'] ?></td>
                        <td><span class="status-pill <?= e(status_class($r['status'])) ?>"><?= e($r['status']) ?></span></td>

                        <td>
                            <div class="row-actions">
                                <a class="btn btn-light btn-sm"
                                    href="withdrawal_form.php?withdrawal_no=<?= e($r['withdrawal_no']) ?>">
                                    Open
                                </a>

                                <a class="btn btn-outline-secondary btn-sm"
                                    href="withdrawal_print.php?withdrawal_no=<?= e($r['withdrawal_no']) ?>"
                                    target="_blank">
                                    Print
                                </a>

                                <a class="btn btn-outline-secondary btn-sm"
                                    href="invoice_preparation.php?withdrawal_no=<?= e($r['withdrawal_no']) ?>">
                                    Invoice
                                </a>
                            </div>
                        </td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section><?php require __DIR__ . '/includes/footer.php'; ?>