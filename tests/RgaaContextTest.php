<?php

declare(strict_types=1);

namespace Azepo\RgaaCheckBehat\Tests;

use Azepo\RgaaCheckBehat\AccessibilityException;
use Azepo\RgaaCheckBehat\RgaaContext;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use PHPUnit\Framework\TestCase;

final class RgaaContextTest extends TestCase
{
    public function testACompliantPagePassesEveryStep(): void
    {
        $context = $this->contextOn('conforme.html');

        $context->assertNoAccessibilityError();
        $context->assertNoAccessibilityIssue();
        self::assertSame([], $context->issues());
    }

    public function testAFaultyPageFailsAndTheMessageNamesEachIssue(): void
    {
        $context = $this->contextOn('fautive.html');

        try {
            $context->assertNoAccessibilityError();
            self::fail('La page fautive aurait dû faire échouer l\'étape.');
        } catch (AccessibilityException $failure) {
            self::assertCount(2, $failure->issues);
            self::assertStringContainsString('2 défaut(s) d\'accessibilité sur http://exemple.test/page', $failure->getMessage());
            self::assertStringContainsString('[RGAA 1.1] images (erreur) : image sans attribut alt : plan.png', $failure->getMessage());
            self::assertStringContainsString('[RGAA 9.1] titres (erreur) : niveau de titre sauté', $failure->getMessage());
        }
    }

    public function testAWarningOnlyFailsTheStrictStep(): void
    {
        $context = $this->contextOn('avertissement.html');

        $context->assertNoAccessibilityError();

        $this->expectException(AccessibilityException::class);
        $this->expectExceptionMessage('[RGAA 1.2] images (avertissement)');
        $context->assertNoAccessibilityIssue();
    }

    public function testRulesCanBeSkippedInAStepOrForTheWholeContext(): void
    {
        $this->contextOn('fautive.html')->assertNoAccessibilityErrorExcept('images, titres');
        $this->contextOn('fautive.html', skip: ['images', 'titres'])->assertNoAccessibilityError();

        $this->expectException(AccessibilityException::class);
        $this->expectExceptionMessage('1 défaut(s)');
        $this->contextOn('fautive.html')->assertNoAccessibilityErrorExcept('images');
    }

    public function testAnExpectedIssueCanBeAsserted(): void
    {
        $context = $this->contextOn('fautive.html');
        $context->assertAccessibilityIssue('image sans attribut alt');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aucun défaut d\'accessibilité ne contient « tableau sans légende »');
        $context->assertAccessibilityIssue('tableau sans légende');
    }

    public function testWithoutABrowserAScriptIsNotExecuted(): void
    {
        // L'image sans alt de cette page est ajoutée par un script : sans navigateur, elle n'existe pas.
        $this->contextOn('dynamique.html')->assertNoAccessibilityError();
        $this->addToAssertionCount(1);
    }

    /**
     * @param list<string> $skip
     */
    private function contextOn(string $fixture, array $skip = []): RgaaContext
    {
        $html = (string) file_get_contents(__DIR__.'/fixtures/site/'.$fixture);
        $session = new Session(new BrowserKitDriver(new FakeBrowser($html)));
        $mink = new Mink(['default' => $session]);
        $mink->setDefaultSessionName('default');

        $context = new RgaaContext($skip);
        $context->setMink($mink);
        $context->setMinkParameters([]);
        $session->visit('http://exemple.test/page');

        return $context;
    }
}
