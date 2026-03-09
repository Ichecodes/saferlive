<?php
declare(strict_types=1);

/**
 * Centralized routing logic for discovery collector selection.
 */
namespace Lib\Discovery;

use InvalidArgumentException;

class CollectorRouter
{
    /**
     * @var array<string, string>
     */
    private array $collectorMap = [
        'x' => 'XSearchCollector',
        'rss' => 'RssCollector',
        'news' => 'NewsSiteCollector',
        'blog' => 'NewsSiteCollector',
        'instagram' => 'ManualFeedCollector',
        'manual' => 'ManualFeedCollector',
        'agency' => 'ManualFeedCollector',
    ];

    /**
     * Resolve collector key/name for a source row.
     *
     * @param array<string, mixed> $source
     */
    public function resolveCollectorForSource(array $source): string
    {
        $platform = $this->extractPlatform($source, 'source');
        return $this->resolveByPlatform($platform);
    }

    /**
     * Resolve collector key/name for a job row.
     *
     * @param array<string, mixed> $job
     */
    public function resolveCollectorForJob(array $job): string
    {
        $platform = $this->extractPlatform($job, 'job');
        return $this->resolveByPlatform($platform);
    }

    private function resolveByPlatform(string $platform): string
    {
        $platform = strtolower(trim($platform));
        if ($platform === '') {
            throw new InvalidArgumentException('Platform is required for collector routing.');
        }

        if (!isset($this->collectorMap[$platform])) {
            throw new InvalidArgumentException('Unsupported platform for collector routing: ' . $platform);
        }

        return $this->collectorMap[$platform];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractPlatform(array $row, string $rowType): string
    {
        if (!array_key_exists('platform', $row)) {
            throw new InvalidArgumentException(
                'Missing platform in ' . $rowType . ' row for collector routing.'
            );
        }

        return (string)$row['platform'];
    }
}
