<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Dns;

interface DnsResolverInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSrvRecords(string $name): array;
}
