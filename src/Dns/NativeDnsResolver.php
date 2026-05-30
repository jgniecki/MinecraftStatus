<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Dns;

final class NativeDnsResolver implements DnsResolverInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSrvRecords(string $name): array
    {
        $records = @dns_get_record($name, DNS_SRV);

        if (!is_array($records)) {
            return [];
        }

        return $records;
    }
}
