<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;


use DevLancer\MinecraftStatus\Exception\Exception;
use InvalidArgumentException;

/**
 * Class Ping
 * @package DevLancer\MinecraftStatus
 */
class Ping extends AbstractStatus
{
    /**
     * @return StatusInterface
     * @throws Exception
     */
    public function connect(): self
    {
        if ($this->isConnected())
            $this->disconnect();

        $this->_connect($this->host, $this->port);
        $this->getStatus();
        return $this;
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @throws Exception
     */
    protected function getStatus(): void
    {
        $timestart = \microtime(true); // for read timeout purposes

        $data = "\x00"; // packet ID = 0 (varint)
        $data .= "\x04"; // Protocol version (varint)
        $data .= \pack('c', \strlen( $this->host)) . $this->host; // Server (varint len + UTF-8 addr)
        $data .= \pack('n', $this->port); // Server port (unsigned short)
        $data .= "\x01"; // Next state: status (varint)
        $data = \pack('c', \strlen($data)) . $data; // prepend length of packet ID + data

        \fwrite($this->socket, $data); // handshake
        \fwrite($this->socket, "\x01\x00"); // status ping

        $length = $this->readVarInt(); // full packet length
        if($length < 10)
            throw new Exception('Failed to receive status.');

        $this->readVarInt(); // packet type, in server ping it's 0
        $length = $this->readVarInt(); // string length
        $data = "";

        do {
            if (\microtime(true) - $timestart > $this->timeout)
                throw new Exception( 'Server read timed out' );

            $remainder = $length - \strlen($data);
            $block = \fread($this->socket, $remainder);
            if (!$block)
                throw new Exception( 'Server returned too few data' );

            $data .= $block;
        } while(\strlen($data) < $length);

        $result = \json_decode($data, true);
        $result = $this->encoding($result);

        if (isset($result['players']['sample'])) {
            foreach ($result['players']['sample'] as $value)
                $this->players[] = $value['name'];
        }

        $this->info = $result;
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @return int
     * @throws Exception
     */
    private function readVarInt(): int
    {
        $i = 0;
        $j = 0;

        while(true) {
            $k = @\fgetc($this->socket);
            if($k === false)
                return 0;

            $k = \ord($k);
            $i |= ($k&0x7F) << $j++ * 7;

            if($j>5)
                throw new Exception( 'VarInt too big' );

            if(($k&0x80) != 128)
                break;
        }

        return $i;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getCountPlayers(): int
    {
        return (int) $this->getInfo()['players']['online'] ?? 0;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getMaxPlayers(): int
    {
        return (int) $this->getInfo()['players']['max'] ?? 0;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getFavicon(): string
    {
        return $this->getInfo()['favicon'] ?? "";
    }
}