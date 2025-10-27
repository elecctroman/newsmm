<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

function authenticateUser(PDO $pdo, string $email, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM admin_users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => trim($email)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return null;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }
    unset($user['password_hash']);
    return $user;
}

function recordSuccessfulLogin(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $userId]);
}

function requireAuth(): array
{
    $user = getCurrentUser();
    if (!$user) {
        redirect('/index.php');
    }
    return $user;
}

function ensureGuest(): void
{
    if (getCurrentUser()) {
        redirect('/admin/dashboard.php');
    }
}

