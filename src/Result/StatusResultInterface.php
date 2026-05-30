<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Result;

interface StatusResultInterface
{
    /**
     * @return array<string, mixed>
     */
    public function raw(): array;

    public function motd(): string;

    public function onlinePlayers(): int;

    public function maxPlayers(): int;
}
