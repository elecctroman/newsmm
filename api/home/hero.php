<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
http_response_code(410);

echo json_encode([
    'status' => 'gone',
    'message' => 'Hero slider bileşeni kaldırıldı. Lütfen home_blocks API veya kategori uçlarını kullanın.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
