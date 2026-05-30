# Upgrade To 4.0

This guide lists the user-visible changes for Minecraft Status 4.0. It focuses on code migrating from V3 while keeping the compatibility aliases that still exist in V4.

## Runtime

V4 requires PHP `^8.1`.

Update your Composer platform and runtime before upgrading:

```json
{
  "require": {
    "php": "^8.1"
  }
}
```

## Fetching Status

Use `fetch()` for new code:

```php
$status = new MinecraftJavaStatus('mc.example.com');
$status->fetch();
```

`connect()` still exists as an alias of `fetch()` for compatibility:

```php
$status->connect();
```

The preferred V4 name is `fetch()` because the operation opens transport, reads the response and stores the parsed result.

## Reading Results

Use `getResult()` for typed data in new code:

```php
$result = $status->fetch()->getResult();

echo $result->motd();
echo $result->onlinePlayers();
echo $result->maxPlayers();
```

`getInfo()` still returns the raw parsed array for compatibility:

```php
$status->fetch();

print_r($status->getInfo());
```

## Lifecycle State

V4 adds `status(): StatusState`.

```php
use DevLancer\MinecraftStatus\StatusState;

$status->status(); // StatusState::Idle
$status->fetch();
$status->status(); // StatusState::Fetched
```

`disconnect()` closes the socket but does not clear a successfully fetched result. After a failed refresh, the old result is cleared and `status()` becomes `StatusState::Failed`.

`isConnected()` only describes the socket. Use `status()` to check whether a result is available.

## Exceptions

`NotConnectedException` remains the compatible exception for reading data before a successful `fetch()`. It also extends `StatusNotResolvedException`.

Status receive failures are more granular:

- `TimeoutException` for read timeout;
- `ProtocolException` for malformed protocol packets;
- `InvalidResponseException` for payloads that cannot be parsed into a valid status.

These exceptions still extend `ReceiveStatusException`, so existing compatibility catches continue to work:

```php
try {
    $status->fetch();
} catch (ReceiveStatusException $exception) {
    // Still catches timeout, protocol and invalid response failures.
}
```

## Bedrock Status Parsing

Bedrock status parsing is stricter in V4. The pong contains one length-prefixed status string, and that string is split by semicolons into the Bedrock fields. Vanilla Bedrock Dedicated Server documents `server-name` as a value without semicolons, so V4 rejects payloads with an unexpected field count instead of guessing that extra segments belong to the name.

This can break custom servers or proxies that send `;` inside the server name. The stricter behavior is intentional: guessing would shift protocol, version, player count and port fields and could return misleading data.

## Timeout

Timeouts now accept `int|float` seconds:

```php
$status = new MinecraftJavaStatus('mc.example.com', timeout: 0.5);
$status->setTimeout(1.5);
```

Values must be greater than zero. `getTimeout()` returns `float`.

## SRV Resolution

SRV resolving is still enabled by default for clients that use host and port constructors:

```php
$status = new MinecraftJavaStatus('mc.example.com');
```

Pass `false` as the fourth constructor argument to disable SRV:

```php
$status = new MinecraftJavaStatus('mc.example.com', 25565, 3, false);
```

V4 uses an SRV resolver that ignores literal IPv4 and IPv6 hosts, validates records, normalizes trailing dots and sorts candidates by priority ascending and weight descending. If a domain has multiple SRV records, V4 may choose a different endpoint than older versions.

## Deprecated Aliases

The deprecated aliases are still present in this V4 stage:

- `Ping` -> `MinecraftJavaStatus`
- `Query` -> `MinecraftJavaQuery`
- `QueryBedrock` -> `MinecraftBedrockStatus`
- `PingPreOld17` -> `MinecraftJavaPreOld17Status`

New code should use the full class names. The aliases are kept only to reduce migration cost.
