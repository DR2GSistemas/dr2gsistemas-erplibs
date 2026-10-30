<?php

namespace Dr2g\ErpLibs\CLI;

abstract class Command
{
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    /** @param list<string> $arguments */
    abstract public function handle(array $arguments): void;
}
