<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Skills\Remote;

class RemoteSkill
{
    public function __construct(
        public string $name,
        public string $repo,
        public string $path
    ) {}
}