<?php

declare(strict_types=1);

namespace Azepo\RgaaCheckBehat\Tests;

use Azepo\RgaaCheckBehat\ContrastAudit;
use PHPUnit\Framework\TestCase;

final class ContrastAuditTest extends TestCase
{
    private const WHITE = [255, 255, 255];

    public function testSufficientContrastGivesNothing(): void
    {
        self::assertSame([], ContrastAudit::issues('page', [
            ['text' => 'Texte noir', 'color' => [0, 0, 0], 'background' => self::WHITE, 'size' => 16, 'bold' => false],
            ['text' => 'Lien', 'color' => [0, 81, 168], 'background' => self::WHITE, 'size' => 16, 'bold' => false],
        ]));
    }

    public function testInsufficientContrastIsReportedOncePerColorPair(): void
    {
        $grey = ['color' => [153, 153, 153], 'background' => self::WHITE, 'size' => 16, 'bold' => false];

        $issues = ContrastAudit::issues('page', [['text' => 'Premier texte gris'] + $grey, ['text' => 'Second texte gris'] + $grey]);

        self::assertCount(1, $issues);
        self::assertSame('3.2', $issues[0]->criterion);
        self::assertSame('contrastes', $issues[0]->rule);
        self::assertSame('contraste insuffisant : #999999 sur #ffffff = 2,85:1, minimum 4,5:1 (texte courant, « Premier texte gris »)', $issues[0]->message);
    }

    public function testLargeTextHasALowerThreshold(): void
    {
        // #0593ff sur blanc = 3,17:1 : insuffisant en texte courant, suffisant en grand texte.
        $blue = ['text' => 'Titre', 'color' => [5, 147, 255], 'background' => self::WHITE];

        self::assertCount(1, ContrastAudit::issues('page', [$blue + ['size' => 16, 'bold' => false]]));
        self::assertCount(1, ContrastAudit::issues('page', [$blue + ['size' => 18.5, 'bold' => false]]));
        self::assertSame([], ContrastAudit::issues('page', [$blue + ['size' => 18.5, 'bold' => true]]));
        self::assertSame([], ContrastAudit::issues('page', [$blue + ['size' => 24, 'bold' => false]]));
    }

    public function testUnusableSamplesAreIgnored(): void
    {
        self::assertSame([], ContrastAudit::issues('page', [
            'pas un relevé',
            ['text' => 'Fond inconnu', 'color' => [153, 153, 153], 'background' => null, 'size' => 16],
            ['text' => 'Couleur incomplète', 'color' => [153, 153], 'background' => self::WHITE, 'size' => 16],
        ]));
    }
}
