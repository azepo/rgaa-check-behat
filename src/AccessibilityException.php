<?php

declare(strict_types=1);

namespace Azepo\RgaaCheckBehat;

use Azepo\RgaaCheck\Issue;

/**
 * Fait échouer une étape Behat en listant les défauts d'accessibilité relevés.
 */
final class AccessibilityException extends \RuntimeException
{
    /**
     * @param list<Issue> $issues
     */
    public function __construct(public readonly string $page, public readonly array $issues)
    {
        $lines = array_map(
            static fn (Issue $issue): string => sprintf('  - [RGAA %s] %s (%s) : %s', $issue->criterion, $issue->rule, $issue->severity->value, $issue->message),
            $issues,
        );

        parent::__construct(sprintf("%d défaut(s) d'accessibilité sur %s :\n%s", \count($issues), $page, implode("\n", $lines)));
    }
}
