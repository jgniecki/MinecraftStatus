<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
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
     * @return mixed
     */
    public function connect();

    /**
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * @return string[]
     */
    public function getPlayers(): array;

    /**
     * @return int
     */
    public function getCountPlayers(): int;

    /**
     * @return int
     */
    public function getMaxPlayers(): int;

    /**
     * @param int $timeout
     */
    public function setTimeout(int $timeout): void;

    /**
     * @return string[]
     */
    public function getInfo():array;
}