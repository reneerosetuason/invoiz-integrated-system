<?php

// Keep registration order stable; URLs, route names and middleware stay with their role.
require __DIR__.'/buyer/landing.php';
require __DIR__.'/buyer/mobile-web.php';
require __DIR__.'/seller/application.php';
require __DIR__.'/shared/auth.php';
require __DIR__.'/buyer/web.php';
require __DIR__.'/seller/web.php';
require __DIR__.'/admin/auth.php';
require __DIR__.'/buyer/chat.php';
require __DIR__.'/admin/web.php';
require __DIR__.'/shared/storage.php';
require __DIR__.'/logistics/web.php';
