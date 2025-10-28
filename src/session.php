<?php

declare(strict_types=1);

function ensureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'] ?? '/',
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function setCurrentUser(array $user, bool $regenerate = true): void
{
    ensureSession();
    $_SESSION['auth_user'] = $user;
    if ($regenerate) {
        session_regenerate_id(true);
    }
}

function getCurrentUser(): ?array
{
    ensureSession();
    /** @var array|null $user */
    $user = $_SESSION['auth_user'] ?? null;
    return $user ? $user : null;
}

function clearCurrentUser(): void
{
    ensureSession();
    unset($_SESSION['auth_user'], $_SESSION['csrf_tokens']);
    $_SESSION['flash_messages'] = [];
    session_regenerate_id(true);
}

function setCurrentCustomer(array $customer): void
{
    ensureSession();
    $_SESSION['customer_user'] = $customer;
    session_regenerate_id(true);
}

function getCurrentCustomer(): ?array
{
    ensureSession();
    /** @var array|null $user */
    $user = $_SESSION['customer_user'] ?? null;
    return $user ?: null;
}

function clearCurrentCustomer(): void
{
    ensureSession();
    unset($_SESSION['customer_user']);
    unset($_SESSION['csrf_tokens']);
    session_regenerate_id(true);
}

function addFlash(string $type, string $message): void
{
    ensureSession();
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    ensureSession();
    $flashes = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

function issueCsrfToken(string $form): string
{
    ensureSession();
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$form] = $token;
    return $token;
}

function validateCsrfToken(string $form, ?string $token): bool
{
    ensureSession();
    if (!isset($_SESSION['csrf_tokens'][$form])) {
        return false;
    }
    $valid = hash_equals($_SESSION['csrf_tokens'][$form], (string)($token ?? ''));
    if ($valid) {
        unset($_SESSION['csrf_tokens'][$form]);
    }
    return $valid;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

