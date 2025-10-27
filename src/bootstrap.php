<?php
declare(strict_types=1);


$configPath = __DIR__ . '/../config/config.php';
$examplePath = __DIR__ . '/../config/config.example.php';

if (!file_exists($configPath)) {
    if (!file_exists($examplePath)) {
        throw new RuntimeException('Configuration file not found.');
    }
    $config = require $examplePath;
    $missingConfig = true;
} else {
    $config = require $configPath;
    $missingConfig = false;
}

if (!isset($config['db']) || !is_array($config['db'])) {
    throw new RuntimeException('Database configuration is missing.');
}

$dbConfig = $config['db'];

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $dbConfig['host'] ?? 'localhost',
    (int)($dbConfig['port'] ?? 3306),
    $dbConfig['name'] ?? '',
    $dbConfig['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO(
        $dsn,
        $dbConfig['user'] ?? '',
        $dbConfig['pass'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    $pdo = null;
    $connectionError = $e->getMessage();
}

function fetchCategories(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, slug, title, description, emoji, sort_order FROM categories ORDER BY sort_order, id');
    return $stmt->fetchAll();
}

function fetchProducts(PDO $pdo): array
{
    $sql = 'SELECT p.id, p.category_id, c.slug AS category_slug, p.name, p.price_label, p.price_cents, p.note, p.sort_order
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            ORDER BY c.sort_order, p.sort_order, p.id';
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}
