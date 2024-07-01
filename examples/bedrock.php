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
use DevLancer\MinecraftStatus\QueryBedrock;

require_once '../vendor/autoload.php';
echo "MinecraftStatus\n<br>";

$queryBedrock = new QueryBedrock("mc.server-bedrock.loc");

try {
    print_r($queryBedrock->getInfo()); //Don't do it that way,
} catch (NotConnectedException $e) {
    echo $e->getMessage() . "\n<br>";
}

//Connection to the server
try {
    $queryBedrock->connect();
} catch (ConnectionException $e) {
    //When the server is probably offline
    echo $e->getMessage() . "\n<br>";
} catch (ReceiveStatusException $e) {
    //When communication with the server failed
    print_r($queryBedrock->getInfo()); //Return empty array
    echo $e->getMessage() . "\n<br>";
}

if ($queryBedrock->isConnected()) {
    echo sprintf("Server %s:%d is online", $queryBedrock->getHost(), $queryBedrock->getPort()) . "\n<br>";
    print_r($queryBedrock->getInfo()); //When the array is empty, it probably failed to communicate properly with the server. Previously, a ReceiveStatusException exception was thrown
} else {
    echo sprintf("Server %s:%d is offline", $queryBedrock->getHost(), $queryBedrock->getPort()) . "\n<br>";
}
