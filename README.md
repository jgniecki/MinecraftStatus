# Minecraft Status
![](https://img.shields.io/packagist/l/dev-lancer/minecraft-status?style=for-the-badge)
![](https://img.shields.io/packagist/dt/dev-lancer/minecraft-status?style=for-the-badge)
![](https://img.shields.io/github/v/release/DeveloperLancer/MinecraftStatus?style=for-the-badge)
![](https://img.shields.io/packagist/php-v/dev-lancer/minecraft-status?style=for-the-badge)

MinecraftStatus library allows you to communicate with minecraft servers using the most popular protocols.

## Installation
This library can be installed by issuing the following command:
```bash
composer require dev-lancer/minecraft-status
```

## Differences between Ping and Query

### Ping
Ping uses the TCP protocol to communicate with the Minecraft server in the java edition and bedrock edition, it uses the port on which the server is running.
Ping provides the most necessary information (hostname, motd, favicon, and a sample of players).
Thanks to its simplicity, it does not require any configuration on the server side, communication works with servers from version 1.7 and above.

To communicate with a server which has a version lower than 1.7, use the `PingPreOld17` class

### Query
Query uses GameSpy 4 protocol for communication,
so you should start listening in the server properties.
Query allows you to request more information about the server,
but has more security vulnerabilities.

## Usage

### Query

Example: [Query](examples/query.php)

This method uses GameSpy4 protocol, and requires enabling `query` listener in your `server.properties` like this:

> *enable-query=true*<br>
> *query.port=25565*

```php
<?php
use \DevLancer\MinecraftStatus\Query;

require_once ("vendor/autoload.php");

$host = ""; //Address server minecraft
$port = 25565; //from query.port
$timeout = 3;
$resolveSVR = true;

$query = new Query($host, $port, $timeout, $resolveSVR);
$query->connect();
print_r($query->getInfo());
```

### QueryBedrock

Example: [Bedrock](examples/bedrock.php)

Use this class for bedrock edition servers

In QueryBedrock you do not need to set anything in the properties of the server,
the port on which the server runs is used to communicate with the server

```php
<?php
use \DevLancer\MinecraftStatus\QueryBedrock;

require_once ("vendor/autoload.php");

$host = ""; //Address server minecraft
$port = 19132;
$timeout = 3;
$resolveSVR = true;

$query = new QueryBedrock($host, $port, $timeout, $resolveSVR);
$query->connect();
print_r($query->getInfo());
```

### Ping and PingPreOld17

Example: [Ping](examples/ping.php)

```php
<?php
use \DevLancer\MinecraftStatus\Ping;
use \DevLancer\MinecraftStatus\PingPreOld17;

require_once ("vendor/autoload.php");

$host = ""; //Address server minecraft
$port = 25565;
$timeout = 3;
$resolveSVR = true;

$ping = new Ping($host, $port, $timeout, $resolveSVR);
//$ping = new PingPreOld17($host, $port, $timeout, $resolveSVR); //use when version is older than Minecraft 1.7
$ping->connect();
print_r($ping->getInfo());
```

If you want to get `ping` info from a server that uses a version older than Minecraft 1.7, then use class `PingPreOld17` instead of `Ping`.

## Methods

### List of methods
|                   | Query | QueryBedrock | Ping | PingPreOld17 |
|-------------------|-------|--------------|------|--------------|
| connect()         |   X   |       X      |   X  |       X      |
| isConnected()     |   X   |       X      |   X  |       X      |
| disconnect()      |   X   |       X      |   X  |       X      |
| getPlayers()      |   X   |              |   X  |              |
| getCountPlayers() |   X   |       X      |   X  |       X      |
| getMaxPlayers()   |   X   |       X      |   X  |       X      |
| getInfo()         |   X   |       X      |   X  |       X      |
| getFavicon()      |       |              |   X  |              |
| setTimeout()      |   X   |       X      |   X  |       X      |
| getTimeout()      |   X   |       X      |   X  |       X      |
| setEncoding()     |   X   |       X      |   X  |       X      |
| getEncoding()     |   X   |       X      |   X  |       X      |
| isResolveSRV()    |   X   |       X      |   X  |       X      |
| getHost()         |   X   |       X      |   X  |       X      |
| getPort()         |   X   |       X      |   X  |       X      |

### Use before connecting

Sets the timeout for the connection
```php
setTimeout(int $timeout): void
```

Sets the character encoding for the returned values using the `getInfo()` and `getPlayers()` methods
```php
setEncoding(string $encoding): void
```

Connects to the server.
There may be a `ConnectionException` which means that the connection to the server has failed
or a `ReceiveStatusException` when the connection to the server has succeeded,
but the communication has not been successful.
When the method is used again, it disconnects from the server and establishes a new connection
```php
connect(): self
```

### Use when you are connected

Disconnects the connection to the server
```php
disconnect(): void
```

Using the following methods when not connected to the server will throw a `NotConnectedException` exception,
in case you have successfully connected to the server but the communication does not work then they will return a default value of empty

It returns a full array with the information it was able to obtain.
```php
getInfo(): array
```

Returns arrays of users
```php
getPlayers(): array
```

Returns the number of online players
```php
getCountPlayers(): int
```

Returns the number of server slots
```php
getMaxPlayers(): int
```

Returns the favicon as a string
```php
getFavicon(): string
```

### Use independently of the connection

Returns the server host
```php
getHost(): string
```

Returns a port
```php
getPort(): int
```

Returns the character encoding used to encode the values in the `getInfo()` and `getPlayers()` methods.
```php
getEncoding(): string
```

Returns a timeout.
```php
getTimeout(): int
```

Returns `true` when a successful connection to the server is made regardless of whether a `ReceiveStatusException` exception is thrown.
```php
isConnected(): bool
```

Returns `true` when an attempt to resolve the SRV occurs on a connection, regardless of the result.
```php
isResolveSRV(): bool
```

## SRV DNS record
The library allows automatic resolution of SRV records, by default the service is enabled, to disable it you must specify `false` in the fourth parameter of the constructor

## License

[MIT](LICENSE)