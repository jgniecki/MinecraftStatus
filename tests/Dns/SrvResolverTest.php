<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Dns;

use DevLancer\MinecraftStatus\AbstractStatus;
use DevLancer\MinecraftStatus\Dns\DnsResolverInterface;
use DevLancer\MinecraftStatus\Dns\SrvResolver;
use PHPUnit\Framework\TestCase;

final class SrvResolverTest extends TestCase
{
    public function testIpv4HostDoesNotUseSrvLookup(): void
    {
        $dnsResolver = new FakeDnsResolver();
        $srvResolver = new SrvResolver($dnsResolver);

        self::assertSame(['host' => '127.0.0.1', 'port' => 25565], $srvResolver->resolve('127.0.0.1', 25565));
        self::assertSame([], $dnsResolver->queries);
    }

    public function testIpv6HostDoesNotUseSrvLookup(): void
    {
        $dnsResolver = new FakeDnsResolver();
        $srvResolver = new SrvResolver($dnsResolver);

        self::assertSame(['host' => '::1', 'port' => 25565], $srvResolver->resolve('::1', 25565));
        self::assertSame([], $dnsResolver->queries);
    }

    public function testMissingRecordsFallsBackToOriginalHostAndPort(): void
    {
        $dnsResolver = new FakeDnsResolver();
        $srvResolver = new SrvResolver($dnsResolver);

        self::assertSame(['host' => 'example.org', 'port' => 25565], $srvResolver->resolve('example.org', 25565));
        self::assertSame(['_minecraft._tcp.example.org'], $dnsResolver->queries);
    }

    public function testRecordWithoutTargetIsIgnored(): void
    {
        $srvResolver = new SrvResolver(new FakeDnsResolver([
            '_minecraft._tcp.example.org' => [
                ['port' => 25566, 'pri' => 0, 'weight' => 1],
            ],
        ]));

        self::assertSame([], $srvResolver->resolveCandidates('example.org'));
    }

    public function testRecordWithoutPortIsIgnored(): void
    {
        $srvResolver = new SrvResolver(new FakeDnsResolver([
            '_minecraft._tcp.example.org' => [
                ['target' => 'srv.example.org', 'pri' => 0, 'weight' => 1],
            ],
        ]));

        self::assertSame([], $srvResolver->resolveCandidates('example.org'));
    }

    public function testRecordWithoutPriorityOrWeightIsIgnored(): void
    {
        $srvResolver = new SrvResolver(new FakeDnsResolver([
            '_minecraft._tcp.example.org' => [
                ['target' => 'missing-priority.example.org', 'port' => 25566, 'weight' => 1],
                ['target' => 'missing-weight.example.org', 'port' => 25567, 'pri' => 0],
            ],
        ]));

        self::assertSame([], $srvResolver->resolveCandidates('example.org'));
    }

    public function testTargetTrailingDotIsNormalized(): void
    {
        $srvResolver = new SrvResolver(new FakeDnsResolver([
            '_minecraft._tcp.example.org' => [
                ['target' => 'srv.example.org.', 'port' => 25566, 'pri' => 0, 'weight' => 1],
            ],
        ]));

        self::assertSame(
            [['host' => 'srv.example.org', 'port' => 25566, 'priority' => 0, 'weight' => 1]],
            $srvResolver->resolveCandidates('example.org')
        );
    }

    public function testRecordsAreSortedByPriorityAscending(): void
    {
        $srvResolver = new SrvResolver(new FakeDnsResolver([
            '_minecraft._tcp.example.org' => [
                ['target' => 'priority-two.example.org', 'port' => 25567, 'pri' => 2, 'weight' => 10],
                ['target' => 'priority-one.example.org', 'port' => 25566, 'pri' => 1, 'weight' => 1],
            ],
        ]));

        self::assertSame(
            ['priority-one.example.org', 'priority-two.example.org'],
            array_column($srvResolver->resolveCandidates('example.org'), 'host')
        );
    }

    public function testRecordsWithSamePriorityAreSortedByWeightDescending(): void
    {
        $srvResolver = new SrvResolver(new FakeDnsResolver([
            '_minecraft._tcp.example.org' => [
                ['target' => 'weight-one.example.org', 'port' => 25566, 'pri' => 1, 'weight' => 1],
                ['target' => 'weight-ten.example.org', 'port' => 25567, 'pri' => 1, 'weight' => 10],
            ],
        ]));

        self::assertSame(
            ['weight-ten.example.org', 'weight-one.example.org'],
            array_column($srvResolver->resolveCandidates('example.org'), 'host')
        );
    }

    public function testResolveChoosesFirstSortedCandidate(): void
    {
        $srvResolver = new SrvResolver(new FakeDnsResolver([
            '_minecraft._tcp.example.org' => [
                ['target' => 'fallback.example.org', 'port' => 25567, 'pri' => 10, 'weight' => 100],
                ['target' => 'winner.example.org', 'port' => 25566, 'pri' => 1, 'weight' => 1],
            ],
        ]));

        self::assertSame(['host' => 'winner.example.org', 'port' => 25566], $srvResolver->resolve('example.org', 25565));
    }

    public function testConstructorUsesSrvResolverWhenResolveSrvIsEnabled(): void
    {
        $dnsResolver = new FakeDnsResolver([
            '_minecraft._tcp.example.org' => [
                ['target' => 'srv.example.org', 'port' => 25566, 'pri' => 0, 'weight' => 1],
            ],
        ]);

        $client = new SrvAwareStatusDouble(new SrvResolver($dnsResolver), true);

        self::assertSame('srv.example.org', $client->getHost());
        self::assertSame(25566, $client->getPort());
        self::assertSame(['_minecraft._tcp.example.org'], $dnsResolver->queries);
    }

    public function testConstructorDoesNotUseSrvResolverWhenResolveSrvIsDisabled(): void
    {
        $dnsResolver = new FakeDnsResolver([
            '_minecraft._tcp.example.org' => [
                ['target' => 'srv.example.org', 'port' => 25566, 'pri' => 0, 'weight' => 1],
            ],
        ]);

        $client = new SrvAwareStatusDouble(new SrvResolver($dnsResolver), false);

        self::assertSame('example.org', $client->getHost());
        self::assertSame(25565, $client->getPort());
        self::assertSame([], $dnsResolver->queries);
    }
}

final class FakeDnsResolver implements DnsResolverInterface
{
    /**
     * @var list<string>
     */
    public array $queries = [];

    /**
     * @param array<string, array<int, array<string, mixed>>> $recordsByName
     */
    public function __construct(private readonly array $recordsByName = [])
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSrvRecords(string $name): array
    {
        $this->queries[] = $name;

        return $this->recordsByName[$name] ?? [];
    }
}

final class SrvAwareStatusDouble extends AbstractStatus
{
    public function __construct(private readonly SrvResolver $srvResolver, bool $resolveSRV)
    {
        parent::__construct('example.org', 25565, 3, $resolveSRV);
    }

    public function getCountPlayers(): int
    {
        return 0;
    }

    public function getMaxPlayers(): int
    {
        return 0;
    }

    public function getMotd(): string
    {
        return '';
    }

    protected function createSrvResolver(): SrvResolver
    {
        return $this->srvResolver;
    }

    protected function getStatus(): void
    {
        $this->info = [];
    }
}
