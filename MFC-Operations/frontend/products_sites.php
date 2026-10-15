<?php

//declare(strict_types=1);
$page_title = 'Products & Sites';
require_once __DIR__ . '/../backend/config/app.php';
require_login();
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'Admin';
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
        } elseif ($action === 'update_product') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['product_name'] ?? '');
            if ($id < 1 || $name === '') throw new RuntimeException('Select a product and enter its name.');
            $site = trim($_POST['default_site_id'] ?? '');
            if ($site !== '') {
                $siteCheck = $pdo->prepare('SELECT id FROM sites WHERE id=?');
                $siteCheck->execute([(int)$site]);
                if (!$siteCheck->fetch()) throw new RuntimeException('Select a valid default site.');
            }
            $price = trim($_POST['default_price'] ?? '');
            $kg = trim($_POST['kg_per_box'] ?? '');
            if (($price !== '' && (!is_numeric($price) || (float)$price < 0)) || ($kg !== '' && (!is_numeric($kg) || (float)$kg < 0))) {
                throw new RuntimeException('Price and weight must be zero or greater.');
            }
            $active = ($_POST['active'] ?? '0') === '1' ? 1 : 0;
            $s = $pdo->prepare('UPDATE products SET product_name=?,item_code=?,description=?,u_m=?,default_site_id=?,default_price=?,kg_per_box=?,active=? WHERE id=?');
            $s->execute([$name, trim($_POST['item_code'] ?? '') ?: null, trim($_POST['description'] ?? '') ?: null, trim($_POST['u_m'] ?? '') ?: null, $site !== '' ? (int)$site : null, $price !== '' ? (float)$price : null, $kg !== '' ? (float)$kg : null, $active, $id]);
            log_activity($pdo, 'UPDATE', 'product', $id, 'Updated product ' . $name);
            flash('success', 'Product changes saved.');
        } elseif ($action === 'update_site') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['site_name'] ?? '');
            if ($id < 1 || $name === '') throw new RuntimeException('Select a site and enter its name.');
            $active = ($_POST['active'] ?? '0') === '1' ? 1 : 0;
            $s = $pdo->prepare('UPDATE sites SET site_name=?,active=? WHERE id=?');
            $s->execute([$name, $active, $id]);
            log_activity($pdo, 'UPDATE', 'site', $id, 'Updated site ' . $name);
            flash('success', 'Site changes saved.');
        } elseif ($action === 'update_client') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['client_name'] ?? '');
            if ($id < 1 || $name === '') throw new RuntimeException('Select a client and enter its name.');
            $active = ($_POST['active'] ?? '0') === '1' ? 1 : 0;
            $s = $pdo->prepare('UPDATE clients SET client_name=?,active=? WHERE id=?');
            $s->execute([$name, $active, $id]);
            log_activity($pdo, 'UPDATE', 'client', $id, 'Updated client ' . $name);
            flash('success', 'Client changes saved.');
        }
        header('Location: products_sites.php');
        exit;
    } catch (Throwable $e) {
        $err = $e instanceof PDOException ? 'Could not save. Check whether the name already exists.' : $e->getMessage();
    }
}
$products = $isAdmin
    ? $pdo->query('SELECT id,product_name,item_code,description,u_m,default_site_id,default_price,kg_per_box,active FROM products ORDER BY product_name')->fetchAll()
    : get_products($pdo);
$sites = $isAdmin
    ? $pdo->query('SELECT id,site_name,active FROM sites ORDER BY site_name')->fetchAll()
    : get_sites($pdo);
$clients = $isAdmin
    ? $pdo->query('SELECT id,client_name,active FROM clients ORDER BY client_name')->fetchAll()
    : $pdo->query('SELECT id,client_name,active FROM clients WHERE active=1 ORDER BY client_name')->fetchAll();
