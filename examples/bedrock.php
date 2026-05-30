<?php declare(strict_types=1);

use DevLancer\MinecraftStatus\Exception\ConnectionException;
use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\Exception\TimeoutException;
use DevLancer\MinecraftStatus\MinecraftBedrockStatus;

require_once __DIR__ . '/../vendor/autoload.php';

$status = new MinecraftBedrockStatus('mc.server-bedrock.loc');

try {
    $result = $status->fetch()->getResult();

    echo sprintf(
        "Server %s:%d is online\n",
        $status->getHost(),
        $status->getPort()
    );
    echo sprintf("MOTD: %s\n", $result->motd());
    echo sprintf("Players: %d/%d\n", $result->onlinePlayers(), $result->maxPlayers());
    echo sprintf("Version: %s\n", $result->version ?? 'unknown');
    echo sprintf("Protocol: %d\n", $result->protocol);

    print_r($result->raw());
} catch (ConnectionException $exception) {
    echo "Connection failed: " . $exception->getMessage() . "\n";
} catch (TimeoutException $exception) {
    echo "Server read timed out: " . $exception->getMessage() . "\n";
} catch (ProtocolException | InvalidResponseException $exception) {
    echo "Invalid server response: " . $exception->getMessage() . "\n";
} catch (ReceiveStatusException $exception) {
    echo "Status could not be received: " . $exception->getMessage() . "\n";
}
