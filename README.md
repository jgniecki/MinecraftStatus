# Minecraft Status

![](https://img.shields.io/packagist/l/dev-lancer/minecraft-status?style=for-the-badge)
![](https://img.shields.io/packagist/dt/dev-lancer/minecraft-status?style=for-the-badge)
![](https://img.shields.io/github/v/release/DeveloperLancer/MinecraftStatus?style=for-the-badge)
![](https://img.shields.io/packagist/php-v/dev-lancer/minecraft-status?style=for-the-badge)

Minecraft Status is a PHP 8.1+ library for reading Minecraft server information without running a Minecraft client.

Use it when you want to show server availability, MOTD, player counts, version, protocol, favicon, player samples or Query data in a website, panel, bot, monitor or backend job.

The library supports:

- Java Edition status ping, used by the Minecraft multiplayer server list;
- Java Edition GameSpy4 Query, when `enable-query=true` is enabled on the server;
- Bedrock Edition RakNet unconnected ping/pong;
- legacy Java ping for servers before Minecraft 1.7.

## Installation

Install the package with Composer:

```bash
composer require dev-lancer/minecraft-status
```

Requirements:

- PHP `^8.1`;
- `ext-json`;
- `ext-iconv`;
- `ext-mbstring`.

## The Basic Flow

Every client follows the same shape: instantiate it for a host, call `fetch()`, then read the typed result with `getResult()`. Use `raw()` or `getInfo()` only when you need the parsed protocol array.

```php
use DevLancer\MinecraftStatus\MinecraftJavaStatus;

$status = new MinecraftJavaStatus('mc.example.com');
$result = $status->fetch()->getResult();

echo $result->motd();
echo $result->onlinePlayers() . '/' . $result->maxPlayers();
```

`fetch()` returns the same client instance, so chaining is safe:

```php
$result = (new MinecraftJavaStatus('mc.example.com'))
    ->fetch()
    ->getResult();
```

## Your First Java Status Check

Most Java Edition servers should be queried with `MinecraftJavaStatus`. It uses the same TCP status ping that the Minecraft multiplayer screen uses.

```php
<?php declare(strict_types=1);

use DevLancer\MinecraftStatus\Exception\ConnectionException;
use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\Exception\TimeoutException;
use DevLancer\MinecraftStatus\MinecraftJavaStatus;

require_once __DIR__ . '/vendor/autoload.php';

$status = new MinecraftJavaStatus('mc.example.com');

try {
    $result = $status->fetch()->getResult();

    echo "Server is online\n";
    echo "MOTD: " . $result->motd() . "\n";
    echo "Players: " . $result->onlinePlayers() . '/' . $result->maxPlayers() . "\n";
    echo "Version: " . ($result->versionName ?? 'unknown') . "\n";
    echo "Protocol: " . $result->protocol . "\n";
} catch (ConnectionException $exception) {
    echo "Connection failed: " . $exception->getMessage() . "\n";
} catch (TimeoutException $exception) {
    echo "Server read timed out: " . $exception->getMessage() . "\n";
} catch (ProtocolException | InvalidResponseException $exception) {
    echo "Invalid server response: " . $exception->getMessage() . "\n";
} catch (ReceiveStatusException $exception) {
    echo "Status could not be received: " . $exception->getMessage() . "\n";
}
```

The repository also contains a fuller Java example in [examples/ping.php](examples/ping.php).

## Choosing The Right Client

Pick the client by protocol, not only by server edition:

| Client | Use when | Transport | Default port |
|---|---|---|---:|
| `MinecraftJavaStatus` | You want normal Java Edition server list status | TCP | 25565 |
| `MinecraftJavaQuery` | You know GameSpy4 Query is enabled | UDP | 25565 |
| `MinecraftBedrockStatus` | You query a Bedrock Edition server | UDP | 19132 |
| `MinecraftJavaPreOld17Status` | You query a pre-1.7 Java server | TCP | 25565 |

Start with `MinecraftJavaStatus` for Java servers. Query is optional server functionality and is commonly disabled on public servers.

## Reading Typed Results

`getResult()` returns a result object. Every result object has these methods:

```php
raw(): array
motd(): string
onlinePlayers(): int
maxPlayers(): int
```

The common methods are enough for many dashboards:

```php
$result = $status->fetch()->getResult();

$isFull = $result->onlinePlayers() >= $result->maxPlayers();

echo $result->motd();
echo $isFull ? 'Server is full' : 'Slots are available';
```

Each protocol also exposes extra readonly properties:

| Result | Extra data |
|---|---|
| `JavaStatusResult` | `protocol`, `versionName`, `favicon`, `delay`, `players` |
| `JavaQueryResult` | `hostIp`, `players` |
| `BedrockStatusResult` | `protocol`, `version`, `gameMode`, `map`, `serverId`, `ipv4Port`, `ipv6Port` |
| `LegacyJavaStatusResult` | `protocol`, `versionName` |

For Java status, the `players` property is the sample returned by the server. Servers are allowed to return no sample even when players are online.

```php
foreach ($result->players as $player) {
    print_r($player);
}
```

If a server sends a favicon, Java status exposes it as a data URI string:

```php
if ($result->favicon !== '') {
    file_put_contents('favicon.txt', $result->favicon);
}
```

## Reading Raw Data

Use raw data when you need protocol fields that are not represented by the typed result yet.

```php
$status->fetch();

print_r($status->getResult()->raw());
print_r($status->getInfo());
```

`getInfo()` is kept for compatibility. New code should prefer `getResult()` for common fields and `getResult()->raw()` for raw protocol data.

## Java Query

`MinecraftJavaQuery` uses the GameSpy4 Query protocol. It is not the same thing as normal Java status ping.

The Minecraft server must enable Query in `server.properties`:

```properties
enable-query=true
query.port=25565
```

Then query it:

```php
<?php declare(strict_types=1);

use DevLancer\MinecraftStatus\MinecraftJavaQuery;

require_once __DIR__ . '/vendor/autoload.php';

$query = new MinecraftJavaQuery('mc.example.com');
$result = $query->fetch()->getResult();

echo "MOTD: " . $result->motd() . "\n";
echo "Players: " . $result->onlinePlayers() . '/' . $result->maxPlayers() . "\n";
echo "Host IP: " . $result->hostIp . "\n";

foreach ($result->players as $player) {
    echo $player . "\n";
}
```

Example file: [examples/query.php](examples/query.php)

If Java status works but Query times out, the most likely reason is that Query is disabled or blocked by firewall rules.

## Bedrock

Bedrock Edition uses UDP and usually listens on port `19132`:

```php
<?php declare(strict_types=1);

use DevLancer\MinecraftStatus\MinecraftBedrockStatus;

require_once __DIR__ . '/vendor/autoload.php';

$status = new MinecraftBedrockStatus('bedrock.example.com', 19132);
$result = $status->fetch()->getResult();

echo "MOTD: " . $result->motd() . "\n";
echo "Players: " . $result->onlinePlayers() . '/' . $result->maxPlayers() . "\n";
echo "Version: " . ($result->version ?? 'unknown') . "\n";
echo "Game mode: " . ($result->gameMode ?? 'unknown') . "\n";
```

Example file: [examples/bedrock.php](examples/bedrock.php)

Bedrock status data is a length-prefixed, semicolon-delimited RakNet status string. The parser treats `;` as a field separator and requires the expected Bedrock field count.

Vanilla Bedrock Dedicated Server treats `server-name` as a string without semicolons. This library does not try to guess or rebuild names that contain `;`, because doing so could shift protocol, version, player count and port fields. Payloads with an unexpected field count are rejected as invalid responses.

## Legacy Java

Use `MinecraftJavaPreOld17Status` only for old Java servers that still implement the legacy ping used before Minecraft 1.7:

```php
<?php declare(strict_types=1);

use DevLancer\MinecraftStatus\MinecraftJavaPreOld17Status;

require_once __DIR__ . '/vendor/autoload.php';

$status = new MinecraftJavaPreOld17Status('legacy.example.com');
$result = $status->fetch()->getResult();

echo "MOTD: " . $result->motd() . "\n";
echo "Players: " . $result->onlinePlayers() . '/' . $result->maxPlayers() . "\n";
echo "Version: " . ($result->versionName ?? 'unknown') . "\n";
```

Modern Java servers should use `MinecraftJavaStatus` instead.

## Timeouts, Encoding And SRV Records

All clients accept host, port, timeout and SRV resolving flag:

```php
$status = new MinecraftJavaStatus(
    host: 'mc.example.com',
    port: 25565,
    timeout: 0.5,
    resolveSRV: true
);
```

Timeouts are seconds and accept `int|float`. The value must be greater than zero.

```php
$status->setTimeout(1.5);
```

Set encoding when you need raw strings converted to a specific encoding:

```php
$status->setEncoding('UTF-8');
$status->fetch();
```

SRV lookup is enabled by default. Pass `false` as the fourth constructor argument when you want to query the exact host and port:

```php
$status = new MinecraftJavaStatus('mc.example.com', 25565, 3, false);
```

SRV resolution ignores literal IPv4 and IPv6 hosts, validates SRV records, normalizes trailing dots and sorts candidates by priority ascending and weight descending.

## Lifecycle

The lifecycle state describes the last fetch result:

```php
use DevLancer\MinecraftStatus\StatusState;

$status = new MinecraftJavaStatus('mc.example.com');

$status->status(); // StatusState::Idle

$status->fetch();
$status->status(); // StatusState::Fetched

$status->disconnect();
$status->status(); // StatusState::Fetched
```

`disconnect()` closes only the socket. It does not clear a successfully fetched result, so `getResult()` and `getInfo()` still work after disconnecting.

If a later `fetch()` fails, the previous result is cleared and `status()` becomes `StatusState::Failed`.

`isConnected()` only reports whether the socket is currently open. Use `status()` when you need to know whether parsed data is available.

## Error Handling

Handle the specific exceptions when you want precise error messages:

```php
use DevLancer\MinecraftStatus\Exception\ConnectionException;
use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\Exception\TimeoutException;

try {
    $result = $status->fetch()->getResult();
} catch (ConnectionException $exception) {
    // DNS failed, socket could not be opened, or the endpoint refused connection.
} catch (TimeoutException $exception) {
    // The server did not answer before the configured timeout.
} catch (ProtocolException $exception) {
    // The response packet did not match the expected Minecraft protocol.
} catch (InvalidResponseException $exception) {
    // The packet was received, but the payload could not be parsed as valid status data.
} catch (ReceiveStatusException $exception) {
    // Compatibility catch for receive, timeout, protocol and invalid response failures.
} catch (NotConnectedException $exception) {
    // Data was requested before a successful fetch().
}
```

`TimeoutException`, `ProtocolException` and `InvalidResponseException` extend `ReceiveStatusException`, so older `catch (ReceiveStatusException $exception)` blocks still work.

## Convenience Getters

Typed results are the preferred API, but clients also expose convenience getters:

```php
getInfo(): array
getResult(): StatusResultInterface
getCountPlayers(): int
getMaxPlayers(): int
getMotd(): string
```

Additional client-specific getters:

| Method | Java Status | Java Query | Bedrock Status | Legacy Java |
|---|---:|---:|---:|---:|
| `getPlayers()` | Yes | Yes | No | No |
| `getFavicon()` | Yes | No | No | No |
| `getDelay()` | Yes | No | No | No |
| `getProtocol()` | Yes | No | Yes | Yes |

## Compatibility And Migration

`connect()` remains available as an alias of `fetch()` for older code:

```php
$status->connect();
```

The deprecated class aliases `Ping`, `Query`, `QueryBedrock` and `PingPreOld17` still exist. New code should use `MinecraftJavaStatus`, `MinecraftJavaQuery`, `MinecraftBedrockStatus` and `MinecraftJavaPreOld17Status`.

See [UPGRADE-4.0.md](UPGRADE-4.0.md) for migration notes from V3.

## Development

Run the default checks:

```bash
composer qa
```

Real public server smoke tests are opt-in. Create an ignored `.servers-list.php` file in the repository root, then run:

```bash
composer test-real
```

The real server config should return sections for `java_status`, `java_query`, `bedrock`, `legacy_java` and `srv_java_status`. Public servers are not reliable enough for the default test suite, so these tests are intentionally separate from `composer test`.

## License

[MIT](LICENSE)
