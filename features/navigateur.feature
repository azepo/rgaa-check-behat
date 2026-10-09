# language: fr
@javascript
Fonctionnalité: Mesures de la page affichée
  Les étapes du contexte RgaaBrowserContext mesurent la page dans un navigateur.

  Scénario: Une page conforme tient dans 320 pixels et ses contrastes sont suffisants
    Étant donné que je suis sur "/conforme.html"
    Alors la page ne doit pas défiler horizontalement à 320 pixels de large
    Et les contrastes du texte doivent être suffisants

  Scénario: Les contrastes sont mesurés sur les couleurs affichées
    Étant donné que je suis sur "/contraste.html"
    Alors la page ne doit pas défiler horizontalement à 320 pixels de large

  @echec
  Scénario: Un élément trop large fait défiler la page à 320 pixels
    Étant donné que je suis sur "/large.html"
    Alors la page ne doit pas défiler horizontalement à 320 pixels de large

  @echec
  Scénario: Un texte peu contrasté est signalé
    Étant donné que je suis sur "/contraste.html"
    Alors les contrastes du texte doivent être suffisants

  Scénario: Le lien d'évitement d'une page conforme mène au contenu
    Étant donné que je suis sur "/conforme.html"
    Alors le lien d'évitement doit mener au contenu principal

  @echec
  Scénario: Un lien d'évitement dont la cible ne reçoit pas le focus
    Étant donné que je suis sur "/evitement-sans-focus.html"
    Alors le lien d'évitement doit mener au contenu principal

  @echec
  Scénario: Un lien d'évitement placé après d'autres liens
    Étant donné que je suis sur "/evitement-tardif.html"
    Alors le lien d'évitement doit mener au contenu principal

  @echec
  Scénario: Une page sans lien d'évitement vers le contenu
    Étant donné que je suis sur "/evitement-absent.html"
    Alors le lien d'évitement doit mener au contenu principal

  Scénario: Une page conforme se parcourt au clavier, avec un focus visible
    Étant donné que je suis sur "/conforme.html"
    Alors on doit pouvoir parcourir la page au clavier
    Et le focus doit être visible sur chaque élément atteint au clavier

  @echec
  Scénario: Un champ qui retient la touche Tab est un piège au clavier
    Étant donné que je suis sur "/piege.html"
    Alors on doit pouvoir parcourir la page au clavier

  @echec
  Scénario: Un lien dont le focus n'est pas indiqué
    Étant donné que je suis sur "/sans-focus.html"
    Alors le focus doit être visible sur chaque élément atteint au clavier

  Scénario: Un texte masqué visuellement n'est pas mesuré
    Étant donné que je suis sur "/masque.html"
    Alors les contrastes du texte doivent être suffisants
