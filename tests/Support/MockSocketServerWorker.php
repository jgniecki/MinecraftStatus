<?php declare(strict_types=1);

$arguments = $_SERVER['argv'] ?? [];
if (!is_array($arguments) || !isset($arguments[1]) || !is_string($arguments[1])) {
    fwrite(STDERR, "Missing worker configuration.\n");
    exit(64);
}

$payload = base64_decode($arguments[1], true);
if ($payload === false) {
    fwrite(STDERR, "Invalid worker configuration encoding.\n");
    exit(64);
}

$config = json_decode($payload, true);
if (!is_array($config)) {
    fwrite(STDERR, "Invalid worker configuration JSON.\n");
    exit(64);
}

$protocol = $config['protocol'] ?? null;
$encodedResponses = $config['responses'] ?? null;
$timeout = (int)($config['timeout'] ?? 5);

if (!is_string($protocol) || !is_array($encodedResponses) || $timeout <= 0) {
    fwrite(STDERR, "Incomplete worker configuration.\n");
    exit(64);
}

$responses = [];
foreach ($encodedResponses as $encodedResponse) {
    if (!is_string($encodedResponse)) {
        fwrite(STDERR, "Invalid response type.\n");
        exit(64);
    }

    $response = base64_decode($encodedResponse, true);
    if ($response === false) {
        fwrite(STDERR, "Invalid response encoding.\n");
        exit(64);
    }

    $responses[] = $response;
}

if ($protocol === 'tcp') {
    runTcpServer($responses, $timeout);
}

if ($protocol === 'udp') {
    runUdpServer($responses, $timeout);
}

fwrite(STDERR, "Unsupported protocol: " . $protocol . "\n");
exit(64);

/**
 * @param list<string> $responses
 */
function runTcpServer(array $responses, int $timeout): void
{
    $server = @stream_socket_server(
        'tcp://127.0.0.1:0',
        $errorCode,
        $errorMessage,
        STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
    );

    if ($server === false) {
        fwrite(STDERR, "Failed to start TCP server: " . $errorMessage . " (" . $errorCode . ")\n");
        exit(1);
    }

    stream_set_timeout($server, $timeout);
    echo extractPort($server) . PHP_EOL;
    flush();

    foreach ($responses as $response) {
        $connection = @stream_socket_accept($server, $timeout);
        if ($connection === false) {
            fwrite(STDERR, "TCP client did not connect.\n");
            fclose($server);
            exit(2);
        }

        $request = readTcpRequest($connection, $timeout);
        reportRequest($request);
        fwrite($connection, $response);
        fclose($connection);
    }

    fclose($server);
    exit(0);
}

/**
 * @param list<string> $responses
 */
function runUdpServer(array $responses, int $timeout): void
{
    $server = @stream_socket_server(
        'udp://127.0.0.1:0',
        $errorCode,
        $errorMessage,
        STREAM_SERVER_BIND
    );

    if ($server === false) {
        fwrite(STDERR, "Failed to start UDP server: " . $errorMessage . " (" . $errorCode . ")\n");
        exit(1);
    }

    stream_set_timeout($server, $timeout);
    echo extractPort($server) . PHP_EOL;
    flush();

    foreach ($responses as $response) {
        $peer = '';
        $request = stream_socket_recvfrom($server, 4096, 0, $peer);
        if ($request === false || $peer === '') {
            fwrite(STDERR, "UDP client did not send a request.\n");
            fclose($server);
            exit(2);
        }

        reportRequest($request);
        stream_socket_sendto($server, $response, 0, $peer);
    }

    fclose($server);
    exit(0);
}

/**
 * @param resource $server
 */
function extractPort($server): int
{
    $name = stream_socket_get_name($server, false);
    if (!is_string($name)) {
        fwrite(STDERR, "Failed to read server socket name.\n");
        exit(1);
    }

    $separator = strrpos($name, ':');
    if ($separator === false) {
        fwrite(STDERR, "Failed to parse server socket port: " . $name . "\n");
        exit(1);
    }

    return (int)substr($name, $separator + 1);
}

/**
 * @param resource $stream
 */
function waitForReadable($stream, int $timeout): void
{
    $read = [$stream];
    $write = [];
    $except = [];

    @stream_select($read, $write, $except, $timeout, 0);
}

/**
 * @param resource $stream
 */
function readTcpRequest($stream, int $timeout): string
{
    stream_set_blocking($stream, false);

    $request = '';
    $deadline = microtime(true) + $timeout;
    $idleDeadline = null;

    while (microtime(true) < $deadline) {
        $read = [$stream];
        $write = [];
        $except = [];

        $selected = @stream_select($read, $write, $except, 0, 50_000);
        if ($selected === false) {
            break;
        }

        if ($selected > 0) {
            $chunk = fread($stream, 4096);
            if ($chunk === false || $chunk === '') {
                if ($request !== '') {
                    break;
                }

                continue;
            }

            $request .= $chunk;
            $idleDeadline = microtime(true) + 0.05;
            continue;
        }

        if ($request !== '' && $idleDeadline !== null && microtime(true) >= $idleDeadline) {
            break;
        }
    }

    return $request;
}

function reportRequest(string $request): void
{
    echo 'REQUEST ' . base64_encode($request) . PHP_EOL;
    flush();
}
