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
use DevLancer\MinecraftStatus\MinecraftJavaQuery;
use DevLancer\MinecraftStatus\Query;

require_once '../vendor/autoload.php';
echo "MinecraftStatus\n<br>";

$query = new MinecraftJavaQuery("mc.server-query.loc");

try {
    print_r($query->getInfo()); //Don't do it that way,
} catch (NotConnectedException $e) {
    echo $e->getMessage() . "\n<br>";
}

//Connection to the server
try {
    $query->connect();
} catch (ConnectionException $e) {
    //When the server is probably offline
    echo $e->getMessage() . "\n<br>";
} catch (ReceiveStatusException $e) {
    //When communication with the server failed
    //Query communication is probably disabled
    print_r($query->getInfo()); //Return empty array
    echo $e->getMessage() . "\n<br>";
}

if ($query->isConnected()) {
    echo sprintf("Server %s:%d is online", $query->getHost(), $query->getPort()) . "\n<br>";
    print_r($query->getInfo()); //When the array is empty, it probably failed to communicate properly with the server. Previously, a ReceiveStatusException exception was thrown
    print_r($query->getPlayers());
} else {
    echo sprintf("Server %s:%d is offline", $query->getHost(), $query->getPort()) . "\n<br>";
}
