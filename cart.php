<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/session.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/customer.php';

ensureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($_SERVER['HTTP_REFERER'] ?? '/index.php');
}

$redirectTarget = (string) ($_POST['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/index.php'));
if ($redirectTarget === '' || strpos($redirectTarget, '/') !== 0) {
    $redirectTarget = '/index.php';
}

$formKey = (string) ($_POST['form_key'] ?? 'cart-action');
if (!validateCsrfToken($formKey, $_POST['_token'] ?? null)) {
    addFlash('error', 'Sepet isteği doğrulanamadı. Lütfen tekrar deneyin.');
    redirect($redirectTarget);
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    addFlash('error', 'Sepet işlemleri için veritabanı bağlantısı kurulamadı.');
    redirect($redirectTarget);
}

$customer = getCurrentCustomer();
$customerId = $customer['id'] ?? null;
$action = (string) ($_POST['cart_action'] ?? 'add');

try {
    switch ($action) {
        case 'add':
            $productId = (int) ($_POST['product_id'] ?? 0);
            $quantity = (int) ($_POST['quantity'] ?? 1);
            $quantity = max(1, min(999, $quantity));

            if ($productId <= 0) {
                addFlash('error', 'Sepete eklemek için geçerli bir ürün seçin.');
                break;
            }

            $product = fetchProductSnapshot($pdo, $productId);
            if (!$product) {
                addFlash('error', 'Seçilen ürün bulunamadı veya yayından kaldırılmış olabilir.');
                break;
            }

            if ($customerId) {
                addCustomerCartItem($pdo, (int) $customerId, $productId, $quantity, false);
                syncCustomerCartToSession($pdo, (int) $customerId);
            } else {
                $sessionCart = getSessionCartItems();
                $sessionCart[$productId] = min(999, ($sessionCart[$productId] ?? 0) + $quantity);
                saveSessionCartItems($sessionCart);
            }

            addFlash('success', sprintf('"%s" ürününden %d adet sepete eklendi.', $product['name'], $quantity));
            break;

        case 'update':
            $productId = (int) ($_POST['product_id'] ?? 0);
            $quantity = max(0, min(999, (int) ($_POST['quantity'] ?? 1)));
            if ($productId <= 0) {
                addFlash('error', 'Geçersiz ürün seçimi.');
                break;
            }
            if ($customerId) {
                if ($quantity === 0) {
                    removeCustomerCartItem($pdo, (int) $customerId, $productId);
                } else {
                    setCustomerCartItemQuantity($pdo, (int) $customerId, $productId, $quantity);
                }
                syncCustomerCartToSession($pdo, (int) $customerId);
            } else {
                $sessionCart = getSessionCartItems();
                if ($quantity === 0) {
                    unset($sessionCart[$productId]);
                } else {
                    $sessionCart[$productId] = $quantity;
                }
                saveSessionCartItems($sessionCart);
            }
            addFlash('success', 'Sepet güncellendi.');
            break;

        case 'remove':
            $productId = (int) ($_POST['product_id'] ?? 0);
            if ($productId <= 0) {
                addFlash('error', 'Geçersiz ürün seçimi.');
                break;
            }
            if ($customerId) {
                removeCustomerCartItem($pdo, (int) $customerId, $productId);
                syncCustomerCartToSession($pdo, (int) $customerId);
            } else {
                $sessionCart = getSessionCartItems();
                unset($sessionCart[$productId]);
                saveSessionCartItems($sessionCart);
            }
            addFlash('success', 'Ürün sepetten kaldırıldı.');
            break;

        case 'clear':
            if ($customerId) {
                clearCustomerCart($pdo, (int) $customerId);
                syncCustomerCartToSession($pdo, (int) $customerId);
            } else {
                clearSessionCartItems();
            }
            addFlash('success', 'Sepetiniz temizlendi.');
            break;

        default:
            addFlash('error', 'Bilinmeyen sepet işlemi.');
            break;
    }
} catch (Throwable $e) {
    addFlash('error', 'Sepet işlemi sırasında bir hata oluştu: ' . $e->getMessage());
}

redirect($redirectTarget);
