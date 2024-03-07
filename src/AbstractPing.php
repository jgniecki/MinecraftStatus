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
 * Class AbstractPing
 * @package DevLancer\MinecraftStatus
 */
abstract class AbstractPing extends AbstractStatus implements DelayInterface
{
    protected int $delay = 0;

    /**
     * @inheritDoc
     * @return AbstractPing
     * @throws ConnectionException Thrown when failed to connect to resource
     * @throws ReceiveStatusException Thrown when the status has not been obtained or resolved
     */
    public function connect(): self
    {
        if ($this->isConnected())
            $this->disconnect();

        $this->_connect($this->host, $this->port);
        $this->getStatus();
        return $this;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    abstract protected function getStatus();

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @return int
     * @throws ReceiveStatusException
     */
    protected function readVarInt(): int
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
                throw new ReceiveStatusException( 'VarInt too big' );

            if(($k&0x80) != 128)
                break;
        }

        return $i;
    }

    /**
     * @return int
     * @throws NotConnectedException
     */
    public function getCountPlayers(): int
    {
        return (int) ($this->getInfo()['players']['online'] ?? 0);
    }

    /**
     * @return int
     * @throws NotConnectedException
     */
    public function getMaxPlayers(): int
    {
        return (int) ($this->getInfo()['players']['max'] ?? 0);
    }
}