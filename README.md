# SecureScan
Ce site vous permet d'analyser votre repository GitHub pour dénicher les vulnérabilités ou erreurs syntaxiques que vous auriez pu manquer.

## Différents outils d'analyse

L'analyse est effectuée avec différents outils, tels que :
* **phpstan** qui permet de détecter les problèmes de syntaxe dans le code et d'autres erreurs
* **npm audit/composer audit** qui permettent de détecter les potentiels problèmes avec les versions de dépendances (pas la dernière version installée, dépendance dépréciée, ...)
* **semgrep** qui détecte les potentielles failles de sécurité du projet analysé et renvoie le code OWASP correspondant.

## Fonctionnalités disponibles

Ce site offre la possibilité de lancer une analyse d'un repo GitHub ou d'un dossier .zip du dit repo.

Un récapitulatif de l'analyse est ensuite généré, listant les erreurs trouvées et leur sévérité, attribuant une note au projet analysé.

Une analyse des principaux langages du projet est également effectuée.

## Lancement du projet en local

Pour lancer ce projet en local, il faut suivre les étapes suivantes :
1. Cloner le repo ou télécharger le zip
2. Créer un fichier .env en copiant le .env.example et y renseigner l'accès à la base de données
3. Lancer les commandes :
```
composer install
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate
```
4. Il faut ensuite télécharger semgrep, qui s'installe côté serveur :
    - Si Python n'est pas installé :
        - Taper `python` dans le terminal, ce qui ouvre la fenêtre du Microsoft Store (uniquement sur Windows)
        - Accepter chaque étape sauf la dernière
        - Dans le menu Démarrer, chercher "Modifier les variables d'environnement"
            - Cliquer sur "Variables d'environnement"
            - Double-cliquer sur "Path", puis "Nouveau"
            - Ajouter `C:\Users\[user]\AppData\Local\Python\pythoncore-3.14-64\Scripts\` pour que les scripts Python tels que semgrep soient exécutables
            - Redémarrer la machine pour que le chemin soit bien ajouté au Path
5. Se connecter et lancer l'analyse d'un projet, elle devrait se lancer sans problème