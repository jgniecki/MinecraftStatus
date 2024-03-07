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

class Ping extends AbstractPing implements PlayerListInterface, FaviconInterface
{
    /**
     * @var string[]
     */
    protected array $players = [];

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @throws ReceiveStatusException*
     */
    protected function getStatus(): void
    {
        $data = "\x00"; // packet ID = 0 (varint)
        $data .= "\xff\xff\xff\xff\x0f"; //Protocol version (varint)
        $data .= \pack('c', \strlen( $this->host)) . $this->host; // Server (varint len + UTF-8 addr)
        $data .= \pack('n', $this->port); // Server port (unsigned short)
        $data .= "\x01"; // Next state: status (varint)
        $data = \pack('c', \strlen($data)) . $data; // prepend length of packet ID + data
        $timestart = \microtime(true); // for read timeout purposes
        \fwrite($this->socket, $data . "\x01\x00"); // handshake

        $length = $this->readVarInt(); // full packet length
        if($length < 10)
            throw new ReceiveStatusException('Failed to receive status.');

        $this->readVarInt(); // packet type, in server ping it's 0
        $length = $this->readVarInt(); // string length
        if($length < 2)
            throw new ReceiveStatusException('Failed to receive status.');

        $data = "";

        do {
            if (\microtime(true) - $timestart > $this->timeout)
                throw new ReceiveStatusException( 'Server read timed out' );

            $remainder = $length - \strlen($data);
            if ($remainder <= 0)
                break;

            $block = \fread($this->socket, $remainder);
            if ($this->delay == 0)
                $this->delay = (int) floor((microtime(true) - $timestart) * 1000);

            if (!$block)
                throw new ReceiveStatusException( 'Server returned too few data' );

            $data .= $block;
        } while(\strlen($data) < $length);
        $result = \json_decode($data, true);
        if (\json_last_error() !== JSON_ERROR_NONE)
            throw new ReceiveStatusException( 'JSON parsing failed: ' . \json_last_error_msg( ) );

        if (!\is_array($result))
            throw new ReceiveStatusException( 'The server did not return the information' );

        $result = $this->encoding($result);
        $this->players = $this->resolvePlayerList($result);
        $this->info = $result;
    }

    /**
     * @param array $data<string, mixed>
     * @return void
     */
    protected function resolvePlayerList(array $data): array
    {
        if (isset($data['players']['sample'])) {
            foreach ($data['players']['sample'] as $value)
                $this->players[] = $value;
        }

        return [];
    }

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
     * @return string
     * @throws NotConnectedException
     */
    public function getFavicon(): string
    {
        return $this->getInfo()['favicon'] ?? "";
    }
}