<?php

namespace App\Console\Commands;

use App\Support\AssetFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeMarketplaceDocuments extends Command
{
    protected $signature = 'documents:privatize {--apply : Copy, verify and remove public copies}';
    protected $description = 'Move marketplace identity/tax documents out of web-accessible storage; preserves database paths.';

    public function handle(): int
    {
        $files = [];
        foreach ([storage_path('app/public'), public_path('storage')] as $root) {
            $base = realpath($root);
            if (! $base) {
                continue;
            }
            foreach (AssetFiles::PRIVATE_DIRECTORIES as $directory) {
                $source = $base.DIRECTORY_SEPARATOR.$directory;
                if (! is_dir($source)) {
                    continue;
                }
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                    $resolved = AssetFiles::within($base, $relative);
                    if (! $resolved) {
                        $this->error('Document path escapes its source directory. No files changed.');
                        return self::FAILURE;
                    }
                    $files[$resolved] = ['relative' => $relative, 'hash' => hash_file('sha256', $resolved)];
                }
            }
        }
        // Check every collision before changing any source file.
        $hashes = [];
        foreach ($files as $file) {
            $relative = $file['relative'];
            $destination = Storage::disk('private')->path($relative);
            $prior = $hashes[$relative] ?? (is_file($destination) ? hash_file('sha256', $destination) : null);
            if ($prior !== null && ! hash_equals($prior, $file['hash'])) {
                $this->error('Conflicting document copies found. No public copies removed; resolve on a staging backup.');
                return self::FAILURE;
            }
            $hashes[$relative] = $file['hash'];
        }
        $this->info(count($files).' public copies; '.count($hashes).' unique documents.');
        if (! $this->option('apply')) {
            $this->info('Dry run. Use --apply to protect these files.');
            return self::SUCCESS;
        }
        // Preserve and verify every destination before removing any public copy.
        foreach ($files as $source => $file) {
            $destination = Storage::disk('private')->path($file['relative']);
            if (! is_file($destination)) {
                Storage::disk('private')->put($file['relative'], fopen($source, 'rb'));
            }
            if (! is_file($destination) || ! hash_equals($file['hash'], hash_file('sha256', $destination))) {
                $this->error('Private copy verification failed. Public copies have not been removed.');
                return self::FAILURE;
            }
        }
        foreach ($files as $source => $file) {
            if (! hash_equals($file['hash'], hash_file('sha256', $source)) || ! unlink($source)) {
                $this->error('A public copy changed or could not be removed. Private copies are preserved; retry after review.');
                return self::FAILURE;
            }
        }
        $this->info('Private copies verified; public document copies removed. No database records changed.');
        return self::SUCCESS;
    }
}
