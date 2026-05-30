<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Integration\RealServer;

use DevLancer\MinecraftStatus\MinecraftBedrockStatus;
use DevLancer\MinecraftStatus\Dns\SrvResolver;
use DevLancer\MinecraftStatus\MinecraftJavaPreOld17Status;
use DevLancer\MinecraftStatus\MinecraftJavaQuery;
use DevLancer\MinecraftStatus\MinecraftJavaStatus;
use DevLancer\MinecraftStatus\Result\StatusResultInterface;
use DevLancer\MinecraftStatus\StatusInterface;
use DevLancer\MinecraftStatus\StatusState;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('real-server')]
final class RealServerSocketTest extends TestCase
{
    private const CONFIG_PATH = __DIR__ . '/../../../.servers-list.php';

    public function testJavaStatusRealServers(): void
    {
        $this->assertRealServers('java_status', MinecraftJavaStatus::class);
    }

    public function testJavaQueryRealServers(): void
    {
        $this->assertRealServers('java_query', MinecraftJavaQuery::class);
    }

    public function testBedrockRealServers(): void
    {
        $this->assertRealServers('bedrock', MinecraftBedrockStatus::class);
    }

    public function testLegacyJavaRealServers(): void
    {
        $this->assertRealServers('legacy_java', MinecraftJavaPreOld17Status::class);
    }

    public function testSrvResolvedJavaStatusRealServers(): void
    {
        $servers = $this->serversFor('srv_java_status');
        $resolver = new SrvResolver();

        foreach ($servers as $server) {
            self::assertNotSame(
                [],
                $resolver->resolveCandidates($server['host']),
                sprintf('No SRV candidates found for "%s".', $server['host'])
            );

            $client = new MinecraftJavaStatus(
                $server['host'],
                $server['port'],
                $server['timeout'],
                true
            );

            $client->fetch();

            self::assertTrue($client->isResolveSRV());
            self::assertSame(StatusState::Fetched, $client->status(), 'srv_java_status did not reach fetched state.');
            $this->assertResultLooksFetched($client->getResult(), 'srv_java_status');
        }
    }

    /**
     * @param class-string<StatusInterface> $clientClass
     */
    private function assertRealServers(string $protocol, string $clientClass): void
    {
        $servers = $this->serversFor($protocol);

        foreach ($servers as $server) {
            $client = new $clientClass(
                $server['host'],
                $server['port'],
                $server['timeout'],
                false
            );

            $client->fetch();

            self::assertSame(StatusState::Fetched, $client->status(), $protocol . ' did not reach fetched state.');
            $this->assertResultLooksFetched($client->getResult(), $protocol);
        }
    }

    private function assertResultLooksFetched(StatusResultInterface $result, string $protocol): void
    {
        self::assertNotSame([], $result->raw(), $protocol . ' raw result should not be empty.');
        self::assertGreaterThanOrEqual(0, $result->onlinePlayers(), $protocol . ' online player count should be non-negative.');
        self::assertGreaterThanOrEqual(0, $result->maxPlayers(), $protocol . ' max player count should be non-negative.');
    }

    /**
     * @return list<array{host: string, port: int, timeout: int|float}>
     */
    private function serversFor(string $protocol): array
    {
        $config = $this->loadConfig();
        if (!array_key_exists($protocol, $config)) {
            self::markTestSkipped(sprintf('No "%s" section configured in %s.', $protocol, basename(self::CONFIG_PATH)));
        }

        $servers = $config[$protocol];
        if (!is_array($servers) || $servers === []) {
            self::markTestSkipped(sprintf('No real "%s" servers configured in %s.', $protocol, basename(self::CONFIG_PATH)));
        }

        $normalized = [];
        foreach ($servers as $index => $server) {
            if (!is_array($server)) {
                self::fail(sprintf('Invalid "%s" server config at index %d: expected array.', $protocol, $index));
            }

            $host = $server['host'] ?? null;
            $port = $server['port'] ?? null;
            $timeout = $server['timeout'] ?? 2.0;

            if (!is_string($host) || $host === '') {
                self::fail(sprintf('Invalid "%s" server config at index %d: host must be a non-empty string.', $protocol, $index));
            }

            if (!is_int($port) || $port < 1 || $port > 65535) {
                self::fail(sprintf('Invalid "%s" server config at index %d: port must be an integer between 1 and 65535.', $protocol, $index));
            }

            if ((!is_int($timeout) && !is_float($timeout)) || $timeout <= 0) {
                self::fail(sprintf('Invalid "%s" server config at index %d: timeout must be greater than zero.', $protocol, $index));
            }

            $normalized[] = [
                'host' => $host,
                'port' => $port,
                'timeout' => $timeout,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        if (!is_file(self::CONFIG_PATH)) {
            self::markTestSkipped(sprintf('Create %s to run real server smoke tests.', basename(self::CONFIG_PATH)));
        }

        $config = require self::CONFIG_PATH;
        if (!is_array($config)) {
            self::fail(sprintf('%s must return an array.', basename(self::CONFIG_PATH)));
        }

        return $config;
    }
}