$activeClientCount = count(array_filter($clients, static fn($client) => (int)$client['active'] === 1));
require __DIR__ . '/includes/layout_start.php'; ?>
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
                                    <option value="">None</option><?php foreach ($sites as $site): if ((int)($site['active'] ?? 1) === 1): ?><option value="<?= $site['id'] ?>"><?= e($site['site_name']) ?></option><?php endif;
                                                                                                                                                                                                                endforeach; ?>
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
            <p><?= $isAdmin ? count($products) . ' product(s), including inactive entries' : count($products) . ' active product(s)' ?></p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table <?= $isAdmin ? 'admin-edit-table' : '' ?>">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th>U/M</th>
                    <th>Default Site</th>
                    <th>Default Price</th>
                    <th>KG/Box</th>
                    <?php if ($isAdmin): ?><th>Status</th>
                        <th>Save</th><?php endif; ?>
                </tr>
            </thead>
            <tbody><?php foreach ($products as $pr): ?><tr>
                        <?php if ($isAdmin): $formId = 'product-edit-' . (int)$pr['id']; ?>
                            <td><input class="form-control table-input" form="<?= e($formId) ?>" name="product_name" value="<?= e($pr['product_name']) ?>" aria-label="Product name" required></td>
                            <td><input class="form-control table-input" form="<?= e($formId) ?>" name="item_code" value="<?= e($pr['item_code'] ?? '') ?>" aria-label="Item code"></td>
                            <td><input class="form-control table-input description" form="<?= e($formId) ?>" name="description" value="<?= e($pr['description'] ?? '') ?>" aria-label="Description"></td>
                            <td><input class="form-control table-input" form="<?= e($formId) ?>" name="u_m" value="<?= e($pr['u_m'] ?? '') ?>" aria-label="Unit of measure"></td>
                            <td><select class="form-select table-input" form="<?= e($formId) ?>" name="default_site_id" aria-label="Default site">
                                    <option value="">None</option><?php foreach ($sites as $site): ?><option value="<?= (int)$site['id'] ?>" <?= (string)$pr['default_site_id'] === (string)$site['id'] ? 'selected' : '' ?>><?= e($site['site_name']) ?><?= empty($site['active']) ? ' (Inactive)' : '' ?></option><?php endforeach; ?>
                                </select></td>
                            <td><input class="form-control table-input" form="<?= e($formId) ?>" name="default_price" type="number" min="0" step="0.01" value="<?= e($pr['default_price'] ?? '') ?>" aria-label="Default price"></td>
                            <td><input class="form-control table-input" form="<?= e($formId) ?>" name="kg_per_box" type="number" min="0" step="0.001" value="<?= e($pr['kg_per_box'] ?? '') ?>" aria-label="Kilograms per box"></td>
                            <td><select class="form-select table-input" form="<?= e($formId) ?>" name="active" aria-label="Product status">
                                    <option value="1" <?= (int)$pr['active'] === 1 ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= (int)$pr['active'] !== 1 ? 'selected' : '' ?>>Inactive</option>
                                </select></td>
                            <td>
                                <form id="<?= e($formId) ?>" method="post" data-confirm-submit="Save these product changes?" data-confirm-title="Update product?" data-confirm-button="Save changes"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_product"><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>"><button class="btn btn-primary btn-sm" type="submit">Save</button></form>
                            </td>
                        <?php else: ?>
                            <td><strong><?= e($pr['product_name']) ?></strong></td>
                            <td><?= e($pr['item_code'] ?? '') ?></td>
                            <td><?= e($pr['description'] ?? '') ?></td>
                            <td><?= e($pr['u_m'] ?? '') ?></td>
                            <td><?php $siteName = '';
                                foreach ($sites as $si) if ((string)$si['id'] === (string)$pr['default_site_id']) $siteName = $si['site_name'];
                                echo e($siteName); ?></td>
                            <td><?= $pr['default_price'] !== null ? 'PHP ' . number_format((float)$pr['default_price'], 2) : '-' ?></td>
                            <td><?= $pr['kg_per_box'] !== null ? number_format((float)$pr['kg_per_box'], 3) : '-' ?></td>
                        <?php endif; ?>
                    </tr><?php endforeach; ?><?php if (!$products): ?><tr>
                        <td colspan="<?= $isAdmin ? '9' : '7' ?>">No products to display.</td>
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
                    <p><?= $isAdmin ? count($sites) . ' site(s), including inactive entries' : 'Only active sites can be selected for invoice lines.' ?></p>
                </div>
            </div>
            <div class="panel-body <?= $isAdmin ? 'admin-list-panel-body' : '' ?>">
                <?php foreach ($sites as $site): ?>
                    <?php if ($isAdmin): ?><form class="setting-row admin-list-edit" method="post" data-confirm-submit="Save these site changes?" data-confirm-title="Update site?" data-confirm-button="Save changes"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_site"><input type="hidden" name="id" value="<?= (int)$site['id'] ?>"><label class="visually-hidden" for="site-<?= (int)$site['id'] ?>">Site name</label><input class="form-control" id="site-<?= (int)$site['id'] ?>" name="site_name" value="<?= e($site['site_name']) ?>" required><select class="form-select" name="active" aria-label="Site status">
                                <option value="1" <?= (int)$site['active'] === 1 ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= (int)$site['active'] !== 1 ? 'selected' : '' ?>>Inactive</option>
                            </select><button class="btn btn-primary btn-sm" type="submit">Save</button></form>
                    <?php else: ?><div class="setting-row"><strong><?= e($site['site_name']) ?></strong><span class="status-pill status-success">Active</span></div><?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$sites): ?><div class="empty-state">No sites to display.</div><?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Clients</h3>
                    <p><?= $isAdmin ? count($clients) . ' client(s), ' . $activeClientCount . ' active' : count($clients) . ' active client(s)' ?></p>
                </div>
            </div>
            <div class="panel-body <?= $isAdmin ? 'admin-list-panel-body' : '' ?>">
                <?php foreach ($clients as $client): ?>
                    <?php if ($isAdmin): ?><form class="setting-row admin-list-edit" method="post" data-confirm-submit="Save these client changes?" data-confirm-title="Update client?" data-confirm-button="Save changes"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_client"><input type="hidden" name="id" value="<?= (int)$client['id'] ?>"><label class="visually-hidden" for="client-<?= (int)$client['id'] ?>">Client name</label><input class="form-control" id="client-<?= (int)$client['id'] ?>" name="client_name" value="<?= e($client['client_name']) ?>" required><select class="form-select" name="active" aria-label="Client status">
                                <option value="1" <?= (int)$client['active'] === 1 ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= (int)$client['active'] !== 1 ? 'selected' : '' ?>>Inactive</option>
                            </select><button class="btn btn-primary btn-sm" type="submit">Save</button></form>
                    <?php else: ?><div class="setting-row"><strong><?= e($client['client_name']) ?></strong><span class="status-pill status-success">Active</span></div><?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$clients): ?><div class="empty-state">No clients to display.</div><?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>