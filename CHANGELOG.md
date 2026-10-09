# Journal des versions

## Non publié

Première version.

- Contexte Behat `RgaaContext` : quatre étapes, en français et en anglais, qui appliquent les règles de `rgaa-check` à la page en cours.
- Fonctionne sans navigateur (HTML renvoyé par le serveur) ou avec un navigateur (page une fois les scripts exécutés).
- Avec un navigateur, le document est relu directement dans la page : le contenu fourni par certains pilotes perd la balise `<html>` et son attribut `lang`, ce qui provoquait une fausse alerte sur le critère 8.3.
- Message d'échec listant chaque défaut avec son critère RGAA.
- Contexte `RgaaBrowserContext`, expérimental, qui mesure la page dans un navigateur :
  - absence de défilement horizontal à une largeur donnée, 320 px en général (RGAA 10.11) ;
  - contrastes du texte affiché (RGAA 3.2), calculés sur les couleurs réellement rendues ;
  - lien d'évitement (RGAA 12.7) : présent, premier élément atteint au clavier, et déplaçant le focus dans le contenu principal ;
  - parcours au clavier par de vraies frappes de Tab : piège au clavier (RGAA 12.9), éléments jamais atteints (12.8) ;
  - focus visible sur chaque élément atteint (RGAA 10.7).
