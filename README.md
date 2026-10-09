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

Avec un navigateur, cinq contrôles expérimentaux s'y ajoutent : contrastes du texte affiché (critère 3.2), absence de défilement horizontal à 320 px (10.11), fonctionnement du lien d'évitement (12.7), focus visible (10.7) et parcours au clavier (12.8, 12.9). Voir « Mesures dans le navigateur ».

## Installation

PHP 8.2 ou plus récent, Behat 3.

```bash
composer require --dev azepo/rgaa-check-behat
```

Les deux paquets sont sur GitHub, pas sur Packagist. Déclarer leurs dépôts dans le `composer.json` du projet avant la commande ci-dessus :

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/azepo/rgaa-check" },
        { "type": "vcs", "url": "https://github.com/azepo/rgaa-check-behat" }
    ]
}
```

Tant qu'aucune version de ce paquet n'est étiquetée, demander la branche principale : `composer require --dev azepo/rgaa-check-behat:dev-main`.

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

## Mesures dans le navigateur

Le contexte `Azepo\RgaaCheckBehat\RgaaBrowserContext` mesure la page affichée. Ses étapes demandent un vrai navigateur : le scénario doit être marqué `@javascript`.

```yaml
contexts:
    - Behat\MinkExtension\Context\MinkContext
    - Azepo\RgaaCheckBehat\RgaaContext
    - Azepo\RgaaCheckBehat\RgaaBrowserContext
```

```gherkin
@javascript
Scénario: La page de contact est lisible sur un petit écran
  Étant donné que je suis sur "/contact"
  Alors la page ne doit pas défiler horizontalement à 320 pixels de large
  Et les contrastes du texte doivent être suffisants
```

| Français | Anglais | Critère |
|---|---|---|
| `la page ne doit pas défiler horizontalement à 320 pixels de large` | `the page should not scroll horizontally at 320 pixels wide` | 10.11 |
| `les contrastes du texte doivent être suffisants` | `the text contrasts should be sufficient` | 3.2 |
| `le lien d'évitement doit mener au contenu principal` | `the skip link should lead to the main content` | 12.7 |
| `on doit pouvoir parcourir la page au clavier` | `the page should be navigable with the keyboard` | 12.8, 12.9 |
| `le focus doit être visible sur chaque élément atteint au clavier` | `the focus should be visible on every element reached with the keyboard` | 10.7 |

Exemples de ce que Behat affiche :

```
- [RGAA 10.11] affichage (avertissement) : défilement horizontal à 320 px de large : le contenu fait 608 px ; éléments trop larges : div#bandeau.fixe (608 px)
- [RGAA 3.2] contrastes (avertissement) : contraste insuffisant : #999999 sur #ffffff = 2,85:1, minimum 4,5:1 (texte courant, « Mention en gris clair sur fond blanc. »)
```

**Ces cinq étapes sont expérimentales.** Elles n'ont été essayées que sur des pages d'essai simples, dans Chromium 155. Leurs limites :

- **Largeur** : la largeur est émulée, comme dans les outils de développement du navigateur. La hauteur n'est pas contrôlée. La largeur peut être changée dans la phrase (`à 360 pixels de large`). Un tableau de données ou une carte, que le RGAA autorise à défiler, sera signalé : c'est à vous d'en juger.
- **Contrastes — ce qui est mesuré** : la couleur calculée de chaque texte visible et celle du fond derrière lui, transparences comprises. Le seuil est de 4,5:1, ou de 3:1 pour un grand texte (24 px, ou 18,5 px en gras). Un même couple de couleurs n'est signalé qu'une fois par page.
- **Contrastes — textes masqués** : un texte masqué visuellement mais laissé aux lecteurs d'écran (classe `visually-hidden` de Bootstrap, par exemple) n'est pas mesuré, puisqu'il n'est pas affiché. Un lien d'évitement masqué jusqu'au focus n'est donc pas mesuré dans son état visible.
- **Contrastes — ce qui ne l'est pas** : un texte posé sur une image ou un dégradé est laissé de côté, sans avertissement. Les états au survol et au focus, les textes des champs de formulaire (`placeholder`), les pseudo-éléments (`::before`) et les images de texte ne sont pas mesurés. Les composants d'interface et les éléments graphiques (critère 3.3) non plus.
- Une page qui passe ces mesures n'a pas pour autant des contrastes conformes : seule une vérification humaine le dit.
- **Parcours au clavier — ce qui est contrôlé** : le contexte envoie de vraies frappes de la touche Tab depuis le haut de la page. Il signale un piège (la touche Tab ne fait pas quitter un élément, critère 12.9) et les éléments qui devraient être atteignables mais ne le sont jamais (12.8).
- **Parcours au clavier — ce qui ne l'est pas** : que l'ordre soit logique pour un lecteur, le retour arrière (Maj + Tab), les autres touches (flèches, Échap, Entrée), et les composants qui s'ouvrent (menus, fenêtres modales). Un élément masqué dans un menu fermé peut être signalé à tort comme jamais atteint.
- **Focus visible — ce qui est contrôlé** : pour chaque élément atteint par Tab, soit un contour est affiché, soit son apparence a changé par rapport à l'état sans focus (fond, couleur, bordure, ombre, soulignement).
- **Focus visible — ce qui ne l'est pas** : que l'indication soit assez contrastée ou assez marquée pour être vue. Un changement de couleur imperceptible suffit à satisfaire l'étape.
- **Lien d'évitement — ce qui est contrôlé** : il existe un lien d'ancre vers la zone `<main>` (ou `role="main"`) ou un élément qu'elle contient ; c'est le premier élément atteint au clavier ; une fois activé, le focus se trouve dans le contenu principal. Si la cible ne peut pas recevoir le focus, le message conseille d'ajouter `tabindex="-1"`.
- **Lien d'évitement — ce qui ne l'est pas** : que le lien devienne visible quand il reçoit le focus, et que son intitulé soit clair. Le lien est activé par la page elle-même, pas par une vraie frappe : un script qui intercepterait la touche Entrée ne serait pas détecté. Certains navigateurs déplacent le point de départ de la tabulation sans déplacer le focus ; l'étape est alors plus stricte que le navigateur.

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

