<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DevLancer\MinecraftStatus;

use DevLancer\MinecraftStatus\Exception\NotConnectedException;

interface FaviconInterface
{
    /**
     * @return string
     * @throws NotConnectedException
     */
    public function getFavicon(): string;
}