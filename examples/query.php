<?php declare(strict_types=1);

use DevLancer\MinecraftStatus\Exception\ConnectionException;
use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\Exception\TimeoutException;
use DevLancer\MinecraftStatus\MinecraftJavaQuery;

require_once __DIR__ . '/../vendor/autoload.php';

$query = new MinecraftJavaQuery('mc.server-query.loc');

try {
    $result = $query->fetch()->getResult();

    echo sprintf(
        "Server %s:%d answered Query\n",
        $query->getHost(),
        $query->getPort()
    );
    echo sprintf("MOTD: %s\n", $result->motd());
    echo sprintf("Players: %d/%d\n", $result->onlinePlayers(), $result->maxPlayers());
    echo sprintf("Host IP: %s\n", $result->hostIp);

    print_r($result->players);
    print_r($result->raw());
} catch (ConnectionException $exception) {
    echo "Connection failed: " . $exception->getMessage() . "\n";
} catch (TimeoutException $exception) {
    echo "Server read timed out: " . $exception->getMessage() . "\n";
} catch (ProtocolException | InvalidResponseException $exception) {
    echo "Invalid Query response: " . $exception->getMessage() . "\n";
} catch (ReceiveStatusException $exception) {
    echo "Query could not be received. Check enable-query and query.port: " . $exception->getMessage() . "\n";
}
