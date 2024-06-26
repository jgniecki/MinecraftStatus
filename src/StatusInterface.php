<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright Jakub Gniecki
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;

/**
 * Interface StatusInterface
 * @package DevLancer\MinecraftStatus
 */
interface StatusInterface
{
    /**
     * Attempts to connect to the given host.
     * @return StatusInterface
     */
    public function connect(): StatusInterface;

    /**
     * Returns information about whether the connection was successful, it can also tell if the server is online
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Returns the potential number of players
     * @return int
     */
    public function getCountPlayers(): int;

    /**
     * Returns the number of slots
     * @return int
     */
    public function getMaxPlayers(): int;

    /**
     * Sets the time to get resources
     * @param int $timeout
     */
    public function setTimeout(int $timeout): void;

    /**
     * Gets server motd
     * @return string
     */
    public function getMotd(): string;

    /**
     * Returns the obtained host information
     * @return array<string, mixed>
     */
    public function getInfo(): array;
}
