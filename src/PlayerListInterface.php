<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DevLancer\MinecraftStatus;

interface PlayerListInterface
{
    /**
     * Returns a potential list of players
     * @return string[]
     */
    public function getPlayers(): array;
}
