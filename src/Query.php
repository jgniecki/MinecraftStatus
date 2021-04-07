<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;


use DevLancer\MinecraftStatus\Exception\QueryException;
use InvalidArgumentException;

/**
 * Class QueryException
 * @package DevLancer\MinecraftStatus
 */
class Query implements StatusInterface
{
    /**
     * @var resource|null
     */
    protected $socket = null;

    /**
     * @var string
     */
    protected string $host;

    /**
     * @var int
     */

    protected int $port;

    /**
     * @var int
     */
    protected int $timeout;

    /**
     * @var bool
     */
    protected bool $resolveSRV;

    /**
     * @var string[]
     */
    protected array $info = [];

    /**
     * @var string[]
     */
    protected array $players = [];


    /**
     * QueryException constructor.
     * @param string $host
     * @param int $port
     * @param int $timeout
     * @param bool $resolveSRV
     * @throws InvalidArgumentException
     */
    public function __construct(string $host, int $port = 25565, int $timeout = 3, bool $resolveSRV = true)
    {
        $this->host = $host;
        $this->port = $port;
        $this->resolveSRV = $resolveSRV;

        $this->setTimeout($timeout);
    }

    /**
     *
     */
    public function __destruct()
    {
        if ($this->isConnected())
            fclose($this->socket);
    }

    /**
     * @return $this
     * @throws QueryException
     */
    public function connect(): self
    {
        $host = $this->host;
        if ($this->resolveSRV)
            $host = $this->resolveSRV($host)?? $host;

        $socket = @fsockopen('udp://' . $host, $this->port, $err_no, $err_str, $this->timeout);

        if( $err_no || $socket === false )
            throw new QueryException( 'Could not create socket: ' . $err_str );

        $this->socket = $socket;

        stream_set_timeout($this->socket, $this->timeout);
        stream_set_blocking($this->socket, true);


        try {
            $this->getStatus();
        } finally {
            fclose($this->socket);
            $this->socket = null;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return (bool) $this->socket;
    }

    /**
     * @return array
     */
    public function getPlayers(): array
    {
        return $this->isConnected()? $this->players : [];
    }

    /**
     * @return int
     */
    public function getCountPlayers(): int
    {
        if (!$this->isConnected() || !isset($this->info['numplayers']))
            return 0;

        return (int) $this->info['numplayers'];
    }

    /**
     * @return int
     */
    public function getMaxPlayers(): int
    {
        if (!$this->isConnected() || !isset($this->info['maxplayers']))
            return 0;

        return (int) $this->info['maxplayers'];
    }

    /**
     * @return array
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * @param int $timeout
     * @throws InvalidArgumentException
     */
    public function setTimeout(int $timeout): void
    {
        if ($timeout <= 0)
            throw new InvalidArgumentException("The timeout must be a positive integer.");

        $this->timeout = $timeout;
    }

    /**
     * @param string $host
     * @return string|null
     */
    protected function resolveSRV(string $host): ?string
    {
        if(ip2long($host) !== false)
            return null;

        $record = @dns_get_record( '_minecraft._tcp.' . $host, DNS_SRV );

        return $record[0]['target']?? null;
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @param int $command
     * @param string $append
     * @return string|null
     * @throws QueryException
     */
    protected function writeData(int $command, string $append = ""): ?string
    {
        $command = pack( 'c*', 0xFE, 0xFD, $command, 0x01, 0x02, 0x03, 0x04 ) . $append;
        $length  = strlen( $command );

        if($length !== fwrite($this->socket, $command, $length))
            throw new QueryException( "Failed to write on socket." );

        $data = fread($this->socket, 4096);

        if($data === false)
            throw new QueryException( "Failed to read from socket." );

        if(strlen($data) < 5 || $data[0] != $command[2])
            return null;

        return substr($data, 5);
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @return string
     * @throws QueryException
     */
    protected function getChallenge(): string
    {
        $data = $this->writeData(0x09);

        if(!$data)
            throw new QueryException('Failed to receive challenge.');

        return pack('N', $data);
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @throws QueryException
     */
    protected function getStatus()
    {
        $append = $this->getChallenge() . pack('c*', 0x00, 0x00, 0x00, 0x00);
        $data = $this->writeData(0x00, $append);

        if(!$data)
            throw new QueryException('Failed to receive status.' );

        $data = substr($data,11);
        $data = explode("\x00\x00\x01player_\x00\x00", $data);

        if(count($data) !== 2)
            throw new QueryException('Failed to parse server\'s response.');

        $players = substr($data[1], 0, -2);
        $data    = explode("\x00", $data[0]);

        $info = [];

        foreach ($data as $id => $value) {
            if ($id % 2 == 0)
                $key = $value;
            else
                $info[$key] = $value;
        }

        if (explode(".", $info['hostip'])[0] == "127")
            $info['hostip'] = gethostbyname($this->host);

        $this->info = (array) mb_convert_encoding($info, 'UTF-8');
        $this->players = empty($Players)? explode("\x00", $players) : [];
    }
}