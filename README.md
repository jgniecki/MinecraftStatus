# Minecraft Status [![Packagist](https://img.shields.io/packagist/dt/dev-lancer/minecraft-status.svg)](https://packagist.org/packages/dev-lancer/minecraft-status)

## Installation
This library can installed by issuing the following command:
```bash
composer require dev-lancer/minecraft-status
```
### Query
This method uses GameSpy4 protocol, and requires enabling `query` listener in your `server.properties` like this:

> *enable-query=true*<br>
> *query.port=25565*

## Example

### Query

```php
<?php
use \DevLancer\MinecraftStatus\Query;

require_once ("vendor/autoload.php");

$host = "";
$port = 25565;
$timeout = 3;
$resolveSVR = true;

$query = new Query($host, $port, $timeout, $resolveSVR);
$query->connect();
print_r($query->getInfo());
```

### QueryBedrock

```php
<?php
use \DevLancer\MinecraftStatus\QueryBedrock;

require_once ("vendor/autoload.php");

$host = "";
$port = 19132;
$timeout = 3;
$resolveSVR = true;

$query = new QueryBedrock($host, $port, $timeout, $resolveSVR);
$query->connect();
print_r($query->getInfo());
```

### Ping

```php
<?php
use \DevLancer\MinecraftStatus\Ping;

require_once ("vendor/autoload.php");

$host = "";
$port = 25565;
$timeout = 3;
$resolveSVR = true;

$ping = new Ping($host, $port, $timeout, $resolveSVR);
$ping->connect();
print_r($ping->getInfo());
```

### PingPreOld17

```php
<?php
use \DevLancer\MinecraftStatus\PingPreOld17;

require_once ("vendor/autoload.php");

$host = "";
$port = 25565;
$timeout = 3;
$resolveSVR = true;

$ping = new PingPreOld17($host, $port, $timeout, $resolveSVR);
$ping->connect();
print_r($ping->getInfo());
```

## License

[MIT](LICENSE)