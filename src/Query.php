<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DevLancer\MinecraftStatus;

use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;

class Query extends AbstractQuery implements PlayerListInterface
{
    /**
     * @var string[]
     */
    protected array $players = [];

    /**
     * @inheritDoc
     * @throws NotConnectedException
     */
    public function getPlayers(): array
    {
        if (!$this->isConnected())
            throw new NotConnectedException('The connection has not been established.');

        return $this->players;
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @throws ReceiveStatusException
     */
    protected function getStatus(): void
    {
        $append = $this->getChallenge() . \pack('c*', 0x00, 0x00, 0x00, 0x00);
        $data = $this->writeData(0x00, $append);

        if(!$data)
            throw new ReceiveStatusException('Failed to receive status.');

        $data = \substr($data, 11);
        $data = \explode("\x00\x00\x01player_\x00\x00", $data);

        if(\count($data) !== 2)
            throw new ReceiveStatusException('Failed to parse server\'s response.');

        if (is_string($data[1]))
            $this->players = $this->resolvePlayerList($data[1]);

        $data = \explode("\x00", $data[0]);
        $info = [];
        for ($i = 1; $i < \count($data); $i+=2)
            $info[$data[$i-1]] = $data[$i];

        $this->info = $this->encoding($info);
        $this->info['hostip'] = \gethostbyname($this->host);
    }

    /**
     * @param string $data
     * @return void
     */
    protected function resolvePlayerList(string $data): array
    {
        $players = \substr($data, 0, -2);
        $players = \explode("\x00", $players);
        return $this->encoding($players);
    }
}
