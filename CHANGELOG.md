# Journal des versions

## Non publié

Première version.

- Contexte Behat `RgaaContext` : quatre étapes, en français et en anglais, qui appliquent les règles de `rgaa-check` à la page en cours.
- Fonctionne sans navigateur (HTML renvoyé par le serveur) ou avec un navigateur (page une fois les scripts exécutés).
- Avec un navigateur, le document est relu directement dans la page : le contenu fourni par certains pilotes perd la balise `<html>` et son attribut `lang`, ce qui provoquait une fausse alerte sur le critère 8.3.
- Message d'échec listant chaque défaut avec son critère RGAA.
