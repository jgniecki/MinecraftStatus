<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;


use DevLancer\MinecraftStatus\Exception\ConnectionException;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;

/**
 * Class AbstractQuery
 * @package DevLancer\MinecraftStatus
 */
abstract class AbstractQuery extends AbstractStatus
{
    /**
     * @inheritDoc
     * @return AbstractQuery
     * @throws ConnectionException Thrown when failed to connect to resource
     */
    public function connect(): StatusInterface
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
     * @throws NotConnectedException
     */
    public function getCountPlayers(): int
    {
        return (int) ($this->getInfo()['numplayers'] ?? 0);
    }

    /**
     * @return int
     * @throws NotConnectedException
     */
    public function getMaxPlayers(): int
    {
        return (int) ($this->getInfo()['maxplayers'] ?? 0);
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @param int $command
     * @param string $append
     * @return string|null
     * @throws ReceiveStatusException
     */
    protected function writeData(int $command, string $append = ""): ?string
    {
        $command = \pack('c*', 0xFE, 0xFD, $command, 0x01, 0x02, 0x03, 0x04) . $append;
        $length  = \strlen($command);

        if($length !== \fwrite($this->socket, $command, $length))
            throw new ReceiveStatusException( "Failed to write on socket.");

        $data = \fread($this->socket, 4096);

        if($data === false)
            throw new ReceiveStatusException( "Failed to read from socket.");

        if(\strlen($data) < 5 || $data[0] != $command[2])
            return null;

        return \substr($data, 5);
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @return string
     * @throws ReceiveStatusException
     */
    protected function getChallenge(): string
    {
        $data = $this->writeData(0x09);

        if(!$data)
            throw new ReceiveStatusException('Failed to receive challenge.');

        return \pack('N', $data);
    }

    abstract protected function getStatus(): void;

    /**
     * @return string
     * @throws NotConnectedException
     */
    public function getMotd(): string
    {
        return $this->getInfo()['hostname'] ?? "";
    }
}