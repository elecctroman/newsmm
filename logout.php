<?php

declare(strict_types=1);

require __DIR__ . '/src/session.php';

ensureSession();

$target = isset($_GET['redirect']) ? (string) $_GET['redirect'] : '/';
if ($target === '' || preg_match('/^https?:/i', $target)) {
    $target = '/';
}

clearCurrentCustomer();
addFlash('success', 'Oturumunuz güvenle kapatıldı.');
redirect($target);
