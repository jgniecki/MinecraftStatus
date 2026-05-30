<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Dns;

final class SrvResolver
{
    private DnsResolverInterface $dnsResolver;

    public function __construct(?DnsResolverInterface $dnsResolver = null)
    {
        $this->dnsResolver = $dnsResolver ?? new NativeDnsResolver();
    }

    /**
     * @return array{host: string, port: int}
     */
    public function resolve(string $host, int $port): array
    {
        $candidates = $this->resolveCandidates($host);

        if ($candidates === []) {
            return [
                'host' => $host,
                'port' => $port,
            ];
        }

        return [
            'host' => $candidates[0]['host'],
            'port' => $candidates[0]['port'],
        ];
    }

    /**
     * @return list<array{host: string, port: int, priority: int, weight: int}>
     */
    public function resolveCandidates(string $host): array
    {
        if (inet_pton($host) !== false) {
            return [];
        }

        $records = $this->dnsResolver->getSrvRecords('_minecraft._tcp.' . $host);
        $candidates = [];

        foreach ($records as $record) {
            $candidate = $this->normalizeRecord($record);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        usort(
            $candidates,
            static function (array $first, array $second): int {
                if ($first['priority'] !== $second['priority']) {
                    return $first['priority'] <=> $second['priority'];
                }

                return $second['weight'] <=> $first['weight'];
            }
        );

        return $candidates;
    }

    /**
     * @param array<string, mixed> $record
     * @return array{host: string, port: int, priority: int, weight: int}|null
     */
    private function normalizeRecord(array $record): ?array
    {
        if (!isset($record['target'], $record['port'], $record['pri'], $record['weight'])) {
            return null;
        }

        if (!is_string($record['target']) || $record['target'] === '') {
            return null;
        }

        if (!is_numeric($record['port']) || !is_numeric($record['pri']) || !is_numeric($record['weight'])) {
            return null;
        }

        $host = rtrim($record['target'], '.');
        $port = (int)$record['port'];
        $priority = (int)$record['pri'];
        $weight = (int)$record['weight'];

        if ($host === '' || $port < 1 || $port > 65535 || $priority < 0 || $weight < 0) {
            return null;
        }

        return [
            'host' => $host,
            'port' => $port,
            'priority' => $priority,
            'weight' => $weight,
        ];
    }
}
