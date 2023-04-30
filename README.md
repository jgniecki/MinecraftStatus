# Minecraft Status [![Packagist](https://img.shields.io/packagist/dt/dev-lancer/minecraft-status.svg)](https://packagist.org/packages/dev-lancer/minecraft-status)

## Installation
This library can be installed by issuing the following command:
```bash
composer require dev-lancer/minecraft-status
```

## Example

### Query

This method uses GameSpy4 protocol, and requires enabling `query` listener in your `server.properties` like this:

> *enable-query=true*<br>
> *query.port=25565*

```php
<?php
use \DevLancer\MinecraftStatus\Query;

require_once ("vendor/autoload.php");

$host = ""; //Address server minecraft
$port = 25565;
$timeout = 3;
$resolveSVR = true;

$query = new Query($host, $port, $timeout, $resolveSVR);
$query->connect();
print_r($query->getInfo());
```

### Ping

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


### QueryBedrock

Use this class for bedrock edition servers

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

### Method list
```php
connect()
```

```php
isConnected(): bool
```

```php
getPlayers(): array
```

```php
getCountPlayers(): int
```

```php
getMaxPlayers(): int
```

```php
setTimeout(int $timeout): void
```

```php
getTimeout(): int
```

```php
getInfo(): array
```

```php
getEncoding(): string
```

```php
setEncoding(string $encoding): void
```

```php
getHost(): string
```

```php
getPort(): int
```

## License

[MIT](LICENSE)