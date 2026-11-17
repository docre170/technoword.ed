TechnoWorld Ed : API « catalogue de cours »

Cahier des charges commun à toutes les versions (PHP, Go, Java, Python, …) Travail pratique n° 2 de Méthode de recherche en informatique, FASI, Université protestante au Congo.

Équipe : Credo KUTANGA Kitamba · Samuel MUANZA Bimvuala · Esther CIVAVA Litsani · Gabriel MBENZA Mabonzo

1. Objectif

Écrire la même application avec plusieurs technologies, puis mesurer et comparer leur comportement sous charge croissante (temps de réponse, débit, taux d'erreurs).

L'application est le module « catalogue » de la plateforme d'e-learning TechnoWorld Ed, réduit à une API JSON volontairement simple. Toutes les versions doivent offrir exactement les mêmes fonctionnalités et renvoyer les mêmes résultats, afin que la comparaison porte sur la technologie et non sur le code métier.

2. Périmètre
Inclus	Exclu
4 routes JSON (ajout, liste, recherche, moyenne)	Authentification, sessions, rôles
Stockage dans deux fichiers JSON	Base de données (MySQL, SQLite, …)
Serveur HTTP sur 127.0.0.1:8080	Interface HTML, CORS, cookies
Réponses et erreurs toujours en JSON	Journaux (logs) par requête
3. Données

Deux fichiers dans le dossier data/, fournis et jamais régénérés par les versions :

data/cours.json : 5 000 cours

json
[{ "id": 1, "titre": "Cours 1 : Anatomie", "description": "…", "categorie": "Bio-informatique", "auteur": "Kenza David" }]

data/resultats.json : 25 000 résultats (5 par cours)

json
[{ "id": 1, "cours_id": 1, "utilisateur": "…", "score": 14.5 }]
Champ	Type	Règle
id	entier	unique, croissant
titre	texte	unique
description, categorie, auteur	texte	non vides
cours_id	entier	référence un id de cours
score	nombre	de 0 à 20

Un cours contient exactement 5 champs, dans cet ordre : id, titre, description, categorie, auteur. Aucun autre champ ne doit apparaître (pas de statut).

Empreintes de référence (SHA-256)
cours.json      46e2b8710d75e0e40517d249fdb0a8beb2ba8606a82b0af1e2ca5ad0af231bad   (1 296 972 octets)
resultats.json  e9832096a05fecb55b0c8a6fc9f3d772b3980df40de722f7d9b34769e2a4580c   (1 575 811 octets)

Vérification sous Windows :

powershell
certutil -hashfile data\cours.json SHA256
certutil -hashfile data\resultats.json SHA256

Le POST /cours modifie cours.json. Après tout test d'écriture, restaurer le fichier d'origine et revérifier l'empreinte avant de mesurer.

4. Spécification des routes

Toutes les réponses ont le type application/json; charset=utf-8.

Succès : { "success": true, "data": … }
Erreur : { "success": false, "message": "texte court en français" }
4.1 POST /cours : ajouter un cours

Corps : { "titre", "description", "categorie", "auteur" }

Cas	Code
Cours créé (id = dernier id + 1)	201 avec le cours créé
Champ absent, null, non textuel, vide ou de plus de 100 caractères	400
JSON illisible, ou corps qui n'est pas un objet	400
Titre déjà existant	409

La longueur se compte en caractères, pas en octets.

4.2 GET /cours?page=n : lister
20 cours par page, triés par id croissant ; page vaut 1 par défaut.
page non entière, nulle ou négative : 400.
Une page au-delà de la dernière renvoie "items": [] (jamais null).
json
{ "success": true, "data": { "page": 1, "par_page": 20, "total": 5000, "items": [ … ] } }
4.3 GET /cours/recherche?q=mot : rechercher
Recherche par sous-chaîne, insensible à la casse, dans le titre ou la description.
Résultats triés par id, 50 au plus ; total est le nombre de résultats renvoyés.
q absent ou vide : 400. Le paramètre est décodé (%xx, +).
json
{ "success": true, "data": { "total": 50, "items": [ … ] } }

Aucun résultat : "total": 0, "items": [].

4.4 GET /cours/{id}/moyenne : moyenne des scores
Moyenne arithmétique des score du cours, arrondie à 2 décimales avec l'arrondi classique :
floor((x + epsilon) * 100 + 0.5) / 100        epsilon = 2.220446049250313e-16

N'utiliser pas l'arrondi du langage s'il est bancaire (par exemple round() de Python).

Cas	Réponse
Cours trouvé avec des scores	200 { "cours_id": 1, "nombre_resultats": 5, "moyenne": 11.76 }
Cours trouvé sans score	200 avec "moyenne": null
Cours inexistant	404
id non numérique	400
4.5 Erreurs générales
Situation	Code
Route inconnue	404
Méthode non autorisée sur une route connue	405
Erreur interne	500 (toujours en JSON)

Jamais de page HTML ni de texte brut, y compris pour une erreur imprévue.

5. Règles d'équivalence entre versions
Mêmes fichiers de données (empreintes identiques).
Mêmes routes, mêmes codes HTTP, mêmes champs dans le même ordre.
/cours/recherche est traitée avant /cours/{id}/moyenne.
Accents en UTF-8 brut (pas de \uXXXX), / non échappé, listes vides en [], moyenne absente en null.
L'équivalence se vérifie sur le JSON décodé : codes HTTP, champ success et données. Le libellé exact des messages d'erreur peut différer.
6. Contraintes techniques
Contrainte	Valeur
Adresse et port	127.0.0.1:8080
Concurrence	8 threads, workers ou cœurs, si le modèle le permet (à documenter)
Mode d'exécution	Serveur de production, binaire compilé ou jar ; jamais un serveur de développement
Dépendances	Minimum, versions exactes notées dans le rapport
Chargement des données	Une fois au démarrage, gardées en mémoire (PHP, qui redémarre à chaque requête, relit les fichiers)
Écriture	Verrou, fichier temporaire du même dossier, puis renommage atomique
Journaux	Désactivés

Chaque version se lance depuis son propre dossier, avec son sous-dossier data/.

7. Vérification de conformité

Valeurs de référence sur les données fournies :

Requête	Résultat attendu
GET /cours?page=1	total 5000, 20 éléments de 5 champs, premier cours « Cours 1 : Anatomie »
GET /cours/recherche?q=algo	total 50, premiers id : 10, 14, 68
GET /cours/1/moyenne	nombre_resultats 5, moyenne 11.76
GET /cours/999999/moyenne	404
GET /cours/abc/moyenne	400
GET /cours?page=0	400
GET /cours/recherche?q=	400
DELETE /cours	405
GET /inconnu	404
POST /cours deux fois (même titre)	201 puis 409

Exemples de tests (PowerShell). Pour le POST, passer le corps par un fichier sans BOM et utiliser --data-binary :

powershell
curl.exe -s "http://127.0.0.1:8080/cours/1/moyenne"
curl.exe -s -i "http://127.0.0.1:8080/inconnu"

[IO.File]::WriteAllText("$PWD\nouveau.json",'{"titre":"Test unique","description":"d","categorie":"Informatique","auteur":"Moi"}',(New-Object Text.UTF8Encoding $false))
curl.exe -s -i -X POST -H "Content-Type: application/json" --data-binary "@nouveau.json" http://127.0.0.1:8080/cours
8. Protocole de mesure

Outil : k6 2.4.0, exécuté sur la même machine que le serveur (un seul serveur actif à la fois, PC branché sur le secteur).

Scénario (test_mix.js) : les utilisateurs virtuels enchaînent des requêtes sans pause, selon ce mélange, en lecture seule :

Part	Route	Paramètre tiré au hasard
50 %	GET /cours?page=n	page de 1 à 250
30 %	GET /cours/recherche?q=mot	algo, bio, réseaux, génétique, cours 1, données
20 %	GET /cours/{id}/moyenne	id de 1 à 5 000

Campagne :

Paliers : 10, 50, 100, 200 et 500 utilisateurs simultanés.
Essais de 10 s : 40 à 10 utilisateurs, 20 aux autres paliers.
Avant la série : vérifier que le serveur répond, faire un test à blanc (10 utilisateurs, 10 s) et, pour Java, un échauffement non noté.
Un fichier de résumé JSON par essai, nommé <techno>_vu<N>_<essai>.json.
powershell
k6 run -e VUS=50 -e DUREE=10s --summary-export="resultats\php_vu50_01.json" test_mix.js

Métriques : temps de réponse moyen et p95 (ms), débit (requêtes/s), taux d'erreurs (%), et CPU/mémoire du processus quand ils sont relevés.

Données brutes : les fichiers JSON sont agrégés en un CSV par technologie (donnees_brutes_<techno>.csv, une ligne par essai).

9. Lancement par technologie
Technologie	Préparation	Lancement
PHP 8.5	aucune (extension mbstring active)	php -S 127.0.0.1:8080 router.php
Go	go build -o server.exe .	.\server.exe
Java 21 (Spring Boot)	mvn -q clean package	java -Xms512m -Xmx2g -jar target\catalogue.jar
Python (Flask, Waitress)	pip install -r requirements.txt	waitress-serve --host=127.0.0.1 --port=8080 --threads=8 server:app
10. Livrables
Le code source de chaque version.
Les fichiers de résultats bruts (JSON par essai) et les CSV agrégés.
Le rapport au format article, avec ses sources LaTeX.
11. Limites connues
Une seule machine, avec k6 et le serveur en concurrence pour le processeur.
Stockage JSON : non représentatif d'une application de production.
Essais courts (10 s) et une seule campagne par version.
Les versions ne gèrent pas la concurrence de la même façon (PHP est mono-processus sous Windows, Go et Tomcat utilisent plusieurs cœurs, Node n'en utilise qu'un). Ces différences structurelles font partie de ce qui est comparé.
