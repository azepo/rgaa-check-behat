# language: fr
Fonctionnalité: Contrôle d'accessibilité d'une page pendant un scénario
  Les étapes du contexte RgaaContext appliquent les règles de rgaa-check à la page en cours.

  Scénario: Une page conforme ne présente aucun défaut
    Étant donné que je suis sur "/conforme.html"
    Alors la page ne doit présenter aucune erreur d'accessibilité
    Et la page ne doit présenter ni erreur ni avertissement d'accessibilité

  Scénario: Un défaut est nommé avec son critère RGAA
    Étant donné que je suis sur "/fautive.html"
    Alors la page doit présenter le défaut d'accessibilité "image sans attribut alt : plan.png"
    Et la page doit présenter le défaut d'accessibilité "niveau de titre sauté"
    Mais la page ne doit présenter aucune erreur d'accessibilité en dehors des règles "images, titres"

  Scénario: Un avertissement ne fait pas échouer le contrôle des erreurs
    Étant donné que je suis sur "/avertissement.html"
    Alors la page ne doit présenter aucune erreur d'accessibilité
    Et la page doit présenter le défaut d'accessibilité "image avec un alt vide"

  Scénario: Sans navigateur, le contenu ajouté par un script n'est pas vu
    Étant donné que je suis sur "/dynamique.html"
    Alors la page ne doit présenter aucune erreur d'accessibilité

  @javascript
  Scénario: Avec un navigateur, le contenu ajouté par un script est contrôlé
    Étant donné que je suis sur "/dynamique.html"
    Alors la page doit présenter le défaut d'accessibilité "image sans attribut alt : carte.png"

  @javascript
  Scénario: Avec un navigateur, une page conforme reste conforme
    Étant donné que je suis sur "/conforme.html"
    Alors la page ne doit présenter aucune erreur d'accessibilité
