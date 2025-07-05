# Explorateur de Fichiers Web

Un explorateur de fichiers moderne et sécurisé avec interface web et terminal intégré.

## Fonctionnalités

- 🖥️ Interface utilisateur moderne et réactive
- 📂 Navigation dans l'arborescence des fichiers
- 📝 Édition de fichiers texte
- 📊 Aperçu des fichiers (texte, images, vidéos, PDF, etc.)
- ⚡ Terminal intégré pour les commandes système
- 📤 Téléchargement et téléversement de fichiers
- 🔄 Gestion des fichiers et dossiers (création, suppression, renommage, copie, déplacement)
- 🔒 Sécurité renforcée avec validation des chemins et gestion des erreurs
- 🚀 Optimisé pour les performances avec pagination et chargement paresseux

## Prérequis

- PHP 7.4 ou supérieur
- Serveur web (Apache, Nginx, etc.)
- Extensions PHP requises :
  - json
  - fileinfo
  - openssl
  - zip (pour la compression/décompression)

## Installation

1. Clonez le dépôt dans le répertoire de votre serveur web :
   ```bash
   git clone [URL_DU_DEPOT] .
   ```

2. Assurez-vous que le serveur web a les permissions d'écriture sur le répertoire :
   ```bash
   chmod -R 755 /chemin/vers/le/dossier
   ```

3. Configurez votre serveur web pour utiliser `file_explorer.html` comme point d'entrée.

## Utilisation

1. Accédez à l'application via votre navigateur :
   ```
   http://votre-serveur/chemin/vers/file_explorer.html
   ```

2. Naviguez dans vos fichiers en utilisant l'interface ou le terminal intégré.

3. Utilisez le clic droit pour accéder aux actions rapides sur les fichiers et dossiers.

## Fonctionnalités du Terminal

Le terminal intégré permet d'exécuter des commandes système directement depuis l'interface web. Les commandes courantes incluent :

- `ls` - Lister les fichiers
- `cd` - Changer de répertoire
- `mkdir` - Créer un dossier
- `rm` - Supprimer des fichiers/dossiers
- `cp` - Copier des fichiers
- `mv` - Déplacer/renommer des fichiers

## Sécurité

- Tous les chemins sont validés et nettoyés pour éviter les attaques par traversée de répertoires
- Les téléversements de fichiers sont sécurisés
- Les erreurs sont enregistrées dans un fichier de log séparé
- Les accès sont limités au répertoire racine configuré

## Personnalisation

Vous pouvez personnaliser l'apparence en modifiant les classes CSS dans `file_explorer.html` ou en ajustant la configuration de Tailwind CSS.

## Licence

Ce projet est sous licence MIT.

## Auteur

[Votre nom ou organisation]

## Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou une pull request.
