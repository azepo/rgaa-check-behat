<?php

declare(strict_types=1);

namespace Azepo\RgaaCheckBehat;

use Azepo\RgaaCheck\Checker;
use Azepo\RgaaCheck\Issue;
use Azepo\RgaaCheck\Severity;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Step\Then;

/**
 * Étapes Behat qui contrôlent l'accessibilité de la page en cours.
 *
 * La page est contrôlée telle que le pilote Mink la voit : avec un navigateur, c'est la page
 * une fois les scripts exécutés ; sans navigateur, c'est le HTML renvoyé par le serveur.
 *
 * Ces contrôles couvrent ce qu'un programme peut vérifier. Ils ne remplacent ni un audit RGAA,
 * ni les tests au clavier et avec un lecteur d'écran.
 */
class RgaaContext extends RawMinkContext
{
    private readonly Checker $checker;

    /**
     * @param list<string> $skip règles écartées dans tous les scénarios : « langue », « couleurs »…
     */
    public function __construct(private readonly array $skip = [], ?Checker $checker = null)
    {
        $this->checker = $checker ?? Checker::withDefaultRules();
    }

    #[Then("la page ne doit présenter aucune erreur d'accessibilité")]
    #[Then('the page should have no accessibility error')]
    public function assertNoAccessibilityError(): void
    {
        $this->fail(array_values(array_filter($this->issues(), static fn (Issue $issue): bool => Severity::Error === $issue->severity)));
    }

    #[Then("la page ne doit présenter ni erreur ni avertissement d'accessibilité")]
    #[Then('the page should have no accessibility error or warning')]
    public function assertNoAccessibilityIssue(): void
    {
        $this->fail($this->issues());
    }

    #[Then('/^la page ne doit présenter aucune erreur d\'accessibilité en dehors des règles "(?P<rules>[^"]+)"$/')]
    #[Then('/^the page should have no accessibility error except for the rules "(?P<rules>[^"]+)"$/')]
    public function assertNoAccessibilityErrorExcept(string $rules): void
    {
        $skipped = array_map(trim(...), explode(',', $rules));

        $this->fail(array_values(array_filter(
            $this->issues(),
            static fn (Issue $issue): bool => Severity::Error === $issue->severity && !\in_array($issue->rule, $skipped, true),
        )));
    }

    #[Then('/^la page doit présenter le défaut d\'accessibilité "(?P<text>[^"]+)"$/')]
    #[Then('/^the page should have the accessibility issue "(?P<text>[^"]+)"$/')]
    public function assertAccessibilityIssue(string $text): void
    {
        foreach ($this->issues() as $issue) {
            if (str_contains($issue->message, $text)) {
                return;
            }
        }

        throw new \RuntimeException(sprintf('Aucun défaut d\'accessibilité ne contient « %s » sur %s.', $text, $this->getSession()->getCurrentUrl()));
    }

    /**
     * Défauts de la page en cours, règles écartées exclues.
     *
     * @return list<Issue>
     */
    public function issues(): array
    {
        $session = $this->getSession();

        return array_values(array_filter(
            $this->checker->checkHtml($session->getCurrentUrl(), $this->html()),
            fn (Issue $issue): bool => !\in_array($issue->rule, $this->skip, true),
        ));
    }

    /**
     * HTML de la page en cours.
     *
     * Avec un navigateur, le document est relu directement dans la page, déclaration <!doctype>
     * comprise : selon le pilote, le contenu fourni par Mink perd la balise <html> et ses attributs,
     * ce qui ferait signaler à tort une langue absente (RGAA 8.3) ou un doctype absent (8.1).
     * Sans navigateur, c'est le HTML renvoyé par le serveur.
     */
    private function html(): string
    {
        $session = $this->getSession();

        try {
            $html = $session->evaluateScript("(document.doctype ? '<!DOCTYPE ' + document.doctype.name + '>' : '') + document.documentElement.outerHTML");
            if (\is_string($html) && '' !== $html) {
                return $html;
            }
        } catch (UnsupportedDriverActionException|DriverException) {
            // Pilote sans JavaScript : on prend la réponse du serveur.
        }

        return $session->getPage()->getContent();
    }

    /**
     * @param list<Issue> $issues
     */
    private function fail(array $issues): void
    {
        if ([] !== $issues) {
            throw new AccessibilityException($this->getSession()->getCurrentUrl(), $issues);
        }
    }
}
