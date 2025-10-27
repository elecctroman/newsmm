<?php

declare(strict_types=1);

function tableHasColumn(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $sanitizedTable = str_replace('`', '``', $table);

    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$sanitizedTable}` LIKE :column");
        $stmt->execute(['column' => $column]);
        $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $showColumnsException) {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
            );
            $stmt->execute([
                'table' => $table,
                'column' => $column,
            ]);
            $exists = (bool) $stmt->fetchColumn();
        } catch (\Throwable $fallbackException) {
            $exists = false;
        }
    }

    return $cache[$key] = $exists;
}

function sanitizeSlug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9-]+/u', '-', $value) ?? '';
    $value = preg_replace('/-+/', '-', $value) ?? '';
    return trim($value, '-') ?: '';
}

function sanitizeNullableString(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $trimmed = trim($value);
    return $trimmed === '' ? null : $trimmed;
}

function normalizePriceAmount(?string $value): ?float
{
    if ($value === null) {
        return null;
    }
    $normalized = str_replace(['₺', 'TRY', 'try', ' '], '', $value);
    $normalized = trim($normalized);
    $hasComma = strpos($normalized, ',') !== false;
    $hasDot = strpos($normalized, '.') !== false;
    if ($hasComma && $hasDot) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } elseif ($hasComma) {
        $normalized = str_replace(',', '.', $normalized);
    }
    $normalized = preg_replace('/[^0-9.]/', '', $normalized) ?? '';
    if ($normalized === '') {
        return null;
    }
    return filter_var($normalized, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
}

function formatPriceLabel(?string $label, float $amount): string
{
    $label = trim((string)($label ?? ''));
    if ($label !== '') {
        return $label;
    }
    $precision = (abs($amount - round($amount)) < 0.01) ? 0 : 2;
    $formatted = number_format($amount, $precision, ',', '.');
    return '₺' . $formatted;
}

function priceToCents(float $amount): int
{
    return (int) round($amount * 100);
}

function centsToPrice(int $cents): float
{
    return $cents / 100;
}

function formatCurrency(int $cents, string $currency = 'TRY'): string
{
    $amount = $cents / 100;
    $precision = (abs($amount - round($amount)) < 0.01) ? 0 : 2;
    $formatted = number_format($amount, $precision, ',', '.');
    $prefix = $currency === 'TRY' ? '₺' : ($currency . ' ');
    return $prefix . $formatted;
}

