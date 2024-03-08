<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;


use DevLancer\MinecraftStatus\Exception\ConnectionException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;

/**
 * Class PingPreOld17
 * @package DevLancer\MinecraftStatus
 */
class PingPreOld17 extends AbstractPing
{
    /**
     * @inheritDoc
     * @return PingPreOld17
     * @throws ConnectionException Thrown when failed to connect to resource
     * @throws ReceiveStatusException Thrown when the status has not been obtained or resolved
     */
    public function connect(): PingPreOld17
    {
        parent::connect();
        return $this;
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     *
     * @throws ReceiveStatusException
     */
    protected function getStatus(): void
    {
        \fwrite($this->socket, "\xFE\x01");
        $data = \fread($this->socket, 512);
        if(empty($data))
            throw new ReceiveStatusException('Failed to receive status.');

        $length = \strlen($data);
        if( $length < 4 || $data[0] !== "\xFF" )
            throw new ReceiveStatusException('Failed to receive status.');


        $data = \substr($data, 3); // Strip packet header (kick message packet and short length)
        $data = \iconv('UTF-16BE', 'UTF-8', $data);

        // Are we dealing with Minecraft 1.4+ server?
        if($data[1] === "\xA7" && $data[2] === "\x31") {
            $data = \explode( "\x00", $data);
            $result['description']['text'] = $data[3] ?? null;
            $result['players'] = [
                "max" => (int) ($data[5] ?? 0),
                "online" => (int) ($data[4] ?? 0),
            ];

            $result['version'] = [
                'name' => $data[2] ?? null,
                'protocol' => (int) ($data[1] ?? 0)
            ];

            $this->info = $this->encoding($result);
            return;
        }

        $data = \explode("\xA7", $data);
        $result['description']['text'] = \substr($data[0], 0, -1);
        $result['players'] = [
            "max" => (int) ($data[2] ?? 0),
            "online" => (int) ($data[1] ?? 0),
        ];

        $this->info = $this->encoding($result);
    }
}