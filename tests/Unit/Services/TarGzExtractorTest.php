<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TarGzExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class TarGzExtractorTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = sys_get_temp_dir().'/tar-extractor-test-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->workDir.'/out');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDir);

        parent::tearDown();
    }

    public function test_it_extracts_regular_files_into_nested_directories(): void
    {
        $archive = $this->writeArchive([
            self::entry('mods/', '', '5'),
            self::entry('mods/a.jar', 'jar-bytes'),
            self::entry('config/sub/b.toml', str_repeat('x', 1500)),
            self::entry('config/empty.txt', ''),
        ]);

        $written = (new TarGzExtractor)->extract($archive, $this->workDir.'/out');

        $this->assertSame(3, $written);
        $this->assertSame('jar-bytes', file_get_contents($this->workDir.'/out/mods/a.jar'));
        $this->assertSame(str_repeat('x', 1500), file_get_contents($this->workDir.'/out/config/sub/b.toml'));
        $this->assertSame('', file_get_contents($this->workDir.'/out/config/empty.txt'));
    }

    public function test_it_ignores_symlinks_and_hardlinks(): void
    {
        $archive = $this->writeArchive([
            self::entry('config/link', '', '2', '/etc/passwd'),
            self::entry('config/hard', '', '1', 'config/real.txt'),
            self::entry('config/real.txt', 'real'),
        ]);

        $written = (new TarGzExtractor)->extract($archive, $this->workDir.'/out');

        $this->assertSame(1, $written);
        $this->assertFileDoesNotExist($this->workDir.'/out/config/link');
        $this->assertFileDoesNotExist($this->workDir.'/out/config/hard');
        $this->assertSame(['real.txt'], array_values(array_diff(scandir($this->workDir.'/out/config') ?: [], ['.', '..'])));
    }

    public function test_it_rejects_parent_directory_traversal(): void
    {
        $archive = $this->writeArchive([
            self::entry('config/../../escaped.txt', 'evil'),
        ]);

        try {
            (new TarGzExtractor)->extract($archive, $this->workDir.'/out');
            $this->fail('Expected traversal entry to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Unsafe path', $e->getMessage());
        }

        $this->assertFileDoesNotExist($this->workDir.'/escaped.txt');
        $this->assertFileDoesNotExist(dirname($this->workDir).'/escaped.txt');
    }

    public function test_it_rejects_absolute_paths(): void
    {
        $archive = $this->writeArchive([
            self::entry('/tmp/absolute.txt', 'evil'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsafe path');

        (new TarGzExtractor)->extract($archive, $this->workDir.'/out');
    }

    public function test_it_rejects_traversal_hidden_in_a_pax_path(): void
    {
        $archive = $this->writeArchive([
            self::paxEntry(['path' => '../pax-escaped.txt']),
            self::entry('harmless.txt', 'evil'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsafe path');

        (new TarGzExtractor)->extract($archive, $this->workDir.'/out');
    }

    public function test_it_uses_pax_paths_for_long_names(): void
    {
        $longPath = 'config/'.str_repeat('d', 120).'/'.str_repeat('f', 120).'.json';
        $archive = $this->writeArchive([
            self::paxEntry(['path' => $longPath]),
            self::entry('truncated-name', '{"ok":true}'),
            self::entry('config/after.txt', 'after'),
        ]);

        $written = (new TarGzExtractor)->extract($archive, $this->workDir.'/out');

        $this->assertSame(2, $written);
        $this->assertSame('{"ok":true}', file_get_contents($this->workDir.'/out/'.$longPath));
        $this->assertFileDoesNotExist($this->workDir.'/out/truncated-name');
        $this->assertSame('after', file_get_contents($this->workDir.'/out/config/after.txt'));
    }

    public function test_it_only_writes_files_the_callback_accepts(): void
    {
        $archive = $this->writeArchive([
            self::entry('mods/keep.jar', 'keep'),
            self::entry('logs/latest.log', 'skip'),
            self::entry('mods/after.jar', 'after'),
        ]);

        $seen = [];
        $written = (new TarGzExtractor)->extract($archive, $this->workDir.'/out', function (string $relative) use (&$seen): bool {
            $seen[] = $relative;

            return str_starts_with($relative, 'mods/');
        });

        $this->assertSame(2, $written);
        $this->assertSame(['mods/keep.jar', 'logs/latest.log', 'mods/after.jar'], $seen);
        $this->assertDirectoryDoesNotExist($this->workDir.'/out/logs');
        $this->assertSame('after', file_get_contents($this->workDir.'/out/mods/after.jar'));
    }

    public function test_it_rejects_corrupt_headers(): void
    {
        $entry = self::entry('mods/a.jar', 'jar');
        $entry[10] = 'X';
        $archive = $this->writeArchive([$entry]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('checksum');

        (new TarGzExtractor)->extract($archive, $this->workDir.'/out');
    }

    public function test_it_rejects_truncated_archives(): void
    {
        $tar = self::entry('mods/a.jar', str_repeat('a', 4096));
        $archive = $this->workDir.'/truncated.tar.gz';
        file_put_contents($archive, gzencode(substr($tar, 0, 2048)));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected end of archive');

        (new TarGzExtractor)->extract($archive, $this->workDir.'/out');
    }

    public function test_it_extracts_archives_created_by_system_tar(): void
    {
        if (Process::run('tar --version')->failed()) {
            $this->markTestSkipped('tar binary not available.');
        }

        $source = $this->workDir.'/source';
        $longDir = 'config/'.str_repeat('n', 110);
        File::ensureDirectoryExists($source.'/'.$longDir);
        File::ensureDirectoryExists($source.'/mods');
        file_put_contents($source.'/'.$longDir.'/'.str_repeat('m', 90).'.toml', 'long');
        file_put_contents($source.'/mods/a.jar', random_bytes(70000));
        symlink('/etc/passwd', $source.'/mods/link.jar');

        $archive = $this->workDir.'/system.tar.gz';
        Process::path($source)->run(['tar', '-czf', $archive, 'config', 'mods'])->throw();

        $written = (new TarGzExtractor)->extract($archive, $this->workDir.'/out');

        $this->assertSame(2, $written);
        $this->assertSame('long', file_get_contents($this->workDir.'/out/'.$longDir.'/'.str_repeat('m', 90).'.toml'));
        $this->assertSame(md5_file($source.'/mods/a.jar'), md5_file($this->workDir.'/out/mods/a.jar'));
        $this->assertFileDoesNotExist($this->workDir.'/out/mods/link.jar');
    }

    /**
     * @param  list<string>  $entries
     */
    private function writeArchive(array $entries): string
    {
        $path = $this->workDir.'/archive-'.count(glob($this->workDir.'/*.tar.gz') ?: []).'.tar.gz';
        file_put_contents($path, gzencode(implode('', $entries).str_repeat("\0", 1024)));

        return $path;
    }

    /**
     * @param  array<string, string>  $records
     */
    private static function paxEntry(array $records): string
    {
        $data = '';
        foreach ($records as $key => $value) {
            $record = " {$key}={$value}\n";
            $length = strlen($record) + strlen((string) strlen($record));
            $length += strlen((string) $length) - strlen((string) strlen($record));
            $data .= $length.$record;
        }

        return self::entry('PaxHeaders/entry', $data, 'x');
    }

    private static function entry(string $name, string $content, string $type = '0', string $linkName = ''): string
    {
        $header = str_pad($name, 100, "\0")
            .str_pad('0000644', 8, "\0")
            .str_pad('0000000', 8, "\0")
            .str_pad('0000000', 8, "\0")
            .str_pad(sprintf('%011o', strlen($content)), 12, "\0")
            .str_pad(sprintf('%011o', 0), 12, "\0")
            .'        '
            .$type
            .str_pad($linkName, 100, "\0")
            ."ustar\0".'00'
            .str_repeat("\0", 32 * 2 + 8 * 2 + 155 + 12);

        $checksum = array_sum(array_map(ord(...), str_split($header)));
        $header = substr_replace($header, sprintf('%06o', $checksum)."\0 ", 148, 8);

        $padding = (512 - (strlen($content) % 512)) % 512;

        return $header.$content.str_repeat("\0", $padding);
    }
}
