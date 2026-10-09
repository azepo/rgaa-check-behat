<?php

declare(strict_types=1);

namespace Azepo\RgaaCheckBehat;

use Azepo\RgaaCheck\Issue;
use Azepo\RgaaCheck\Severity;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Step\Then;

/**
 * Étapes Behat qui mesurent la page affichée. Elles demandent un vrai navigateur (@javascript).
 *
 * Étapes expérimentales : elles n'ont été essayées que sur des pages simples. Une mesure ne
 * remplace pas un regard humain, en particulier pour un texte posé sur une image.
 */
class RgaaBrowserContext extends RawMinkContext
{
    /** Hauteur donnée à la fenêtre pendant la mesure à largeur réduite. */
    private const HEIGHT = 800;

    /** Éléments trop larges : description des cinq premiers dont le bord droit dépasse de l'écran. */
    private const MEASURE_WIDTH = <<<'JS'
        JSON.stringify((function () {
            var width = window.innerWidth;
            var wide = [];
            document.querySelectorAll('body *').forEach(function (element) {
                var box = element.getBoundingClientRect();
                if (box.width > 0 && box.right > width + 1 && wide.length < 5) {
                    var name = element.tagName.toLowerCase();
                    if (element.id) { name += '#' + element.id; }
                    if (typeof element.className === 'string' && element.className.trim()) { name += '.' + element.className.trim().split(/\s+/).join('.'); }
                    wide.push(name + ' (' + Math.round(box.right) + ' px)');
                }
            });
            return {inner: width, content: document.documentElement.scrollWidth, wide: wide};
        })())
        JS;

