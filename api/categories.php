<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';
require_once __DIR__ . '/../src/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

ensureSession();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pathInfo = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    respond(500, ['error' => 'Veritabanı bağlantısı bulunamadı.']);
}

ensureCategoryInfrastructure($pdo);

function normalizeTreeNode(array $node, int $depth = 0): array
{
    $children = [];
    foreach ($node['subcategories'] ?? [] as $child) {
        $children[] = normalizeTreeNode($child, $depth + 1);
    }

    return [
        'id' => (int) ($node['id'] ?? 0),
        'parent_id' => isset($node['parent_id']) ? (int) $node['parent_id'] : null,
        'name' => (string) ($node['name'] ?? $node['title'] ?? ''),
        'slug' => (string) ($node['slug'] ?? ''),
        'description' => $node['description'] ?? null,
        'emoji' => $node['emoji'] ?? null,
        'icon_url' => $node['icon_url'] ?? $node['icon'] ?? null,
        'sort_order' => (int) ($node['sort_order'] ?? 0),
        'is_active' => !empty($node['is_active']),
        'depth' => $depth,
        'children' => $children,
    ];
}

function validateCategoryPayload(array $data, bool $isUpdate = false, ?int $categoryId = null): array
{
    $errors = [];

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        $errors['name'] = 'Ad 2 ile 80 karakter arasında olmalıdır.';
    }

    if (isset($data['slug']) && $data['slug'] !== '') {
        $slug = sanitizeSlug((string) $data['slug']);
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            $errors['slug'] = 'Slug yalnızca küçük harf, rakam ve tire içerebilir.';
        }
    }

    if (!empty($data['icon_url'])) {
        $icon = trim((string) $data['icon_url']);
        if (filter_var($icon, FILTER_VALIDATE_URL) === false && strncmp($icon, '/', 1) !== 0 && !preg_match('/^[\p{So}\p{Sk}\p{Sc}\p{Sm}]{1,2}$/u', $icon)) {
            $errors['icon_url'] = 'Geçerli bir URL veya emoji girin.';
        }
    }

    if ($isUpdate && ($categoryId === null || $categoryId <= 0)) {
        $errors['id'] = 'Geçerli bir kategori belirtilmelidir.';
    }

    return $errors;
}

function ensureAdmin(): array
{
    return requireAuth();
}

function enforceCsrf(string $token): void
{
    if (!validateCsrfToken('category-actions', $token)) {
        respond(419, ['error' => 'CSRF doğrulaması başarısız oldu.']);
    }
}

switch ($method) {
    case 'GET':
        $target = $pathInfo;
        if ($target === '' && str_contains($requestUri, 'tree')) {
            $target = 'tree';
        }
        if ($target === 'tree') {
            $tree = fetchCategoryTree($pdo);
            $normalized = [];
            foreach ($tree as $node) {
                $normalized[] = normalizeTreeNode($node, 0);
            }
            respond(200, ['data' => $normalized]);
        }

        if (isset($_GET['flat']) && $_GET['flat'] === '1') {
            $flat = fetchFlatCategories($pdo);
            $response = [];
            foreach ($flat as $item) {
                $response[] = [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['name'],
                    'slug' => (string) $item['slug'],
                    'parent_id' => $item['parent_id'] !== null ? (int) $item['parent_id'] : null,
                    'depth' => (int) $item['depth'],
                    'description' => $item['description'],
                    'emoji' => $item['emoji'],
                    'icon_url' => $item['icon_url'],
                    'sort_order' => (int) $item['sort_order'],
                    'is_active' => (bool) $item['is_active'],
                ];
            }
            respond(200, ['data' => $response]);
        }

        // default response tree
        $tree = fetchCategoryTree($pdo);
        $normalized = [];
        foreach ($tree as $node) {
            $normalized[] = normalizeTreeNode($node, 0);
        }
        respond(200, ['data' => $normalized]);
        break;

    case 'POST':
        ensureAdmin();
        $csrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        enforceCsrf($csrfToken);

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload)) {
            respond(400, ['error' => 'Geçersiz JSON gövdesi.']);
        }

        $errors = validateCategoryPayload($payload);
        if (!empty($errors)) {
            respond(422, ['errors' => $errors]);
        }

        $parentId = isset($payload['parent_id']) ? (int) $payload['parent_id'] : null;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }
        if ($parentId !== null && !categoryExists($pdo, $parentId)) {
            respond(422, ['errors' => ['parent_id' => 'Üst kategori bulunamadı.']]);
        }

        $newId = createCategory($pdo, [
            'name' => trim((string) $payload['name']),
            'slug' => $payload['slug'] ?? '',
            'description' => $payload['description'] ?? null,
            'emoji' => $payload['emoji'] ?? null,
            'icon_url' => $payload['icon_url'] ?? null,
            'sort_order' => $payload['sort_order'] ?? 0,
            'is_active' => isset($payload['is_active']) ? (bool) $payload['is_active'] : true,
            'parent_id' => $parentId,
        ]);

        $created = fetchCategoryById($pdo, $newId);
        respond(201, ['data' => $created]);
        break;

    case 'PUT':
        ensureAdmin();
        $csrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        enforceCsrf($csrfToken);

        $categoryId = null;
        if ($pathInfo !== '' && $pathInfo !== 'tree') {
            $categoryId = (int) $pathInfo;
        } elseif (isset($_GET['id'])) {
            $categoryId = (int) $_GET['id'];
        }
        if (!$categoryId || $categoryId <= 0) {
            respond(400, ['error' => 'Düzenlenecek kategori belirtilmelidir.']);
        }

        if (!categoryExists($pdo, $categoryId)) {
            respond(404, ['error' => 'Kategori bulunamadı.']);
        }

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload)) {
            respond(400, ['error' => 'Geçersiz JSON gövdesi.']);
        }

        $errors = validateCategoryPayload($payload, true, $categoryId);
        if (!empty($errors)) {
            respond(422, ['errors' => $errors]);
        }

        $parentId = isset($payload['parent_id']) ? (int) $payload['parent_id'] : null;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }
        if ($parentId !== null) {
            if (!categoryExists($pdo, $parentId)) {
                respond(422, ['errors' => ['parent_id' => 'Üst kategori bulunamadı.']]);
            }
            if ($parentId === $categoryId || categoryWouldCreateCycle($pdo, $categoryId, $parentId)) {
                respond(422, ['errors' => ['parent_id' => 'Kategori kendi altına taşınamaz.']]);
            }
        }

        updateCategory($pdo, $categoryId, [
            'name' => $payload['name'] ?? null,
            'slug' => $payload['slug'] ?? null,
            'description' => $payload['description'] ?? null,
            'emoji' => $payload['emoji'] ?? null,
            'icon_url' => $payload['icon_url'] ?? null,
            'sort_order' => $payload['sort_order'] ?? null,
            'is_active' => isset($payload['is_active']) ? (bool) $payload['is_active'] : null,
            'parent_id' => $parentId,
        ]);

        $updated = fetchCategoryById($pdo, $categoryId);
        respond(200, ['data' => $updated]);
        break;

    default:
        respond(405, ['error' => 'İzin verilmeyen yöntem.']);
}
