<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DevLancer\MinecraftStatus;

trait ResolveSRVTrait
{
    /**
     * @param string $host
     * @return array<string, string|null>
     */
    protected function resolveSRV(string $host): array
    {
        if(ip2long($host) !== false)
            return ['host' => null, 'port' => null];

        $record = @dns_get_record( '_minecraft._tcp.' . $host, DNS_SRV );

        return [
            'host' => $record[0]['target'] ?? null,
            'port' => $record[0]['port'] ?? null
        ];
    }
}