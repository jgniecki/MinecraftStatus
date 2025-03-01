<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use DevLancer\MinecraftStatus\Exception\ConnectionException;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\MinecraftJavaStatus;

require_once '../vendor/autoload.php';
echo "MinecraftStatus\n<br>";

$ping = new MinecraftJavaStatus("mc.server-ping.loc");

try {
    print_r($ping->getInfo()); //Don't do it that way,
} catch (NotConnectedException $e) {
    echo $e->getMessage() . "\n<br>";
}

//Connection to the server
try {
    $ping->connect();
} catch (ConnectionException $e) {
    //When the server is probably offline
    echo $e->getMessage() . "\n<br>";
} catch (ReceiveStatusException $e) {
    //When communication with the server failed
    print_r($ping->getInfo()); //Return empty array
    echo $e->getMessage() . "\n<br>";
}

if ($ping->isConnected()) {
    echo sprintf("Server %s:%d is online", $ping->getHost(), $ping->getPort()) . "\n<br>";
    print_r($ping->getInfo()); //When the array is empty, it probably failed to communicate properly with the server. Previously, a ReceiveStatusException exception was thrown
    if ($ping->getInfo())
        echo '<img width="64" height="64" src="' . str_replace("\n", "", $ping->getFavicon()) . '">';
    print_r($ping->getPlayers());
} else {
    echo sprintf("Server %s:%d is offline", $ping->getHost(), $ping->getPort()) . "\n<br>";
}
