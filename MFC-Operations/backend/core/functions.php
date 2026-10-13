<?php

declare(strict_types=1);
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function current_page(): string
{
    return basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
}
function nav_active(string $page): string
{
    return current_page() === $page ? 'active' : '';
}
function status_class(string $status): string
{
    $s = strtolower($status);
    if (str_contains($s, 'invoiced') || str_contains($s, 'completed')) return 'status-success';
    if (str_contains($s, 'error') || str_contains($s, 'uncertain')) return 'status-danger';
    if (str_contains($s, 'processing') || str_contains($s, 'queued')) return 'status-info';
    if (str_contains($s, 'ready')) return 'status-teal';
    return 'status-muted';
}
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function verify_csrf(): void
{
    if (!hash_equals($_SESSION['_csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        exit('Your session token expired. Refresh the page and try again.');
    }
}
function flash(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}
function take_flash(): ?array
{
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f;
}
function log_activity(PDO $pdo, string $action, string $entity, ?int $entityId, string $details = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs(employee_id,action,entity_type,entity_id,details,ip_address,created_at) VALUES(?,?,?,?,?,?,NOW())');
    $stmt->execute([$_SESSION['user']['id'] ?? null, $action, $entity, $entityId, $details, substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);
}
function next_withdrawal_no(PDO $pdo): string
{
    $prefix = date('Y-');
    $stmt = $pdo->prepare("SELECT withdrawal_no FROM withdrawals WHERE withdrawal_no LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = $last ? ((int)substr((string)$last, 5)) + 1 : 101001;
    return $prefix . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
}
function get_sites(PDO $pdo): array
{
    return $pdo->query('SELECT id,site_name FROM sites WHERE active=1 ORDER BY site_name')->fetchAll();
}
function get_products(PDO $pdo): array
{
    return $pdo->query('SELECT id,product_name,item_code,description,u_m,default_site_id,default_price,kg_per_box FROM products WHERE active=1 ORDER BY product_name')->fetchAll();
}
