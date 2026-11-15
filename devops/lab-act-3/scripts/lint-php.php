<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$failed = 0;
$checked = 0;
foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'tests', 'devops/lab-act-3/scripts', 'devops/lab-act-3/challenges'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || str_contains(str_replace('\\', '/', $file->getPathname()), '/bootstrap/cache/')) {
            continue;
        }
        $process = proc_open([PHP_BINARY, '-l', $file->getPathname()], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start PHP syntax checker.');
        }
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $checked++;
        if ($status !== 0) {
            $failed++;
            fwrite(STDERR, $output);
        }
    }
}
echo "PHP syntax: {$checked} files checked, {$failed} failures.\n";
exit($failed > 0 ? 1 : 0);
