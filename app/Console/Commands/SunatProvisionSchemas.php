<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;
use ZipArchive;

final class SunatProvisionSchemas extends Command
{
    protected $signature = 'sunat:provision-schemas
        {--verify : Verify the provisioned XSD tree against the committed manifest without touching it}
        {--from= : Extract a locally supplied official "Archivos XSD" ZIP and then verify}';

    protected $description = 'Verify or provision the official SUNAT XSD tree from a local archive (no network access)';

    public function handle(FilesystemManager $filesystems): int
    {
        $disk = $filesystems->disk((string) config('sunat.storage_disk'));
        $root = (string) config('sunat.xsd_root');
        $manifest = config('sunat_xsd_manifest');
        if (! is_array($manifest) || ! isset($manifest['files']) || ! is_array($manifest['files']) || $manifest['files'] === []) {
            $this->error('SUNAT_XSD_MANIFEST_MISSING');

            return self::FAILURE;
        }

        $from = $this->option('from');
        if (is_string($from) && $from !== '') {
            try {
                $this->extract($from, $disk, $root);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        return $this->verify($disk, $root, $manifest['files']) ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, string> $files */
    private function verify($disk, string $root, array $files): bool
    {
        $actual = [];
        foreach ($disk->allFiles($root) as $path) {
            $relative = ltrim(substr($path, strlen($root)), '/');
            $actual[$relative] = hash('sha256', (string) $disk->get($path));
        }

        $ok = true;
        foreach ($files as $relative => $expected) {
            $computed = $actual[$relative] ?? null;
            if ($computed === null) {
                $this->error("MISSING  {$relative}");
                $ok = false;
            } elseif (! hash_equals($expected, $computed)) {
                $this->error("CHANGED  {$relative}");
                $ok = false;
            }
            unset($actual[$relative]);
        }
        foreach (array_keys($actual) as $extra) {
            $this->warn("EXTRA    {$extra}");
        }

        $this->line(sprintf(
            '%s: %d manifest file(s) checked under %s (%d extra file(s)).',
            $ok ? 'OK' : 'FAILED',
            count($files),
            $root,
            count($actual),
        ));

        return $ok;
    }

    private function extract(string $from, $disk, string $root): void
    {
        if (! is_file($from)) {
            throw new RuntimeException('SUNAT_XSD_ARCHIVE_NOT_FOUND');
        }
        $zip = new ZipArchive;
        if ($zip->open($from) !== true) {
            throw new RuntimeException('SUNAT_XSD_ARCHIVE_INVALID');
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if ($name === false || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $name)) {
                    throw new RuntimeException('SUNAT_XSD_ARCHIVE_ENTRY_UNSAFE');
                }
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $contents = $zip->getFromIndex($index);
                if ($contents === false) {
                    throw new RuntimeException('SUNAT_XSD_ARCHIVE_ENTRY_UNREADABLE');
                }
                $disk->put($root.'/'.$name, $contents);
            }
        } finally {
            $zip->close();
        }

        $this->line("Extracted {$from} into {$root}.");
    }
}
