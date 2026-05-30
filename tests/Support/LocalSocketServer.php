<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Support;

use RuntimeException;

final class LocalSocketServer
{
    /**
     * @param resource $process
     * @param array<int, resource> $pipes
     * @param list<string> $requests
     */
    private function __construct(
        private readonly mixed $process,
        private array $pipes,
        private readonly int $port,
        private array $requests = [],
        private string $stdoutBuffer = ''
    ) {
    }

    public function __destruct()
    {
        $this->stop();
    }

    public static function isAvailable(): bool
    {
        return function_exists('proc_open') && !in_array('proc_open', self::disabledFunctions(), true);
    }

    public static function tcp(string $response): self
    {
        return self::tcpSequence([$response]);
    }

    /**
     * @param list<string> $responses
     */
    public static function tcpSequence(array $responses): self
    {
        return self::start('tcp', $responses);
    }

    public static function udp(string $response): self
    {
        return self::udpSequence([$response]);
    }

    /**
     * @param list<string> $responses
     */
    public static function udpSequence(array $responses): self
    {
        return self::start('udp', $responses);
    }

    public function port(): int
    {
        return $this->port;
    }

    /**
     * @return list<string>
     */
    public function requests(int $expectedCount = 0, float $timeout = 1.0): array
    {
        $deadline = microtime(true) + $timeout;

        do {
            $this->collectOutput();

            if ($expectedCount <= 0 || count($this->requests) >= $expectedCount) {
                break;
            }

            if (!is_resource($this->process)) {
                break;
            }

            $status = proc_get_status($this->process);
            if ($status['running'] !== true && count($this->requests) >= $expectedCount) {
                break;
            }

            usleep(10_000);
        } while (microtime(true) < $deadline);

        return $this->requests;
    }

    public function stop(): void
    {
        if (!is_resource($this->process)) {
            return;
        }

        $this->collectOutput();

        $status = proc_get_status($this->process);
        if ($status['running'] === true) {
            proc_terminate($this->process);
        }

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($this->process);
        $this->pipes = [];
    }

    /**
     * @param list<string> $responses
     */
    private static function start(string $protocol, array $responses): self
    {
        if (!self::isAvailable()) {
            throw new RuntimeException('proc_open() is not available.');
        }

        $payload = json_encode([
            'protocol' => $protocol,
            'responses' => array_map(static fn (string $response): string => base64_encode($response), $responses),
            'timeout' => 5,
        ], JSON_THROW_ON_ERROR);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            [PHP_BINARY, __DIR__ . DIRECTORY_SEPARATOR . 'MockSocketServerWorker.php', base64_encode($payload)],
            $descriptors,
            $pipes
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start mock socket server.');
        }

        /** @var array<int, resource> $pipes */
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $port = self::readPort($process, $pipes);

        return new self($process, $pipes, $port);
    }

    private function collectOutput(): void
    {
        if (!isset($this->pipes[1]) || !is_resource($this->pipes[1])) {
            return;
        }

        while (($chunk = stream_get_contents($this->pipes[1])) !== false && $chunk !== '') {
            $this->stdoutBuffer .= $chunk;
        }

        while (($position = strpos($this->stdoutBuffer, "\n")) !== false) {
            $line = trim(substr($this->stdoutBuffer, 0, $position));
            $this->stdoutBuffer = substr($this->stdoutBuffer, $position + 1);

            if (!str_starts_with($line, 'REQUEST ')) {
                continue;
            }

            $request = base64_decode(substr($line, 8), true);
            if ($request !== false) {
                $this->requests[] = $request;
            }
        }
    }

    /**
     * @param resource $process
     * @param array<int, resource> $pipes
     */
    private static function readPort(mixed $process, array $pipes): int
    {
        $line = '';
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $chunk = fgets($pipes[1]);
            if ($chunk !== false) {
                $line .= $chunk;
                if (str_contains($line, "\n")) {
                    break;
                }
            }

            $status = proc_get_status($process);
            if ($status['running'] !== true && $line === '') {
                break;
            }

            usleep(10_000);
        }

        $port = trim($line);
        if ($port === '' || !ctype_digit($port)) {
            $stderr = stream_get_contents($pipes[2]);
            throw new RuntimeException('Mock socket server did not report a port. ' . (is_string($stderr) ? $stderr : ''));
        }

        return (int)$port;
    }

    /**
     * @return list<string>
     */
    private static function disabledFunctions(): array
    {
        $disabled = ini_get('disable_functions');
        if (!is_string($disabled) || $disabled === '') {
            return [];
        }

        return array_map('trim', explode(',', $disabled));
    }
}
