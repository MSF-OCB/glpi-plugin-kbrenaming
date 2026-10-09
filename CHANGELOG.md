# Changelog

## 2.1.1 — non publiée

### Corrections

- Fusion des logiciels KB (hook `item_add`/`item_update` et commande
  `plugins:kbrenaming:kb:rename_software`) : le logiciel KB et ses versions ne
  sont plus supprimés si la version cible n'a pas pu être créée ou si un
  déplacement d'installations a échoué (installations orphelines auparavant).
- La version cible est cherchée sous le logiciel cible uniquement : une
  version portant le nom du KB mais rattachée au logiciel KB lui-même n'est
  plus réutilisée puis supprimée avec ses installations.
- Rapport « KB par entité et version d'OS » : plus d'erreur fatale sous
  GLPI 11 (`$DB` hors de portée dans un script chargé par le routeur) ; il lit
  via `DBConnection::getReadConnection()` à la place de `$USEDBREPLICATE`.
- Attente entre deux requêtes au Microsoft Update Catalog bornée à 0,1 s : un
  horodatage futur en mémoire partagée (horloge reculée) bloquait la boucle
  indéfiniment.
- Installation : tables créées en `utf8mb4` et clés `int unsigned`, sans
  l'avertissement GLPI 11 « Usage of utf8_unicode_ci ».
- Suppression du double vidage de l'objet logiciel complet dans les logs de
  debug du hook.

### Compatibilité GLPI 12

- `Hooks::CSRF_COMPLIANT` n'est plus déclaré qu'avec GLPI 10 (déprécié en 11,
  supprimé en 12).
- `inc/includes.php` n'est plus inclus qu'avec GLPI 10 (front et rapport).
- Rapport : `Html::entities_deep()` et `getEntitiesRestrictRequest()`
  (dépréciés en 11, supprimés ou dépréciés en 12) remplacés.

### Outillage

- `composer lint`, `composer test` (PHPUnit) et workflow GitHub Actions sur
  PHP 8.4 ; tests unitaires des helpers de `PluginKbrenamingToolbox` et de
  `hook.php`.
