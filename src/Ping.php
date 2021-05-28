<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;


use DevLancer\MinecraftStatus\Exception\PingException;
use InvalidArgumentException;

/**
 * Class Ping
 * @package DevLancer\MinecraftStatus
 */
class Ping implements StatusInterface
{
    /**
     * @var string|null
     */
    protected ?string $encoding = null;

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
     * @var resource|null
     */
    protected $socket = null;

    /**
     * Ping constructor.
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
        $this->setTimeout($timeout);
        $this->resolveSRV = $resolveSRV;
    }

    public function __destruct()
    {
        if ($this->isConnected())
            fclose($this->socket);
    }

    /**
     * @throws PingException
     */
    public function connect(): self
    {
        $host = $this->host;
        if ($this->resolveSRV)
            $host = $this->resolveSRV($host)?? $host;


        $socket = @fsockopen($host, $this->port, $err_no, $err_str, $this->timeout);

        if( $err_no || $socket === false )
            throw new PingException( 'Could not create socket: ' . $err_str );

        $this->socket = $socket;

        stream_set_timeout($this->socket, $this->timeout);

        $this->getStatus();
        return $this;
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @throws PingException
     */
    protected function getStatus()
    {
        $timestart = microtime(true); // for read timeout purposes

        $data = "\x00"; // packet ID = 0 (varint)
        $data .= "\x04"; // Protocol version (varint)
        $data .= pack('c', StrLen( $this->host)) . $this->host; // Server (varint len + UTF-8 addr)
        $data .= pack('n', $this->port); // Server port (unsigned short)
        $data .= "\x01"; // Next state: status (varint)
        $data = Pack('c', strlen($data)) . $data; // prepend length of packet ID + data

        fwrite($this->socket, $data); // handshake
        fwrite($this->socket, "\x01\x00"); // status ping

        $length = $this->readVarInt(); // full packet length
        if($length<10)
            return;

        $this->readVarInt(); // packet type, in server ping it's 0
        $length = $this->readVarInt(); // string length
        $data = "";

        do
        {
            if (microtime(true) - $timestart > $this->timeout)
                throw new PingException( 'Server read timed out' );

            $remainder = $length - strlen($data);
            $block = fread($this->socket, $remainder);

            if (!$block)
                throw new PingException( 'Server returned too few data' );

            $data .= $block;
        } while(strlen($data) < $length);

        $result = json_decode($data, true);

        if (isset($result['players']['sample'])) {
            foreach ($result['players']['sample'] as $value)
                $this->players[] = $value['name'];
        }

        $this->info = ($this->encoding)? (array) mb_convert_encoding($result, 'UTF-8', $this->encoding) : (array) mb_convert_encoding($result, 'UTF-8');
    }

    /**
     * @return int
     * @throws PingException
     */
    private function readVarInt( )
    {
        $i = 0;
        $j = 0;

        while(true)
        {
            $k = @fgetc( $this->socket );
            if($k === FALSE)
                return 0;

            $k = ord($k);
            $i |= ($k&0x7F) << $j++ * 7;

            if($j>5)
                throw new PingException( 'VarInt too big' );

            if(($k&0x80) != 128)
                break;
        }

        return $i;
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return is_resource($this->socket);
    }

    /**
     * @return array
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    /**
     * @return int
     */
    public function getCountPlayers(): int
    {
        if (!$this->isConnected() || !isset($this->info['players']['online']))
            return 0;

        return (int) $this->info['players']['online'];
    }

    /**
     * @return int
     */
    public function getMaxPlayers(): int
    {
        if (!$this->isConnected() || !isset($this->info['players']['max']))
            return 0;

        return (int) $this->info['players']['max'];
    }

    /**
     * @return string
     */
    public function getFavicon(): string
    {
        if (!$this->isConnected() || !isset($this->info['favicon']))
            return "";

        return $this->info['favicon'];
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
     * @return array
     */
    public function getInfo(): array
    {
        return $this->info;
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
     * @return string|null
     */
    public function getEncoding(): ?string
    {
        return $this->encoding;
    }

    /**
     * @param string|null $encoding
     */
    public function setEncoding(string $encoding): void
    {
        $this->encoding = $encoding;
    }
}