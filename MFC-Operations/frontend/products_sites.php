<?php

//declare(strict_types=1);
$page_title = 'Products & Sites';
require __DIR__ . '/includes/layout_start.php';
$pdo = db();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    verify_csrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'product') {
            $name = trim($_POST['product_name'] ?? '');
            if ($name === '') throw new RuntimeException('Product name is required.');
            $site = trim($_POST['default_site_id'] ?? '');
            $price = trim($_POST['default_price'] ?? '');
            $s = $pdo->prepare('INSERT INTO products(product_name,item_code,description,u_m,default_site_id,default_price,kg_per_box,active) VALUES(?,?,?,?,?,?,?,1)');
            $s->execute([$name, trim($_POST['item_code'] ?? '') ?: null, trim($_POST['description'] ?? '') ?: null, trim($_POST['u_m'] ?? '') ?: null, $site !== '' ? (int)$site : null, $price !== '' ? (float)$price : null, (trim($_POST['kg_per_box'] ?? '') !== '') ? (float)$_POST['kg_per_box'] : null]);
            log_activity($pdo, 'CREATE', 'product', (int)$pdo->lastInsertId(), 'Created product ' . $name);
            flash('success', 'Product added.');
        } elseif ($action === 'site') {
            $name = trim($_POST['site_name'] ?? '');
            if ($name === '') throw new RuntimeException('Site name is required.');
            $s = $pdo->prepare('INSERT INTO sites(site_name,active) VALUES(?,1)');
            $s->execute([$name]);
            log_activity($pdo, 'CREATE', 'site', (int)$pdo->lastInsertId(), 'Created site ' . $name);
            flash('success', 'Site added.');
        } elseif ($action === 'client') {
            $name = trim($_POST['client_name'] ?? '');
            if ($name === '') throw new RuntimeException('Client name is required.');
            $s = $pdo->prepare('INSERT INTO clients(client_name,active) VALUES(?,1)');
            $s->execute([$name]);
            log_activity($pdo, 'CREATE', 'client', (int)$pdo->lastInsertId(), 'Created client ' . $name);
            flash('success', 'Client added.');
        }
        header('Location: products_sites.php');
        exit;
    } catch (Throwable $e) {
        $err = $e instanceof PDOException ? 'Could not save. Check whether the name already exists.' : $e->getMessage();
    }
}
$products = get_products($pdo);
$sites = get_sites($pdo);
$clients = $pdo->query('SELECT * FROM clients WHERE active=1 ORDER BY client_name')->fetchAll(); ?>
<div class="page-intro">
    <div>
        <h2>Products & Sites</h2>
        <p>Maintain product master data, controlled site choices, and client records.</p>
    </div><?php if (($_SESSION['user']['role'] ?? '') === 'Admin'): ?><span class="status-pill status-teal">Administrator tools enabled</span><?php endif; ?>
</div><?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<?php if (($_SESSION['user']['role'] ?? '') === 'Admin'): ?><div class="row g-3 mb-4">
        <div class="col-lg-6">
            <section class="panel h-100">
                <div class="panel-header">
                    <div>
                        <h3>Add Product</h3>
                        <p>Exact product names are used for validated selection.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="post" data-confirm-submit="Add this product to the active product list?" data-confirm-title="Add product?" data-confirm-button="Add product"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="product"><label class="form-label">Product name *</label><input class="form-control mb-2" name="product_name" required>
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label">Item code</label><input class="form-control" name="item_code"></div>
                            <div class="col-md-6"><label class="form-label">U/M</label><input class="form-control" name="u_m" placeholder="BOX"></div>
                            <div class="col-md-4"><label class="form-label">Default price</label><input class="form-control" name="default_price" type="number" min="0" step="0.01"></div>
                            <div class="col-md-4"><label class="form-label">KG per box</label><input class="form-control" name="kg_per_box" type="number" min="0" step="0.001"></div>
                            <div class="col-md-4"><label class="form-label">Default site</label><select class="form-select" name="default_site_id">
                                    <option value="">None</option><?php foreach ($sites as $site): ?><option value="<?= $site['id'] ?>"><?= e($site['site_name']) ?></option><?php endforeach; ?>
                                </select></div>
                        </div><label class="form-label mt-2">Description</label><input class="form-control mb-3" name="description"><button class="btn btn-primary">Add product</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="panel h-100">
                <div class="panel-header">
                    <div>
                        <h3>Add Site or Client</h3>
                        <p>Sites are controlled dropdown options; clients are shared across employees.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="post" class="mb-4" data-confirm-submit="Add this site to the active site list?" data-confirm-title="Add site?" data-confirm-button="Add site"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="site"><label class="form-label">New site name</label>
                        <div class="input-group"><input class="form-control" name="site_name" required><button class="btn btn-outline-primary">Add site</button></div>
                    </form>
                    <form method="post" data-confirm-submit="Add this client to the active client list?" data-confirm-title="Add client?" data-confirm-button="Add client"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="client"><label class="form-label">New client name</label>
                        <div class="input-group"><input class="form-control" name="client_name" required><button class="btn btn-outline-primary">Add client</button></div>
                    </form>
                </div>
            </section>
        </div>
    </div><?php endif; ?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Product Master</h3>
            <p><?= count($products) ?> active product(s)</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th>U/M</th>
                    <th>Default Site</th>
                    <th>Default Price</th>
                    <th>KG/Box</th>
                </tr>
            </thead>
            <tbody><?php foreach ($products as $pr): ?><tr>
                        <td><strong><?= e($pr['product_name']) ?></strong></td>
                        <td><?= e($pr['item_code'] ?? '') ?></td>
                        <td><?= e($pr['description'] ?? '') ?></td>
                        <td><?= e($pr['u_m'] ?? '') ?></td>
                        <td><?php $siteName = '';
                            foreach ($sites as $si) if ((string)$si['id'] === (string)$pr['default_site_id']) $siteName = $si['site_name'];
                            echo e($siteName); ?></td>
                        <td><?= $pr['default_price'] !== null ? 'PHP ' . number_format((float)$pr['default_price'], 2) : '-' ?></td>
                        <td><?= $pr['kg_per_box'] !== null ? number_format((float)$pr['kg_per_box'], 3) : '-' ?></td>
                    </tr><?php endforeach; ?><?php if (!$products): ?><tr>
                        <td colspan="7">No active products yet.</td>
                    </tr><?php endif; ?></tbody>
        </table>
    </div>
</section>
<div class="row g-3">
    <div class="col-md-6">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Site List</h3>
                    <p>Only active sites can be selected for invoice lines.</p>
                </div>
            </div>
            <div class="panel-body"><?php foreach ($sites as $site): ?><div class="setting-row"><strong><?= e($site['site_name']) ?></strong><span class="status-pill status-success">Active</span></div><?php endforeach; ?></div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Clients</h3>
                    <p><?= count($clients) ?> active client(s)</p>
                </div>
            </div>
            <div class="panel-body"><?php foreach ($clients as $client): ?><div class="setting-row"><strong><?= e($client['client_name']) ?></strong><span class="status-pill status-success">Active</span></div><?php endforeach; ?></div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>