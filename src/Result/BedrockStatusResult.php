<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Result;

final class BedrockStatusResult implements StatusResultInterface
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly array $raw,
        public readonly string $motd,
        public readonly int $onlinePlayers,
        public readonly int $maxPlayers,
        public readonly int $protocol,
        public readonly ?string $version,
        public readonly ?string $gameMode,
        public readonly ?string $map,
        public readonly ?string $serverId,
        public readonly int $ipv4Port,
        public readonly int $ipv6Port
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
