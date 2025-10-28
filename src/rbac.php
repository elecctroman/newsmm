<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const RBAC_PERMISSION_CACHE_TTL = 120;

function ensureRbacInfrastructure(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    try {
        $pdo->query('SELECT 1 FROM roles LIMIT 1');
        $pdo->query('SELECT 1 FROM permissions LIMIT 1');
        $pdo->query('SELECT 1 FROM role_permissions LIMIT 1');
        $pdo->query('SELECT 1 FROM admin_user_roles LIMIT 1');
        $ensured = true;
    } catch (Throwable $e) {
        // tables might not exist on legacy installs; nothing else to do
        $ensured = false;
    }
}

function loadUserRoleSlugs(PDO $pdo, int $userId): array
{
    ensureRbacInfrastructure($pdo);

    try {
        $stmt = $pdo->prepare('SELECT r.slug FROM roles r INNER JOIN admin_user_roles aur ON aur.role_id = r.id WHERE aur.admin_user_id = :id');
        $stmt->execute(['id' => $userId]);
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (!$roles) {
            $fallback = $pdo->prepare('SELECT role FROM admin_users WHERE id = :id');
            $fallback->execute(['id' => $userId]);
            $legacyRole = (string) $fallback->fetchColumn();
            if ($legacyRole !== '') {
                $roles[] = $legacyRole === 'owner' ? 'super-admin' : ($legacyRole === 'manager' ? 'content-manager' : 'observer');
            }
        }
        return array_values(array_unique(array_map('strval', $roles)));
    } catch (Throwable $e) {
        return [];
    }
}

function loadUserPermissions(PDO $pdo, int $userId): array
{
    ensureRbacInfrastructure($pdo);

    try {
        $stmt = $pdo->prepare('SELECT DISTINCT p.slug FROM permissions p INNER JOIN role_permissions rp ON rp.permission_id = p.id INNER JOIN admin_user_roles aur ON aur.role_id = rp.role_id WHERE aur.admin_user_id = :id');
        $stmt->execute(['id' => $userId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (!$permissions) {
            $roleStmt = $pdo->prepare('SELECT role FROM admin_users WHERE id = :id');
            $roleStmt->execute(['id' => $userId]);
            $legacyRole = (string) $roleStmt->fetchColumn();
            if ($legacyRole === 'owner') {
                $permStmt = $pdo->query('SELECT slug FROM permissions');
                return $permStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }
        }

        return array_values(array_unique(array_map('strval', $permissions)));
    } catch (Throwable $e) {
        return [];
    }
}

function attachUserPermissions(PDO $pdo, array $user): array
{
    if (!isset($user['id'])) {
        return $user;
    }
    $userId = (int) $user['id'];
    $user['roles'] = loadUserRoleSlugs($pdo, $userId);
    $user['permissions'] = loadUserPermissions($pdo, $userId);
    return $user;
}

function userHasPermission(array $user, string $permission): bool
{
    if (isset($user['roles']) && in_array('super-admin', $user['roles'], true)) {
        return true;
    }
    if (!isset($user['permissions']) || !is_array($user['permissions'])) {
        return false;
    }
    return in_array($permission, $user['permissions'], true);
}

function requirePermission(array $user, string $permission): void
{
    if (userHasPermission($user, $permission)) {
        return;
    }

    http_response_code(403);
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><title>Erişim reddedildi</title>';
    echo '<style>body{font-family:Inter,system-ui,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:40px;}';
    echo '.card{background:rgba(15,23,42,0.7);backdrop-filter:blur(18px);border-radius:20px;border:1px solid rgba(148,163,184,0.2);padding:32px;max-width:440px;text-align:center;}';
    echo '.card h1{font-size:1.5rem;margin-bottom:12px;} .card p{font-size:0.95rem;line-height:1.5;} .card a{display:inline-flex;margin-top:20px;padding:10px 18px;border-radius:12px;background:#475569;color:#f8fafc;text-decoration:none;font-weight:600;}';
    echo '</style></head><body><div class="card"><h1>Erişim reddedildi</h1><p>Bu bölümü görüntülemek için yetkiniz bulunmuyor. Detaylar için yönetici hesabınız ile iletişime geçebilirsiniz.</p><a href="/admin/dashboard.php">Kontrol paneline dön</a></div></body></html>';
    exit;
}
