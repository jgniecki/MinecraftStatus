<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;


use DevLancer\MinecraftStatus\Exception\Exception;

/**
 * Class QueryException
 * @package DevLancer\MinecraftStatus
 */
class Query extends AbstractStatus
{
    /**
     * @inheritDoc
     * @return StatusInterface
     * @throws Exception
     */
    public function connect(): self
    {
        if ($this->isConnected())
            $this->disconnect();

        $this->_connect('udp://' . $this->host, $this->port);
        \stream_set_blocking($this->socket, true);
        $this->getStatus();
        return $this;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getCountPlayers(): int
    {
        return (int) $this->getInfo()['numplayers'] ?? 0;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getMaxPlayers(): int
    {
        return (int) $this->getInfo()['maxplayers'] ?? 0;
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @param int $command
     * @param string $append
     * @return string|null
     * @throws Exception
     */
    protected function writeData(int $command, string $append = ""): ?string
    {
        $command = \pack('c*', 0xFE, 0xFD, $command, 0x01, 0x02, 0x03, 0x04) . $append;
        $length  = \strlen($command);

        if($length !== \fwrite($this->socket, $command, $length))
            throw new Exception( "Failed to write on socket." );

        $data = \fread($this->socket, 4096);

        if($data === false)
            throw new Exception( "Failed to read from socket." );

        if(\strlen($data) < 5 || $data[0] != $command[2])
            return null;

        return \substr($data, 5);
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @return string
     * @throws Exception
     */
    protected function getChallenge(): string
    {
        $data = $this->writeData(0x09);

        if(!$data)
            throw new Exception('Failed to receive challenge.');

        return \pack('N', $data);
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @throws Exception
     */
    protected function getStatus(): void
    {
        $append = $this->getChallenge() . \pack('c*', 0x00, 0x00, 0x00, 0x00);
        $data = $this->writeData(0x00, $append);

        if(!$data)
            throw new Exception('Failed to receive status.' );

        $data = \substr($data,11);
        $data = \explode("\x00\x00\x01player_\x00\x00", $data);

        if(\count($data) !== 2)
            throw new Exception('Failed to parse server\'s response.');

        $players = \substr($data[1], 0, -2);
        $data    = \explode("\x00", $data[0]);

        $info = [];
        foreach ($data as $id => $value) {
            if ($id % 2 == 0)
                $key = $value;
            else
                $info[$key] = $value;
        }

        //TODO: Test encoding
        $this->info = $this->encoding($info);
        $this->info['hostip'] = \gethostbyname($this->host);
        if (!empty($players))
            $this->players = $this->encoding(\explode("\x00", $players));
    }
}