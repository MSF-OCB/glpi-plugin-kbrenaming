# GLPI plugin kbrenaming

`kbrenaming` normalise les entrees d'inventaire logiciel correspondant aux correctifs Microsoft KB.

Dans GLPI, certains inventaires remontent les correctifs Windows comme des logiciels independants nommes `KB5034441`, `KB5021233`, etc. Ce plugin transforme ces entrees en donnees plus exploitables: le correctif KB devient une version logicielle et le logiciel porte le nom de la famille de mise a jour Microsoft.

## Objectif

Le plugin evite d'avoir une longue liste de logiciels nommes uniquement par numero KB. Il regroupe les correctifs Microsoft sous un nom logiciel comprehensible, puis conserve le numero KB comme version.

Exemple de comportement attendu:

- entree inventaire recue: `KB5034441`
- recherche des metadonnees dans le Microsoft Update Catalog
- creation ou reutilisation d'un groupe KB, par exemple une famille de mise a jour Windows
- creation ou reutilisation d'un logiciel GLPI correspondant a cette famille
- creation ou reutilisation d'une version logicielle nommee `KB5034441`
- rattachement des machines inventoriees a cette version logicielle

## Compatibilite

- GLPI: `>= 10.0.0` et `< 12.0.0`
- Plugin teste syntaxiquement avec PHP 8.2
- Le plugin est prevu pour GLPI 10 et GLPI 11

Le plugin est installe dans GLPI avec le nom technique `kbrenaming`.

## Fonctionnalites

- Detection des logiciels dont le nom correspond au motif `KB` suivi d'au moins six chiffres.
- Enrichissement des KB depuis le Microsoft Update Catalog.
- Creation automatique des donnees KB manquantes.
- Regroupement des KB par famille de mise a jour.
- Creation des versions logicielles GLPI correspondant aux numeros KB.
- Reassociation des installations logicielles vers la version logicielle normalisee.
- Hooks d'inventaire pour modifier les donnees logiciel avant leur integration.
- Dropdowns GLPI pour administrer les KB et les groupes de KB.
- Rapport GLPI pour analyser une KB par entite et version de systeme d'exploitation.
- Commandes console de recherche et de migration batch presentes dans le code du plugin.

## Fonctionnement technique

### Detection des KB

Le plugin traite uniquement les noms qui respectent le format:

```text
KB123456
KB1234567
kb5034441
```

Les autres logiciels sont ignores.

### Recherche Microsoft Update Catalog

Quand une KB n'existe pas encore dans les tables du plugin, le plugin interroge:

```text
https://www.catalog.update.microsoft.com
```

Le resultat est parse pour retrouver:

- le titre de la mise a jour
- la categorie Microsoft
- le commentaire descriptif
- la famille de mise a jour a utiliser comme logiciel GLPI

Les appels externes sont limites par timeout et nombre de tentatives pour eviter les blocages si le service Microsoft est lent ou indisponible.

### Normalisation dans GLPI

Quand un logiciel `KBxxxxxx` est detecte, le plugin:

1. recherche ou cree l'entree KB dans `glpi_plugin_kbrenaming_kbs`
2. recherche ou cree le groupe dans `glpi_plugin_kbrenaming_kbgroups`
3. recherche ou cree le logiciel GLPI correspondant a la famille de mise a jour
4. recherche ou cree la version logicielle correspondant au numero KB
5. deplace les relations d'installation vers la version logicielle normalisee
6. supprime l'ancien logiciel `KBxxxxxx` quand il a ete remplace

## Tables ajoutees

### `glpi_plugin_kbrenaming_kbs`

Stocke les KB connues par le plugin.

Champs principaux:

- `name`: numero KB, par exemple `KB5034441`
- `comment`: description ou titre de la mise a jour
- `plugin_kbrenaming_kbgroups_id`: groupe/famille de mise a jour
- `disabled_update`: indicateur d'administration

### `glpi_plugin_kbrenaming_kbgroups`

Stocke les familles de mise a jour utilisees comme logiciels GLPI.

Champs principaux:

- `name`: nom de la famille de mise a jour
- `comment`: commentaire libre
- `softwarecategories_id`: categorie logicielle GLPI associee

## Installation

Copier le plugin dans le dossier GLPI:

```text
glpi/plugins/kbrenaming
```

Installer puis activer le plugin:

```bash
php bin/console plugin:install kbrenaming
php bin/console plugin:activate kbrenaming
```

Dans l'image Docker MSF, le plugin est clone depuis GitHub pendant le build. Le commit utilise est pilote par l'argument Docker:

```dockerfile
ARG VERSION_PLUGIN_KBRENAMING=<commit>
```

Apres une modification du plugin, pousser le nouveau commit puis mettre a jour cet argument dans l'image Docker.

## Utilisation

### Inventaire automatique

Le cas principal est l'inventaire. Lorsqu'un agent ou un connecteur remonte un logiciel nomme `KBxxxxxx`, le plugin normalise automatiquement la donnee pendant le traitement GLPI.

### Administration manuelle

Le plugin ajoute deux dropdowns:

- `KB`
- `Groups of KB`

Ils permettent de consulter ou ajuster les KB et leurs groupes.

### Rapport

Le rapport `Summaries numbers computer by entries by OS version for one KB` permet d'analyser la presence d'une KB par entite et par version de systeme d'exploitation.

Le rapport accepte un nom de KB et une entite, puis affiche les totaux par version d'OS.

### Commandes console

Le code contient deux commandes console:

```bash
php bin/console Kbrenaming:kb:finder KB5034441
php bin/console Kbrenaming:kb:rename_software
```

La premiere recherche ou cree les informations d'une KB. La seconde applique la normalisation aux logiciels KB deja presents en base.

Si ces commandes n'apparaissent pas dans `php bin/console list`, verifier leur enregistrement dans les hooks console du plugin avant de les utiliser en production.

## Limites connues

- Le plugin depend de l'accessibilite du Microsoft Update Catalog pour enrichir une KB inconnue.
- Si le catalogue Microsoft est indisponible, la KB est ignoree sans erreur bloquante.
- Seuls les noms correspondant a `KB` suivi d'au moins six chiffres sont traites.
- Le plugin ne modifie pas les logiciels qui ne sont pas des correctifs Microsoft KB.
- Le plugin ne declare pas de compatibilite GLPI 12.

## Securite et robustesse

Le plugin inclut des protections pour limiter les erreurs frequentes:

- validation defensive des payloads d'inventaire
- timeouts et retries limites pour les appels reseau
- fallback si l'extension PHP `shmop` n'est pas disponible
- casts des identifiants avant les operations DB
- usage de l'API DB GLPI pour les suppressions et mises a jour critiques
- echappement des sorties du rapport

## Maintenance

Avant de publier une nouvelle version:

```bash
php -l setup.php
php -l hook.php
Get-ChildItem . -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

Apres publication du commit plugin, mettre a jour l'image Docker GLPI si necessaire.