## Lancer les scénarios d'essai

Le paquet contient ses propres scénarios et des pages d'essai, conformes et fautives, dans `tests/fixtures/site`. Ils servent à vérifier que les étapes fonctionnent sur votre machine avant de les utiliser sur un vrai site.

Les commandes ci-dessous se lancent depuis la racine du projet qui a installé le paquet, ici `packages/rgaa-check-behat` dans un dépôt qui le contient. Depuis le dossier du paquet lui-même, retirer `-c packages/rgaa-check-behat/behat.dist.yml` et le préfixe `packages/rgaa-check-behat/` des chemins.

### 1. Préparer

Deux programmes doivent tourner pendant les essais, chacun dans son terminal :

| Terminal | Commande | Rôle |
|---|---|---|
| 1 | `php -S 127.0.0.1:8765 -t packages/rgaa-check-behat/tests/fixtures/site` | Sert les pages d'essai à l'adresse `http://127.0.0.1:8765` |
| 2 | `chromium --headless=new --remote-debugging-port=9222 --no-sandbox` | Démarre Chromium sans affichage, pilotable par Behat sur le port 9222 |

Le terminal 2 n'est nécessaire que pour les scénarios avec navigateur. `--no-sandbox` est requis sous WSL et dans la plupart des conteneurs ; sur un poste Linux ordinaire, on peut l'enlever.

### 2. Lancer

Dans un troisième terminal :

| Je veux… | Commande | Résultat attendu |
|---|---|---|
| Les scénarios sans navigateur | `vendor/bin/behat -c packages/rgaa-check-behat/behat.dist.yml --tags='~@javascript&&~@echec'` | 4 scénarios réussis |
| Les scénarios dans Chromium | `vendor/bin/behat -c packages/rgaa-check-behat/behat.dist.yml --profile=chrome --tags='~@echec'` | 7 scénarios réussis |
| Voir les messages sur les pages fautives, dans Chromium | `vendor/bin/behat -c packages/rgaa-check-behat/behat.dist.yml --profile=chrome --tags='@echec'` | 7 scénarios en échec, c'est voulu |
| Voir le message sur une page fautive, sans navigateur | `vendor/bin/behat -c packages/rgaa-check-behat/behat.dist.yml --tags='@echec&&~@javascript'` | 1 scénario en échec, c'est voulu |
| Les tests unitaires (ni serveur ni navigateur) | `vendor/bin/phpunit packages/rgaa-check-behat/tests` | 10 tests réussis |

Ce que signifient les options :

- `-c …/behat.dist.yml` : le fichier de configuration Behat du paquet.
- `--profile=chrome` : utilise Chromium et ne garde que les scénarios marqués `@javascript`. Sans cette option, Behat lit les pages sans navigateur.
- `--tags` : choisit les scénarios. `@javascript` marque ceux qui demandent un navigateur ; `@echec` marque ceux qui **doivent échouer**, parce qu'ils portent sur une page fautive et servent à montrer le message d'erreur. `~` veut dire « sauf ».

Un scénario `@echec` qui réussirait serait donc le signe d'un problème, et inversement.

### 3. Arrêter

- Terminal 1 : Ctrl + C.
- Terminal 2 : Ctrl + C, puis `pkill -x chrome` pour être sûr qu'aucun processus ne reste.

Un Chromium à moitié arrêté fait échouer **tous** les scénarios dès l'ouverture de la page. Si cela arrive, lancer `pkill -x chrome` puis redémarrer Chromium.

### En cas de problème

| Symptôme | Cause probable |
|---|---|
| Tous les scénarios échouent sur « je suis sur … » | Le serveur du terminal 1 ne tourne pas, ou Chromium est à moitié arrêté |
| « Cette étape mesure la page affichée : elle demande un navigateur » | Scénario lancé sans `--profile=chrome`, ou non marqué `@javascript` |
| « Le navigateur n'a pas pu être réduit à 320 px » | Le navigateur refuse la largeur demandée : la mesure n'est pas faite, plutôt que faussée |
| `Class Symfony\Component\HttpClient\HttpClient not found` | `symfony/http-client` n'est pas installé (nécessaire sans navigateur) |

## Développement

Toute nouvelle étape a une page conforme et une page fautive dans `tests/fixtures/site`, un scénario qui réussit et un scénario `@echec` dans `features/`, et sa ligne dans ce README avec ses limites. La logique qui ne dépend pas du navigateur (par exemple `ContrastAudit`) a ses tests unitaires dans `tests/`.

## Licence

MIT. Voir [LICENSE](LICENSE).
