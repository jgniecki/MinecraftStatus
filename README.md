# Minecraft Status

![](https://img.shields.io/packagist/l/dev-lancer/minecraft-status?style=for-the-badge)
![](https://img.shields.io/packagist/dt/dev-lancer/minecraft-status?style=for-the-badge)
![](https://img.shields.io/github/v/release/DeveloperLancer/MinecraftStatus?style=for-the-badge)
![](https://img.shields.io/packagist/php-v/dev-lancer/minecraft-status?style=for-the-badge)

Minecraft Status is a PHP 8.1+ library for reading Minecraft server status data. It supports Java Edition status ping, Java Edition Query, Bedrock Edition status and legacy Java servers before Minecraft 1.7.

The V4 API is centered around `fetch()`, `status(): StatusState` and typed result objects returned by `getResult()`. The older `connect()` method and deprecated class aliases are still available for compatibility, but new code should use the explicit V4 names.

## Installation

```bash
composer require dev-lancer/minecraft-status
```

## Which Client Should I Use?

| Client | Server type | Protocol | Server configuration required | Default port |
|---|---|---|---|---:|
| `MinecraftJavaStatus` | Java Edition 1.7+ | Status ping over TCP | No | 25565 |
| `MinecraftJavaQuery` | Java Edition with Query enabled | GameSpy4 Query over UDP | Yes, `enable-query=true` | 25565 |
| `MinecraftBedrockStatus` | Bedrock Edition | Bedrock/RakNet pong over UDP | No | 19132 |
| `MinecraftJavaPreOld17Status` | Java Edition before 1.7 | Legacy ping over TCP | No | 25565 |

Use `MinecraftJavaStatus` for normal Java Edition servers. Use `MinecraftJavaQuery` only when Query is enabled on the server and you need Query-specific data. Use `MinecraftBedrockStatus` for Bedrock Edition servers.

## Quick Start

### Java Edition Status

```php
<?php

use DevLancer\MinecraftStatus\MinecraftJavaStatus;

require_once __DIR__ . '/vendor/autoload.php';

$status = new MinecraftJavaStatus('mc.example.com');
$result = $status->fetch()->getResult();

echo $result->motd();
echo $result->onlinePlayers() . '/' . $result->maxPlayers();
echo $result->versionName;
```

Example file: [examples/ping.php](examples/ping.php)

### Bedrock Edition Status

```php
<?php

use DevLancer\MinecraftStatus\MinecraftBedrockStatus;

require_once __DIR__ . '/vendor/autoload.php';

$status = new MinecraftBedrockStatus('bedrock.example.com', 19132);
$result = $status->fetch()->getResult();

echo $result->motd();
echo $result->onlinePlayers() . '/' . $result->maxPlayers();
echo $result->version;
```

Example file: [examples/bedrock.php](examples/bedrock.php)

Bedrock status data is a semicolon-delimited RakNet status string. The parser treats `;` as a field separator and requires the expected Bedrock field count. Vanilla Bedrock Dedicated Server documents `server-name` as a string without semicolons, so the library does not try to guess or rebuild names that contain `;`; such payloads are rejected as invalid responses to avoid shifting protocol, version and player-count fields.

### Java Edition Query

Java Query uses the GameSpy4 Query protocol. The Minecraft server must have Query enabled in `server.properties`:

```properties
enable-query=true
query.port=25565
```

```php
<?php

use DevLancer\MinecraftStatus\MinecraftJavaQuery;

require_once __DIR__ . '/vendor/autoload.php';

$query = new MinecraftJavaQuery('mc.example.com', 25565);
$result = $query->fetch()->getResult();

echo $result->motd();
print_r($result->players);
```

Example file: [examples/query.php](examples/query.php)

### Legacy Java Servers Before 1.7

```php
<?php

use DevLancer\MinecraftStatus\MinecraftJavaPreOld17Status;

require_once __DIR__ . '/vendor/autoload.php';

$status = new MinecraftJavaPreOld17Status('legacy.example.com');
$result = $status->fetch()->getResult();

echo $result->motd();
echo $result->onlinePlayers() . '/' . $result->maxPlayers();
```

## Reading Data

`getResult()` is the preferred V4 read API. It returns a typed result object after a successful `fetch()`:

```php
$result = $status->fetch()->getResult();

echo $result->motd();
echo $result->onlinePlayers();
echo $result->maxPlayers();
print_r($result->raw());
```

Each result also exposes protocol-specific readonly properties:

| Result | Extra properties |
|---|---|
| `JavaStatusResult` | `protocol`, `versionName`, `favicon`, `delay`, `players` |
| `JavaQueryResult` | `hostIp`, `players` |
| `BedrockStatusResult` | `protocol`, `version`, `gameMode`, `map`, `serverId`, `ipv4Port`, `ipv6Port` |
| `LegacyJavaStatusResult` | `protocol`, `versionName` |

`getInfo()` is still available as the compatible raw array export:

```php
$status->fetch();

print_r($status->getInfo());
```

Convenience getters remain available for existing code:

```php
getInfo(): array
getResult(): StatusResultInterface
getCountPlayers(): int
getMaxPlayers(): int
getMotd(): string
```

Additional getters exist on specific clients:

| Method | Java Status | Java Query | Bedrock Status | Legacy Java |
|---|---:|---:|---:|---:|
| `getPlayers()` | Yes | Yes | No | No |
| `getFavicon()` | Yes | No | No | No |
| `getDelay()` | Yes | No | No | No |
| `getProtocol()` | Yes | No | Yes | Yes |

## Lifecycle

`fetch()` opens the transport, reads the server response, parses it and stores the result. Calling it again refreshes the stored result.

```php
use DevLancer\MinecraftStatus\StatusState;

$status = new MinecraftJavaStatus('mc.example.com');

$status->status(); // StatusState::Idle

$status->fetch();
$status->status(); // StatusState::Fetched

$status->disconnect();
$status->status(); // StatusState::Fetched

print_r($status->getInfo());
```

`disconnect()` closes only the transport. It does not clear a successfully fetched result. If a later `fetch()` fails, the previous result is cleared and `status()` becomes `StatusState::Failed`.

`isConnected()` reports only whether the socket is currently open. Use `status()` to check the result lifecycle.

## Errors

The library uses exceptions for connection, transport and response failures:

```php
use DevLancer\MinecraftStatus\Exception\ConnectionException;
use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\Exception\TimeoutException;
use DevLancer\MinecraftStatus\MinecraftJavaStatus;

try {
    $status = new MinecraftJavaStatus('mc.example.com');
    $result = $status->fetch()->getResult();

    echo $result->motd();
} catch (ConnectionException $exception) {
    // The socket could not be opened.
} catch (TimeoutException $exception) {
    // The server did not answer in time.
} catch (ProtocolException $exception) {
    // The server response did not match the protocol.
} catch (InvalidResponseException $exception) {
    // The response payload could not be parsed into a valid status.
} catch (ReceiveStatusException $exception) {
    // Compatibility catch for status receive failures.
} catch (NotConnectedException $exception) {
    // Data was requested before a successful fetch().
}
```

`TimeoutException`, `ProtocolException` and `InvalidResponseException` extend `ReceiveStatusException`, so older `catch (ReceiveStatusException $exception)` blocks still work.

## Timeout, Encoding And SRV Records

The constructor accepts host, port, timeout and SRV resolving flag:

```php
$status = new MinecraftJavaStatus(
    host: 'mc.example.com',
    port: 25565,
    timeout: 0.5,
    resolveSRV: true
);
```

Timeouts accept `int|float` seconds. Values must be greater than zero.

```php
$status->setTimeout(1.5);
$status->setEncoding('UTF-8');
$status->fetch();
```

SRV lookup is enabled by default. Pass `false` as the fourth constructor argument to disable it:

```php
$status = new MinecraftJavaStatus('mc.example.com', 25565, 3, false);
```

SRV resolution ignores literal IPv4 and IPv6 hosts, validates SRV records, normalizes trailing dots and sorts candidates by priority ascending and weight descending.

## Compatibility

`connect()` remains available as an alias of `fetch()` for code migrating from older versions:

```php
$status->connect();
```

The deprecated aliases `Ping`, `Query`, `QueryBedrock` and `PingPreOld17` still exist, but new code should use `MinecraftJavaStatus`, `MinecraftJavaQuery`, `MinecraftBedrockStatus` and `MinecraftJavaPreOld17Status`.

See [UPGRADE-4.0.md](UPGRADE-4.0.md) for migration notes from V3.

## Development Notes

Technical planning documents live in [docs/](docs/README.md). They describe the V4 implementation plan, test plan and compatibility decisions for maintainers.

## License

[MIT](LICENSE)
