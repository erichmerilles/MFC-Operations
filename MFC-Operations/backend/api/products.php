<?php
require_once __DIR__ . '/../config/app.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
$q = trim($_GET['q'] ?? '');
$stmt = db()->prepare("SELECT id,product_name,item_code,description,u_m,default_site_id,default_price,kg_per_box FROM products WHERE active=1 AND (? = '' OR product_name LIKE ? OR item_code LIKE ?) ORDER BY product_name LIMIT 30");
$like = '%' . $q . '%';
$stmt->execute([$q, $like, $like]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
