# rgaa-check-behat

Étapes Behat pour contrôler l'accessibilité d'une page pendant un scénario, avec les règles de [rgaa-check](https://github.com/azepo/rgaa-check).

```gherkin
# language: fr
Fonctionnalité: Accessibilité de la page de contact

  Scénario: La page de contact ne présente aucune erreur
    Étant donné que je suis sur "/contact"
    Alors la page ne doit présenter aucune erreur d'accessibilité
```

Par rapport à `rgaa-check` seul, qui lit des fichiers `.html` :

- la page est contrôlée **là où le scénario l'a amenée** : après une connexion, un formulaire envoyé, un menu ouvert ;
- avec un navigateur, elle est contrôlée **une fois les scripts exécutés** : le contenu ajouté par JavaScript est vu ;
- il n'y a pas de pages à enregistrer à la main pour une application dynamique.

## Ce que le paquet ne fait pas

Il applique les règles de `rgaa-check`, ni plus ni moins : 25 critères du RGAA 4.1 touchés sur 106, aucun en entier. **Il ne remplace pas un audit et ne permet pas de déclarer un site conforme.** Le détail de la couverture est dans la documentation de `rgaa-check`.

Même avec un navigateur, cette version ne contrôle pas ce que seul un navigateur peut mesurer : contrastes affichés, focus visible, parcours au clavier, affichage à 320 px. C'est prévu pour une version suivante.

## Installation

PHP 8.2 ou plus récent, Behat 3.

```bash
composer require --dev azepo/rgaa-check-behat
```

Le paquet n'est pas encore publié. D'ici là, déclarer son dépôt dans le `composer.json` du projet, comme pour `rgaa-check`.

Il faut aussi un pilote Mink. Deux cas :

| Cas | À installer |
|---|---|
| Sans navigateur (rapide, pas de JavaScript) | `behat/mink-browserkit-driver` et `symfony/http-client` |
| Avec Chrome ou Chromium | `dmore/chrome-mink-driver` et `dmore/behat-chrome-extension` |

## Configuration

`behat.yml`, sans navigateur :

```yaml
default:
    suites:
        default:
            contexts:
                - Behat\MinkExtension\Context\MinkContext
                - Azepo\RgaaCheckBehat\RgaaContext
    extensions:
        Behat\MinkExtension:
            base_url: 'http://127.0.0.1:8000'
            sessions:
                default:
                    browserkit_http: ~
```

Avec Chromium, pour les scénarios marqués `@javascript` :

```yaml
default:
    extensions:
        DMore\ChromeExtension\Behat\ServiceContainer\ChromeExtension: ~
        Behat\MinkExtension:
            base_url: 'http://127.0.0.1:8000'
            browser_name: chrome
            javascript_session: chrome
            sessions:
                default:
                    browserkit_http: ~
                chrome:
                    chrome:
                        api_url: 'http://127.0.0.1:9222'
```

Chromium doit tourner avant de lancer Behat :

```bash
chromium --headless=new --remote-debugging-port=9222 &
```

Pour écarter des règles dans tous les scénarios :

```yaml
contexts:
    - Azepo\RgaaCheckBehat\RgaaContext:
        skip: ['langue']
```

## Étapes disponibles

Chaque étape existe en français et en anglais.

| Français | Anglais |
|---|---|
| `la page ne doit présenter aucune erreur d'accessibilité` | `the page should have no accessibility error` |
| `la page ne doit présenter ni erreur ni avertissement d'accessibilité` | `the page should have no accessibility error or warning` |
| `la page ne doit présenter aucune erreur d'accessibilité en dehors des règles "images, liens"` | `the page should have no accessibility error except for the rules "images, liens"` |
| `la page doit présenter le défaut d'accessibilité "…"` | `the page should have the accessibility issue "…"` |

- La première ne tient pas compte des avertissements ; la deuxième, si.
- La troisième sert à activer le contrôle sur une page qui a encore des défauts connus, en les écartant par règle.
- La dernière sert surtout à tester : elle vérifie qu'un défaut précis est bien relevé.

Quand une étape échoue, Behat affiche chaque défaut avec son critère :

```
2 défaut(s) d'accessibilité sur http://127.0.0.1:8000/contact :
  - [RGAA 1.1] images (erreur) : image sans attribut alt : plan.png
  - [RGAA 9.1] titres (erreur) : niveau de titre sauté : de h1 à h3 (« Horaires »)
```

## Avec Symfony

Le contexte n'a pas de dépendance obligatoire : il fonctionne tel quel. Avec `friends-of-behat/symfony-extension`, il peut être déclaré comme service pour recevoir un `Checker` configuré par l'application (règles maison, règles retirées) :

```yaml
services:
    Azepo\RgaaCheckBehat\RgaaContext:
        arguments:
            $skip: ['langue']
            $checker: '@Azepo\RgaaCheck\Checker'
```

Cette déclaration n'a pas été essayée dans une application Symfony réelle.

## Limites connues

- **Le fichier de référence des défauts connus** (`--baseline` de `rgaa-check`) n'est pas disponible dans les scénarios. L'étape « en dehors des règles » en tient lieu, moins finement.
- **Pages qui chargent du contenu après coup** : le contrôle porte sur la page à l'instant de l'étape. Attendre d'abord que le contenu soit là, avec une étape d'attente de votre projet.
- **Un seul navigateur essayé** : Chromium 155 avec `dmore/chrome-mink-driver`. Les autres pilotes (Selenium, Panther) devraient fonctionner, mais n'ont pas été essayés.

## Développement

Les tests unitaires n'ont besoin ni de serveur ni de navigateur :

```bash
vendor/bin/phpunit tests
```

Les scénarios d'essai se lancent contre les pages de `tests/fixtures/site` :

```bash
php -S 127.0.0.1:8765 -t tests/fixtures/site &
vendor/bin/behat --tags='~@javascript&&~@echec'      # sans navigateur

chromium --headless=new --remote-debugging-port=9222 &
vendor/bin/behat --profile=chrome                    # dans Chromium
```

Le scénario `@echec` échoue volontairement : il sert à vérifier le message affiché.

## Licence

MIT. Voir [LICENSE](LICENSE).
