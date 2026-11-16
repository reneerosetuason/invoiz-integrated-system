<?php
require __DIR__.'/../scripts/test-bootstrap.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$compiler = $app->make('blade.compiler');
$paths = glob(resource_path('views/shared/failed-deliveries/*.blade.php'));
foreach (['logistics/deliveries/show', 'seller/orders/show', 'admin/payments', 'logistics/settings/index'] as $path) {
    $paths[] = resource_path('views/'.$path.'.blade.php');
}
foreach ($paths as $path) {
    token_get_all($compiler->compileString(file_get_contents($path)), TOKEN_PARSE);
}
echo 'Blade templates compiled and PHP parsed: '.count($paths).PHP_EOL;
