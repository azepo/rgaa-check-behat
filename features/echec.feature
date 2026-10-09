# language: fr
@echec
Fonctionnalité: Un scénario échoue sur une page fautive
  Cette fonctionnalité doit échouer : elle sert à vérifier le message affiché.

  Scénario: Une page fautive fait échouer le scénario
    Étant donné que je suis sur "/fautive.html"
    Alors la page ne doit présenter aucune erreur d'accessibilité
