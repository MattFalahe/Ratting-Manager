<?php

namespace RattingManager\Tests;

final class NoLog
{
    public static function __callStatic(string $name, array $arguments): void
    {
    }
}
