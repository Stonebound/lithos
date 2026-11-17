<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Services\SftpService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Runs SftpService against a real OpenSSH server.
 *
 * Each test starts a throwaway, unprivileged sshd on a free loopback port that only
 * accepts a freshly generated key for the current user. Skipped when sshd is not installed.
 */
class SftpIntegrationTest extends TestCase
{
    private const string SSHD_BINARY = '/usr/sbin/sshd';

    private string $workDir;

    private string $remoteRoot;

    private Process $sshd;

    private int $port;

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_executable(self::SSHD_BINARY)) {
            $this->markTestSkipped('OpenSSH server not installed ('.self::SSHD_BINARY.').');
        }

        Storage::fake('local');

        $this->workDir = sys_get_temp_dir().'/sftp-integration-'.bin2hex(random_bytes(6));
        $this->remoteRoot = $this->workDir.'/remote';
        File::ensureDirectoryExists($this->remoteRoot);

        $this->startSshd();
    }

    protected function tearDown(): void
    {
        if (isset($this->sshd)) {
            $this->sshd->stop(1);
        }

        if (isset($this->workDir)) {
            // Restore permissions so locked fixture directories can be removed.
            (new Process(['chmod', '-R', 'u+rwx', $this->workDir]))->run();
            File::deleteDirectory($this->workDir);
        }

        parent::tearDown();
    }

    public function test_it_uploads_files_into_missing_nested_remote_directories(): void
    {
        config()->set('services.sftp.parallel_upload_connections', 1);
        Storage::disk('local')->put('prepared/config/game.json', '{"enabled":true}');
        Storage::disk('local')->put('prepared/mods/deep/nested/mod.jar', 'jar-bytes');
        File::ensureDirectoryExists($this->remoteRoot.'/mods');

        $summary = (new SftpService)->syncServerDirectory($this->server(), 'prepared', $this->remoteRoot);

        $this->assertSame(2, $summary['uploaded_files']);
        $this->assertSame(0, $summary['failed_workers']);
        $this->assertSame('{"enabled":true}', file_get_contents($this->remoteRoot.'/config/game.json'));
        $this->assertSame('jar-bytes', file_get_contents($this->remoteRoot.'/mods/deep/nested/mod.jar'));
    }

    public function test_it_downloads_included_directories_without_skipped_files_or_symlinks(): void
    {
        $this->writeRemote('config/a.toml', 'a = 1');
        $this->writeRemote('config/nested/b.json', '{"b":2}');
        $this->writeRemote('config/secret.toml', 'token');
        $this->writeRemote('mods/123', 'numeric name');
        $this->writeRemote('logs/latest.log', 'not included');
        symlink('/etc/passwd', $this->remoteRoot.'/mods/linked.jar');

        $service = new SftpService;
        $service->downloadDirectory($service->connect($this->server()), $this->remoteRoot, 'snapshot', [], 0, ['config/secret*']);

        $this->assertSame(
            ['snapshot/config/a.toml', 'snapshot/config/nested/b.json', 'snapshot/mods/123'],
            Storage::disk('local')->allFiles('snapshot'),
        );
        $this->assertSame('{"b":2}', Storage::disk('local')->get('snapshot/config/nested/b.json'));
    }

    public function test_it_deletes_remote_files_missing_locally_except_skipped_ones(): void
    {
        Storage::disk('local')->put('prepared/config/keep.toml', 'keep');
        Storage::disk('local')->put('prepared/mods/keep.jar', 'keep');
        $this->writeRemote('config/keep.toml', 'keep');
        $this->writeRemote('config/removed.toml', 'gone');
        $this->writeRemote('config/server.log', 'skipped');
        $this->writeRemote('mods/keep.jar', 'keep');
        $this->writeRemote('mods/removed/old.jar', 'gone');
        $this->writeRemote('kubejs/scripts/main.js', 'gone');
        $this->writeRemote('world/level.dat', 'outside managed dirs');

        $service = new SftpService;
        $service->deleteRemoved($service->connect($this->server()), 'prepared', $this->remoteRoot, [], ['config/*.log']);

        $this->assertFileExists($this->remoteRoot.'/config/keep.toml');
        $this->assertFileExists($this->remoteRoot.'/config/server.log');
        $this->assertFileExists($this->remoteRoot.'/mods/keep.jar');
        $this->assertFileExists($this->remoteRoot.'/world/level.dat');
        $this->assertFileDoesNotExist($this->remoteRoot.'/config/removed.toml');
        $this->assertDirectoryDoesNotExist($this->remoteRoot.'/mods/removed');
        $this->assertDirectoryDoesNotExist($this->remoteRoot.'/kubejs');
    }

    public function test_a_failed_upload_reports_the_file_that_could_not_be_written(): void
    {
        Storage::disk('local')->put('prepared/locked/file.txt', 'content');
        $this->lockRemoteDirectory('locked');

        $summary = (new SftpService)->uploadRelativeFileBatch($this->connectionConfig(), 'prepared', $this->remoteRoot, ['locked/file.txt'], 1);

        $this->assertSame('failed', $summary['status']);
        $this->assertSame('file.txt', $summary['failed_file']);
        $this->assertSame('Failed to upload: '.$this->remoteRoot.'/locked/file.txt', $summary['error']);
    }

    public function test_a_failed_remote_directory_creation_reports_the_directory(): void
    {
        Storage::disk('local')->put('prepared/locked/sub/file.txt', 'content');
        $this->lockRemoteDirectory('locked');

        $summary = (new SftpService)->uploadRelativeFileBatch($this->connectionConfig(), 'prepared', $this->remoteRoot, ['locked/sub/file.txt'], 1);

        $this->assertSame('failed', $summary['status']);
        $this->assertSame('sub', $summary['failed_file']);
        $this->assertSame('Failed to create remote directory: '.$this->remoteRoot.'/locked/sub', $summary['error']);
    }

    public function test_it_fails_to_download_a_missing_remote_path(): void
    {
        $service = new SftpService;
        $sftp = $service->connect($this->server());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to list remote path: '.$this->remoteRoot.'/missing');

        $service->downloadDirectory($sftp, $this->remoteRoot.'/missing', 'snapshot');
    }

    public function test_it_rejects_an_unauthorized_key(): void
    {
        $this->generateKey($this->workDir.'/unauthorized_key');
        Storage::disk('local')->put('keys/unauthorized', (string) file_get_contents($this->workDir.'/unauthorized_key'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SFTP key login failed');

        (new SftpService)->connect($this->server('keys/unauthorized'));
    }

    private function server(string $privateKeyPath = 'keys/client'): Server
    {
        return Server::factory()->make([
            'host' => '127.0.0.1',
            'port' => $this->port,
            'username' => $this->currentUser(),
            'auth_type' => 'private_key',
            'password' => null,
            'private_key_path' => $privateKeyPath,
            'remote_root_path' => $this->remoteRoot,
        ]);
    }

    /**
     * @return array{host: string, port: int, username: string, auth_type: string, password: ?string, private_key_path: ?string}
     */
    private function connectionConfig(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => $this->port,
            'username' => $this->currentUser(),
            'auth_type' => 'private_key',
            'password' => null,
            'private_key_path' => 'keys/client',
        ];
    }

    private function startSshd(): void
    {
        $this->generateKey($this->workDir.'/host_key');
        $this->generateKey($this->workDir.'/client_key');
        File::copy($this->workDir.'/client_key.pub', $this->workDir.'/authorized_keys');
        Storage::disk('local')->put('keys/client', (string) file_get_contents($this->workDir.'/client_key'));

        $this->port = $this->freePort();

        file_put_contents($this->workDir.'/sshd_config', implode("\n", [
            'ListenAddress 127.0.0.1',
            'Port '.$this->port,
            'HostKey '.$this->workDir.'/host_key',
            'PidFile '.$this->workDir.'/sshd.pid',
            'AuthorizedKeysFile '.$this->workDir.'/authorized_keys',
            'AllowUsers '.$this->currentUser(),
            'StrictModes no',
            'UsePAM no',
            'PasswordAuthentication no',
            'KbdInteractiveAuthentication no',
            'Subsystem sftp internal-sftp',
            'LogLevel ERROR',
        ])."\n");

        $this->sshd = new Process([self::SSHD_BINARY, '-D', '-e', '-f', $this->workDir.'/sshd_config']);
        $this->sshd->start();

        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            if (! $this->sshd->isRunning()) {
                $this->fail('sshd exited: '.$this->sshd->getErrorOutput());
            }

            $socket = @fsockopen('127.0.0.1', $this->port, $errorCode, $errorMessage, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(50_000);
        }

        $this->fail('sshd did not start listening: '.$this->sshd->getErrorOutput());
    }

    private function generateKey(string $path): void
    {
        (new Process(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-f', $path]))->mustRun();
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            $this->fail('Could not reserve a free port.');
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function currentUser(): string
    {
        $user = posix_getpwuid(posix_geteuid());

        return is_array($user) ? $user['name'] : get_current_user();
    }

    private function writeRemote(string $relativePath, string $contents): void
    {
        File::ensureDirectoryExists(dirname($this->remoteRoot.'/'.$relativePath));
        file_put_contents($this->remoteRoot.'/'.$relativePath, $contents);
    }

    private function lockRemoteDirectory(string $relativePath): void
    {
        File::ensureDirectoryExists($this->remoteRoot.'/'.$relativePath);
        chmod($this->remoteRoot.'/'.$relativePath, 0555);
    }
}
