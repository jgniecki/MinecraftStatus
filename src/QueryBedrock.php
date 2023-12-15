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
 * Class QueryBedrock
 * @package DevLancer\MinecraftStatus
 */
class QueryBedrock extends Query
{
    /**
     * QueryBedrock constructor.
     *
     * @inheritDoc
     */
    public function __construct(string $host, int $port = 19132, int $timeout = 3, bool $resolveSRV = true)
    {
        parent::__construct($host, $port, $timeout, $resolveSRV);
    }

    /**
     * @inheritDoc
     * @return QueryBedrock
     * @throws Exception
     */
    public function connect(): QueryBedrock
    {
        return parent::connect();
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @inheritDoc
     */
    protected function getStatus(): void
    {
        $OFFLINE_MESSAGE_DATA_ID = \pack('c*', 0x00, 0xFF, 0xFF, 0x00, 0xFE, 0xFE, 0xFE, 0xFE, 0xFD, 0xFD, 0xFD, 0xFD, 0x12, 0x34, 0x56, 0x78);

        $command = \pack('cQ', 0x01, \time());
        $command .= $OFFLINE_MESSAGE_DATA_ID;
        $command .= \pack('Q', 2);
        $length  = \strlen($command);

        if($length !== \fwrite($this->socket, $command, $length))
            throw new Exception( "Failed to write on socket." );

        $data = \fread($this->socket, 4096);

        if($data === false)
            throw new Exception("Failed to read from socket." );

        if($data[ 0 ] !== "\x1C")
            throw new Exception("First byte is not ID_UNCONNECTED_PONG.");

        if(\substr($data, 17, 16 ) !== $OFFLINE_MESSAGE_DATA_ID)
            throw new Exception("Magic bytes do not match." );

        $info = $this->resolveStatus($data);
        $this->info = $this->encoding($info);
    }

    protected function resolveStatus(string $data): array
    {
        // TODO: What are the 2 bytes after the magic?
        $data = \substr($data, 35);
        $data = \explode(';', $data);
        $offset = \count($data) - 13;
        if ($offset < 0)
            $offset = 0;

        $info = [
            'game_id'          => $data[0] ?? null,
            'hostname'         => [],
            'protocol'         => $data[2+$offset] ?? null,
            'version'          => $data[3+$offset] ?? null,
            'numplayers'       => (isset($data[4+$offset]))? (int) $data[4+$offset] : 0,
            'maxplayers'       => (isset($data[5+$offset]))? (int) $data[5+$offset] : 0,
            'server_id'        => $data[6+$offset] ?? null,
            'map'              => $data[7+$offset] ?? null,
            'game_mode'        => $data[8+$offset] ?? null,
            'nintendo_limited' => $data[9+$offset] ?? null,
            'ipv4port'         => (isset($data[10+$offset]))? (int) $data[10+$offset] : 0,
            'ipv6port'         => (isset($data[11+$offset]))? (int) $data[11+$offset] : 0,
            'extra'            => $data[12+$offset] ?? null, // What is this?
        ];

        for ($i = 0; $i <= $offset; $i++)
            $info['hostname'][] = $data[1+$i];
        $info['hostname'] = implode(";", $info['hostname']);

        return $info;
    }
}