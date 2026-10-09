<?php

declare(strict_types=1);

namespace Azepo\RgaaCheckBehat;

use Azepo\RgaaCheck\Contrast;
use Azepo\RgaaCheck\Issue;
use Azepo\RgaaCheck\Severity;

/**
 * Juge les couleurs relevées dans une page affichée (RGAA 3.2).
 *
 * Un relevé décrit un texte tel que le navigateur l'affiche : sa couleur, la couleur du fond
 * derrière lui, sa taille et sa graisse. Le seuil est de 4,5:1, ou de 3:1 pour un grand texte
 * (24 px, ou 18,5 px en gras).
 */
final class ContrastAudit
{
    /**
     * @param list<mixed> $samples relevés : ['text' => string, 'color' => [r, g, b], 'background' => [r, g, b], 'size' => float, 'bold' => bool]
     *
     * @return list<Issue> un défaut par couple de couleurs insuffisant, avec un exemple de texte
     */
    public static function issues(string $page, array $samples): array
    {
        $issues = [];
        foreach ($samples as $sample) {
            if (!\is_array($sample)) {
                continue;
            }
            $color = self::color($sample['color'] ?? null);
            $background = self::color($sample['background'] ?? null);
            if (null === $color || null === $background) {
                continue;
            }
            $size = is_numeric($sample['size'] ?? null) ? (float) $sample['size'] : 16.0;
            $large = $size >= 24.0 || (true === ($sample['bold'] ?? false) && $size >= 18.5);
            $minimum = $large ? Contrast::LARGE_TEXT : Contrast::TEXT;

            if (Contrast::isEnough($color, $background, $minimum)) {
                continue;
            }
            // Un seul défaut par couple de couleurs et par seuil : une page répète souvent la même faute.
            $key = $color.$background.$minimum;
            if (isset($issues[$key])) {
                continue;
            }
            $text = \is_string($sample['text'] ?? null) ? mb_substr(trim($sample['text']), 0, 40) : '';
            $issues[$key] = new Issue($page, 'contrastes', '3.2', sprintf(
                'contraste insuffisant : %s sur %s = %s:1, minimum %s:1 (%s, « %s »)',
                $color,
                $background,
                number_format(Contrast::ratio($color, $background), 2, ',', ''),
                number_format($minimum, 1, ',', ''),
                $large ? 'grand texte' : 'texte courant',
                $text,
            ), Severity::Warning);
        }

        return array_values($issues);
    }

    /** Couleur « #rrggbb » à partir de trois composantes, ou null si le relevé est inexploitable. */
    private static function color(mixed $rgb): ?string
    {
        if (!\is_array($rgb) || 3 !== \count($rgb)) {
            return null;
        }
        $channels = [];
        foreach ($rgb as $channel) {
            if (!is_numeric($channel)) {
                return null;
            }
            $channels[] = max(0, min(255, (int) round((float) $channel)));
        }

        return sprintf('#%02x%02x%02x', ...$channels);
    }
}
