<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Result;

final class JavaStatusResult implements StatusResultInterface
{
    /**
     * @param array<string, mixed> $raw
     * @param array<int, mixed> $players
     */
    public function __construct(
        public readonly array $raw,
        public readonly string $motd,
        public readonly int $onlinePlayers,
        public readonly int $maxPlayers,
        public readonly int $protocol,
        public readonly ?string $versionName,
        public readonly string $favicon,
        public readonly int $delay,
        public readonly array $players
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    public function motd(): string
    {
        return $this->motd;
    }

    public function onlinePlayers(): int
    {
        return $this->onlinePlayers;
    }

    public function maxPlayers(): int
    {
        return $this->maxPlayers;
    }
}