    /**
     * Relevé des couleurs : pour chaque élément qui porte directement du texte visible, sa couleur
     * et la couleur du fond derrière lui. Un fond en image ou en dégradé rend le relevé inexploitable :
     * l'élément est alors laissé de côté (fond « null »).
     */
    private const MEASURE_COLORS = <<<'JS'
        JSON.stringify((function () {
            function parse(value) {
                var found = /rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\/\s]+([\d.]+%?))?\s*\)/.exec(value);
                if (!found) { return null; }
                var alpha = found[4] === undefined ? 1 : (found[4].slice(-1) === '%' ? parseFloat(found[4]) / 100 : parseFloat(found[4]));
                return [parseFloat(found[1]), parseFloat(found[2]), parseFloat(found[3]), alpha];
            }
            function over(top, bottom) {
                return [0, 1, 2].map(function (i) { return top[i] * top[3] + bottom[i] * (1 - top[3]); });
            }
            function background(element) {
                var layers = [];
                for (var node = element; node && node.nodeType === 1; node = node.parentElement) {
                    var style = getComputedStyle(node);
                    if (style.backgroundImage && style.backgroundImage !== 'none') { return null; }
                    var color = parse(style.backgroundColor);
                    if (color && color[3] > 0) {
                        layers.push(color);
                        if (color[3] >= 1) { break; }
                    }
                }
                var result = [255, 255, 255];
                for (var i = layers.length - 1; i >= 0; i--) { result = over(layers[i], result); }
                return result;
            }
            var samples = [];
            document.querySelectorAll('body *').forEach(function (element) {
                var text = '';
                element.childNodes.forEach(function (child) { if (child.nodeType === 3) { text += child.nodeValue; } });
                text = text.replace(/\s+/g, ' ').trim();
                if (!text || ['SCRIPT', 'STYLE', 'NOSCRIPT'].indexOf(element.tagName) !== -1) { return; }
                var style = getComputedStyle(element);
                var box = element.getBoundingClientRect();
                if (style.visibility === 'hidden' || style.display === 'none' || parseFloat(style.opacity) === 0 || box.width === 0 || box.height === 0) { return; }
                // Texte masqué visuellement mais lu par les lecteurs d'écran (boîte d'un pixel, découpe) : il n'est pas affiché.
                if ((box.width <= 1 && box.height <= 1) || style.clip === 'rect(0px, 0px, 0px, 0px)' || style.clipPath === 'inset(50%)') { return; }
                var color = parse(style.color);
                var behind = background(element);
                if (!color) { return; }
                samples.push({
                    text: text,
                    color: behind ? over(color, behind) : null,
                    background: behind,
                    size: parseFloat(style.fontSize),
                    bold: parseInt(style.fontWeight, 10) >= 700
                });
            });
            return samples;
        })())
        JS;

    /**
     * Active le lien d'évitement et observe où se trouve le focus.
     *
     * Le lien d'évitement est le premier lien d'ancre de la page dont la cible est la zone de
     * contenu principal (<main> ou role="main"), ou un élément situé dedans.
     */
    private const FOLLOW_SKIP_LINK = <<<'JS'
        JSON.stringify((function () {
            var main = document.querySelector('main, [role="main"]');
            if (!main) { return {found: false, reason: 'aucune zone de contenu principal (<main>)'}; }
            var link = null;
            var target = null;
            var links = document.querySelectorAll('a[href^="#"]');
            for (var i = 0; i < links.length && !link; i++) {
                var id = decodeURIComponent(links[i].getAttribute('href').slice(1));
                var candidate = id ? document.getElementById(id) : null;
                if (candidate && (candidate === main || main.contains(candidate))) { link = links[i]; target = candidate; }
            }
            if (!link) { return {found: false, reason: 'aucun lien d\'ancre ne mène au contenu principal'}; }
            var focusable = document.querySelectorAll('a[href], button, input, select, textarea, [tabindex]');
            var first = null;
            for (var j = 0; j < focusable.length && !first; j++) {
                if (focusable[j].getAttribute('tabindex') !== '-1' && !focusable[j].disabled) { first = focusable[j]; }
            }
            link.focus();
            link.click();
            var active = document.activeElement;
            return {
                found: true,
                text: (link.textContent || '').replace(/\s+/g, ' ').trim(),
                href: link.getAttribute('href'),
                isFirst: first === link,
                hash: window.location.hash,
                focusMoved: !!active && (active === target || target.contains(active)),
                targetTag: target.tagName.toLowerCase(),
                targetFocusable: target.hasAttribute('tabindex') || ['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA'].indexOf(target.tagName) !== -1
            };
        })())
        JS;

    /**
     * Numérote les éléments atteignables au clavier et mémorise leur apparence sans le focus.
     * Renvoie leur nombre.
     */
    private const PREPARE_TAB_ORDER = <<<'JS'
        JSON.stringify((function () {
            var properties = ['outlineStyle', 'outlineWidth', 'outlineColor', 'boxShadow', 'backgroundColor', 'color', 'textDecorationLine', 'borderTopColor', 'borderBottomColor', 'borderBottomWidth'];
            function look(element) {
                var style = getComputedStyle(element);
                return properties.map(function (property) { return style[property]; }).join('|');
            }
            if (document.activeElement && document.activeElement !== document.body) { document.activeElement.blur(); }
            var count = 0;
            window.__rgaaLook = look;
            window.__rgaaBefore = [];
            document.querySelectorAll('a[href], button, input, select, textarea, summary, [tabindex]').forEach(function (element) {
                var box = element.getBoundingClientRect();
                var style = getComputedStyle(element);
                var hidden = element.disabled || element.type === 'hidden' || element.getAttribute('tabindex') === '-1'
                    || style.visibility === 'hidden' || style.display === 'none' || (box.width === 0 && box.height === 0);
                if (hidden) { return; }
                element.setAttribute('data-rgaa-i', String(count));
                window.__rgaaBefore[count] = look(element);
                count++;
            });
            return {count: count};
        })())
        JS;

    /** Décrit l'élément qui a le focus : son numéro, un libellé lisible, et si son apparence a changé. */
    private const READ_FOCUS = <<<'JS'
        JSON.stringify((function () {
            var element = document.activeElement;
            if (!element || element === document.body || element === document.documentElement) { return {index: -1}; }
            var index = element.hasAttribute('data-rgaa-i') ? parseInt(element.getAttribute('data-rgaa-i'), 10) : -2;
            var style = getComputedStyle(element);
            var outlined = style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0;
            var changed = index >= 0 && window.__rgaaLook(element) !== window.__rgaaBefore[index];
            var text = (element.getAttribute('aria-label') || element.textContent || element.getAttribute('name') || '').replace(/\s+/g, ' ').trim();
            return {index: index, label: '<' + element.tagName.toLowerCase() + '> « ' + text.slice(0, 40) + ' »', visible: outlined || changed};
        })())
        JS;

    /** Libellés des éléments numérotés qui n'ont jamais reçu le focus. */
    private const LABELS = <<<'JS'
        JSON.stringify((function () {
            var labels = {};
            document.querySelectorAll('[data-rgaa-i]').forEach(function (element) {
                var text = (element.getAttribute('aria-label') || element.textContent || element.getAttribute('name') || '').replace(/\s+/g, ' ').trim();
                labels[element.getAttribute('data-rgaa-i')] = '<' + element.tagName.toLowerCase() + '> « ' + text.slice(0, 40) + ' »';
            });
            return labels;
        })())
        JS;

    private const TAB = 9;

    #[Then('/^la page ne doit pas défiler horizontalement à (?P<width>\d+) pixels de large$/')]
    #[Then('/^the page should not scroll horizontally at (?P<width>\d+) pixels wide$/')]
    public function assertNoHorizontalScroll(string $width): void
    {
        $expected = (int) $width;
        $session = $this->getSession();

        try {
            $session->resizeWindow($expected, self::HEIGHT);
        } catch (UnsupportedDriverActionException|DriverException $error) {
            throw $this->needsBrowser($error);
        }
        $measure = $this->measure(self::MEASURE_WIDTH);

        $inner = self::pixels($measure['inner'] ?? null);
        $content = self::pixels($measure['content'] ?? null);
        if ($inner !== $expected) {
            throw new \RuntimeException(sprintf('Le navigateur n\'a pas pu être réduit à %d px de large (largeur obtenue : %d px) : la mesure n\'est pas possible.', $expected, $inner));
        }
        if ($content <= $inner) {
            return;
        }

        $wide = array_filter((array) ($measure['wide'] ?? []), \is_string(...));
        throw new AccessibilityException($session->getCurrentUrl(), [new Issue($session->getCurrentUrl(), 'affichage', '10.11', sprintf('défilement horizontal à %d px de large : le contenu fait %d px%s', $expected, $content, [] === $wide ? '' : ' ; éléments trop larges : '.implode(', ', $wide)), Severity::Warning)]);
    }

    #[Then("le lien d'évitement doit mener au contenu principal")]
    #[Then('the skip link should lead to the main content')]
    public function assertSkipLinkWorks(): void
    {
        $url = $this->getSession()->getCurrentUrl();
        $result = $this->measure(self::FOLLOW_SKIP_LINK);
        $issues = [];

        if (true !== ($result['found'] ?? false)) {
            $reason = \is_string($result['reason'] ?? null) ? $result['reason'] : 'lien introuvable';
            $issues[] = new Issue($url, 'evitement', '12.7', sprintf('lien d\'évitement absent : %s', $reason), Severity::Warning);
        } else {
            $href = \is_string($result['href'] ?? null) ? $result['href'] : '';
            $text = \is_string($result['text'] ?? null) ? $result['text'] : '';
            if (true !== ($result['focusMoved'] ?? false)) {
                $tag = \is_string($result['targetTag'] ?? null) ? $result['targetTag'] : '?';
                $issues[] = new Issue($url, 'evitement', '12.7', sprintf(
                    'le lien d\'évitement « %s » (%s) ne déplace pas le focus dans le contenu principal%s',
                    $text,
                    $href,
                    true === ($result['targetFocusable'] ?? false) ? '' : sprintf(' : sa cible <%s> ne peut pas recevoir le focus (ajouter tabindex="-1")', $tag),
                ), Severity::Warning);
            }
            if (true !== ($result['isFirst'] ?? false)) {
                $issues[] = new Issue($url, 'evitement', '12.7', sprintf('le lien d\'évitement « %s » n\'est pas le premier élément atteint au clavier', $text), Severity::Warning);
            }
        }

        if ([] !== $issues) {
            throw new AccessibilityException($url, $issues);
        }
    }

    #[Then('on doit pouvoir parcourir la page au clavier')]
    #[Then('the page should be navigable with the keyboard')]
    public function assertKeyboardTraversal(): void
    {
        $url = $this->getSession()->getCurrentUrl();
        $walk = $this->walk();
        $issues = [];

        if (null !== $walk['stuck']) {
            $issues[] = new Issue($url, 'clavier', '12.9', sprintf('piège au clavier : la touche Tab ne fait pas quitter %s', $walk['stuck']), Severity::Warning);
        }
        if (null === $walk['stuck'] && [] !== $walk['unreached']) {
            $issues[] = new Issue($url, 'clavier', '12.8', sprintf('%d élément(s) jamais atteint(s) avec la touche Tab : %s', \count($walk['unreached']), implode(', ', \array_slice($walk['unreached'], 0, 5))), Severity::Warning);
        }

        if ([] !== $issues) {
            throw new AccessibilityException($url, $issues);
        }
    }

    #[Then('le focus doit être visible sur chaque élément atteint au clavier')]
    #[Then('the focus should be visible on every element reached with the keyboard')]
    public function assertVisibleFocus(): void
    {
        $url = $this->getSession()->getCurrentUrl();
        $walk = $this->walk();

        if ([] !== $walk['invisible']) {
            throw new AccessibilityException($url, [new Issue($url, 'focus', '10.7', sprintf('focus non visible sur %d élément(s) : %s', \count($walk['invisible']), implode(', ', \array_slice($walk['invisible'], 0, 5))), Severity::Warning)]);
        }
    }

    /**
     * Parcourt la page avec la touche Tab, depuis le haut.
     *
     * @return array{reached: int, stuck: ?string, unreached: list<string>, invisible: list<string>}
     */
    private function walk(): array
    {
        $count = self::pixels($this->measure(self::PREPARE_TAB_ORDER)['count'] ?? null);
        $driver = $this->getSession()->getDriver();

        $seen = [];
        $invisible = [];
        $stuck = null;
        $current = -1;
        // Quelques frappes de plus que d'éléments : de quoi constater un retour en haut de page ou un blocage.
        for ($press = 0; $press < $count + 3; ++$press) {
            try {
                $driver->keyDown($current >= 0 ? sprintf('//*[@data-rgaa-i="%d"]', $current) : '//body', self::TAB);
            } catch (UnsupportedDriverActionException|DriverException $error) {
                throw $this->needsBrowser($error);
            }
            $focus = $this->measure(self::READ_FOCUS);
            $index = self::pixels($focus['index'] ?? -1);
            $label = \is_string($focus['label'] ?? null) ? $focus['label'] : '?';

            if ($index < 0) {
                // Le focus est sorti de la page (retour en haut) : le parcours est terminé.
                if (-1 === $index) {
                    break;
                }
                continue;
            }
            if ($index === $current) {
                $stuck = $label;
                break;
            }
            if (isset($seen[$index])) {
                break;
            }
            $seen[$index] = true;
            if (true !== ($focus['visible'] ?? false)) {
                $invisible[] = $label;
            }
            $current = $index;
        }

        $unreached = [];
        if (\count($seen) < $count) {
            foreach ($this->measure(self::LABELS) as $number => $label) {
                if (!isset($seen[(int) $number]) && \is_string($label)) {
                    $unreached[] = $label;
                }
            }
        }

        return ['reached' => \count($seen), 'stuck' => $stuck, 'unreached' => $unreached, 'invisible' => $invisible];
    }

    #[Then('les contrastes du texte doivent être suffisants')]
    #[Then('the text contrasts should be sufficient')]
    public function assertSufficientContrasts(): void
    {
        $url = $this->getSession()->getCurrentUrl();
        $issues = ContrastAudit::issues($url, array_values($this->measure(self::MEASURE_COLORS)));

        if ([] !== $issues) {
            throw new AccessibilityException($url, $issues);
        }
    }

    /**
     * Évalue une expression dans la page et décode son résultat JSON.
     *
     * @return array<mixed>
     */
    private function measure(string $expression): array
    {
        try {
            $json = $this->getSession()->evaluateScript($expression);
        } catch (UnsupportedDriverActionException|DriverException $error) {
            throw $this->needsBrowser($error);
        }
        $data = \is_string($json) ? json_decode($json, true) : null;
        if (!\is_array($data)) {
            throw new \RuntimeException('La mesure de la page n\'a rien renvoyé d\'exploitable.');
        }

        return $data;
    }

    private static function pixels(mixed $value): int
    {
        return is_numeric($value) ? (int) round((float) $value) : 0;
    }

    private function needsBrowser(\Throwable $error): \RuntimeException
    {
        return new \RuntimeException('Cette étape mesure la page affichée : elle demande un navigateur. Marquer le scénario @javascript.', 0, $error);
    }
}
