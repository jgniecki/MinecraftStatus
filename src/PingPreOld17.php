<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;


/**
 * Class PingPreOld17
 * @package DevLancer\MinecraftStatus
 */
class PingPreOld17 extends Ping
{
    /**
     * @inheritDoc
     */
    public function getStatus()
    {
        fwrite($this->socket, "\xFE\x01");
        $data = fread($this->socket, 512);
        $length = strLen($data);

        if( $length < 4 || $data[0] !== "\xFF" )
            return;

        $data = substr( $data, 3 ); // Strip packet header (kick message packet and short length)
        $data = iconv( 'UTF-16BE', 'UTF-8', $data );

        // Are we dealing with Minecraft 1.4+ server?
        if($data[ 1 ] === "\xA7" && $data[ 2 ] === "\x31")
        {
            $data = explode( "\x00", $data );
            $result['description']['text'] = $data[3];
            $result['players'] = [
                "max" => $data[5],
                "online" => $data[4],
                "sample" => []
            ];
            $result['version'] = [
                'name' => $data[2],
                'protocol' => $data[1]
            ];

            $this->info = $result;
            return;
        }

        $data = explode( "\xA7", $data );

        $result['description']['text'] = substr($data[0], 0, -1);
        $result['players'] = [
            "max" =>  $data[2]?? 0,
            "online" => $data[1]?? 0,
            "sample" => []
        ];
        $result['version'] = [
            'name' => '1.3',
            'protocol' => 0
        ];

        $this->info = $result;
    }
}