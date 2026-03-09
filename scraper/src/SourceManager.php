<?php

class SourceManager
{
    private array $sources;

    public function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    public function all(): array
    {
        return $this->sources;
    }

    public function enabled(int $max): array
    {
        $enabled = array_values(array_filter($this->sources, static fn ($source) => !empty($source['enabled'])));
        return array_slice($enabled, 0, $max);
    }
}

