<?php
declare(strict_types=1);

require __DIR__ . '/../src/session.php';

ensureSession();
clearCurrentUser();
addFlash('success', 'Oturum kapatıldı.');
redirect('/index.php');
