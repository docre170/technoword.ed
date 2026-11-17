# Cahier des charges — API « catalogue de cours » TechnoWorld Ed

> **Travail pratique n° 2 — Méthode de recherche en informatique**
> FASI, Université protestante au Congo
> **Équipe :** Credo KUTANGA Kitamba · Samuel MUANZA Bimvuala · Esther CIVAVA Litsani · Gabriel MBENZA Mabonzo

---

## Table des matières

1. Contexte et justification
2. Objectifs
3. Périmètre
4. Acteurs et cas d'utilisation
5. Données
6. Spécification des routes
7. Règles d'équivalence entre versions
8. Contraintes techniques
9. Critères d'acceptation
10. Protocole de mesure
11. Lancement par technologie
12. Livrables
13. Risques et limites connues
14. Démarrage rapide

---

## 1. Contexte et justification

### 1.1 Contexte

La plateforme d'e-learning **TechnoWorld Ed** doit servir un catalogue de cours à un nombre croissant d'utilisateurs. Le choix de la technologie qui portera ce catalogue est une décision d'ingénierie qui engage le coût d'infrastructure, l'expérience utilisateur et la maintenabilité du système. Dans un contexte où les ressources matérielles et la connexion Internet sont limitées, la question du dimensionnement — combien d'utilisateurs simultanés un serveur modeste peut-il servir ? — devient critique.

### 1.2 Problème

Les affirmations du type « Go est plus rapide que PHP », « Java tient mieux la charge » ou « Node.js est fait pour le temps réel » circulent sans que l'on sache sur quelles mesures elles reposent. Un choix technologique fondé sur des impressions est un risque : il peut conduire à surdimensionner (coût inutile) ou sous-dimensionner (service dégradé).

### 1.3 Question de recherche

Comment se comporte une même application web, écrite dans plusieurs technologies, lorsque le nombre d'utilisateurs simultanés augmente, et à partir de quelle charge cesse-t-elle de répondre correctement ?

### 1.4 Hypothèses

- **H1** — Le débit de chaque version atteint un plafond dès les faibles charges, après quoi le temps de réponse croît proportionnellement au nombre d'utilisateurs (loi de Little).
- **H2** — Les plafonds de Go, Java et Python sont nettement plus élevés que celui de PHP, car la version PHP relit ses fichiers à chaque requête avec un serveur mono-processus.
- **H3** — PHP cesse de répondre avant 500 utilisateurs, alors que Go, Java et Python tiennent ce niveau.

### 1.5 Contribution attendue

Ce travail produit **des données originales** sur le comportement de quatre technologies dans des conditions contrôlées, et les interprète avec prudence. Il ne prétend pas classer les langages en général, mais documenter ce que valent **ces configurations précises** sur **cette machine précise**.

---

## 2. Objectifs

### 2.1 Objectif principal

Écrire la même application dans plusieurs technologies, puis mesurer et comparer leur comportement sous charge croissante.

### 2.2 Objectifs secondaires

1. **Observer** le système : comparer l'état initial (PHP) aux états modifiés (Go, Java, Python).
2. **Améliorer** le système : identifier le goulot d'étranglement de PHP et proposer une variante (PHP-FPM) qui le lève.
3. **Documenter** la démarche de manière reproductible : protocole, métriques, données brutes, analyse.

### 2.3 Indicateurs de réussite

- Les quatre versions produisent des réponses JSON **identiques** sur les requêtes de contrôle.
- Chaque version est mesurée sur **au moins 100 essais** répartis sur 5 paliers de charge.
- Le rapport contient un **tableau comparatif**, des **graphiques**, et une **interprétation** qui distingue les effets du langage, du serveur et du stockage.

---

## 3. Périmètre

### 3.1 Inclus

| Élément | Détail |
|---|---|
| **4 routes JSON** | Ajout, liste, recherche, moyenne |
| **Stockage** | Deux fichiers JSON (`cours.json`, `resultats.json`) |
| **Serveur HTTP** | `127.0.0.1:8080` |
| **Réponses** | Toujours JSON, jamais HTML |
| **Versions** | PHP, Go, Java, Python (au minimum 4) |

### 3.2 Exclu

| Élément | Raison |
|---|---|
| Authentification, sessions, rôles | Hors périmètre de la comparaison de performance |
| Base de données (MySQL, SQLite) | Introduirait un facteur confondant (accès DB vs accès fichier) |
| Interface HTML, CORS, cookies | L'API est consommée par un client technique, pas par un navigateur |
| Journaux par requête | Fausseraient les mesures (I/O disque) |
| Déploiement en production | Le TP mesure en local, sur une seule machine |

### 3.3 Ce qui est explicitement hors sujet

- Une version **Python avec MySQL/SQLAlchemy/Jinja2** existe par ailleurs (application complète TechnoWorld Ed). Elle n'est **pas** mesurée dans ce TP, car elle diffère sur trois plans (base de données, ORM, templates HTML) qui empêchent une comparaison équitable.

---

## 4. Acteurs et cas d'utilisation

### 4.1 Acteurs

| Acteur | Rôle |
|---|---|
| **Client HTTP** (k6, curl) | Consomme l'API, envoie des requêtes, reçoit du JSON |
| **Serveur applicatif** | Traite les requêtes, lit/écrit les fichiers JSON, renvoie du JSON |
| **Expérimentateur** | Lance les campagnes, agrège les données, interprète les résultats |

### 4.2 Cas d'utilisation

| Cas | Route | Description |
|---|---|---|
| **UC-1** | `POST /cours` | Ajouter un cours au catalogue |
| **UC-2** | `GET /cours?page=n` | Lister les cours par page de 20 |
| **UC-3** | `GET /cours/recherche?q=mot` | Rechercher un cours par mot-clé |
| **UC-4** | `GET /cours/{id}/moyenne` | Calculer la moyenne des scores d'un cours |
| **UC-5** | Toute route inconnue | Renvoyer 404 en JSON |
| **UC-6** | Méthode non autorisée | Renvoyer 405 en JSON |

---

## 5. Données

### 5.1 Fichiers fournis

Deux fichiers dans `data/`, **fournis** et **jamais régénérés** par les versions.

**`data/cours.json`** — 5 000 cours :

```json
[
  {
    "id": 1,
    "titre": "Cours 1 : Anatomie",
    "description": "…",
    "categorie": "Bio-informatique",
    "auteur": "Kenza David"
  }
]
```

**`data/resultats.json`** — 25 000 résultats (5 par cours) :

```json
[
  {
    "id": 1,
    "cours_id": 1,
    "utilisateur": "…",
    "score": 14.5
  }
]
```

### 5.2 Règles de validation

| Champ | Type | Règle |
|---|---|---|
| `id` | entier | unique, croissant |
| `titre` | texte | **unique** |
| `description`, `categorie`, `auteur` | texte | non vides |
| `cours_id` | entier | référence un `id` de cours |
| `score` | nombre | de 0 à 20 |

> **Un cours contient exactement 5 champs, dans cet ordre :** `id`, `titre`, `description`, `categorie`, `auteur`. **Aucun autre champ** (pas de `statut`).

### 5.3 Empreintes de référence (SHA-256)

```
cours.json      46e2b8710d75e0e40517d249fdb0a8beb2ba8606a82b0af1e2ca5ad0af231bad   (1 296 972 octets)
resultats.json  e9832096a05fecb55b0c8a6fc9f3d772b3980df40de722f7d9b34769e2a4580c   (1 575 811 octets)
```

**Vérification sous Windows :**

```powershell
certutil -hashfile data\cours.json SHA256
certutil -hashfile data\resultats.json SHA256
```

> **Important :** `POST /cours` modifie `cours.json`. Après tout test d'écriture, **restaurer le fichier d'origine** et **revérifier l'empreinte** avant de mesurer.

---

## 6. Spécification des routes

Toutes les réponses ont le type `application/json; charset=utf-8`.

### 6.1 Format des réponses

| Cas | Format |
|---|---|
| Succès | `{ "success": true, "data": … }` |
| Erreur | `{ "success": false, "message": "texte court en français" }` |

### 6.2 `POST /cours` — Ajouter un cours

**Corps attendu :** `{ "titre", "description", "categorie", "auteur" }`

| Cas | Code HTTP |
|---|---|
| Cours créé (`id` = dernier `id` + 1) | **201** avec le cours créé |
| Champ absent, `null`, non textuel, vide ou > 100 caractères | **400** |
| JSON illisible, ou corps qui n'est pas un objet | **400** |
| Titre déjà existant | **409** |

> La longueur se compte en **caractères**, pas en octets.

### 6.3 `GET /cours?page=n` — Lister

- **20 cours par page**, triés par `id` croissant ; `page` vaut **1** par défaut.
- `page` non entière, nulle ou négative : **400**.
- Une page au-delà de la dernière renvoie `"items": []` (jamais `null`).

**Réponse :**

```json
{
  "success": true,
  "data": {
    "page": 1,
    "par_page": 20,
    "total": 5000,
    "items": [ … ]
  }
}
```

### 6.4 `GET /cours/recherche?q=mot` — Rechercher

- Recherche par **sous-chaîne**, **insensible à la casse**, dans le `titre` **ou** la `description`.
- Résultats triés par `id`, **50 au plus** ; `total` est le nombre de résultats **renvoyés**.
- `q` absent ou vide : **400**. Le paramètre est décodé (`%xx`, `+`).

**Réponse :**

```json
{
  "success": true,
  "data": {
    "total": 50,
    "items": [ … ]
  }
}
```

**Aucun résultat :** `{ "total": 0, "items": [] }`

### 6.5 `GET /cours/{id}/moyenne` — Moyenne des scores

- Moyenne arithmétique des `score` du cours, arrondie à **2 décimales** avec l'arrondi classique.

**Formule d'arrondi :**

```
floor((x + epsilon) * 100 + 0.5) / 100        epsilon = 2.220446049250313e-16
```

> **N'utilisez PAS** l'arrondi du langage s'il est bancaire (par exemple `round()` de Python).

| Cas | Réponse |
|---|---|
| Cours trouvé avec scores | **200** `{ "cours_id": 1, "nombre_resultats": 5, "moyenne": 11.76 }` |
| Cours trouvé sans score | **200** avec `"moyenne": null` |
| Cours inexistant | **404** |
| `id` non numérique | **400** |

### 6.6 Erreurs générales

| Situation | Code |
|---|---|
| Route inconnue | **404** |
| Méthode non autorisée sur une route connue | **405** |
| Erreur interne | **500** (toujours en JSON) |

> **Jamais** de page HTML ni de texte brut, y compris pour une erreur imprévue.

---

## 7. Règles d'équivalence entre versions

1. **Mêmes fichiers de données** (empreintes identiques).
2. **Mêmes routes, mêmes codes HTTP, mêmes champs dans le même ordre.**
3. `/cours/recherche` est traitée **avant** `/cours/{id}/moyenne`.
4. Accents en UTF-8 brut (pas de `\uXXXX`), `/` non échappé, listes vides en `[]`, moyenne absente en `null`.
5. L'équivalence se vérifie sur le **JSON décodé** : codes HTTP, champ `success` et données. Le libellé exact des messages d'erreur peut différer.

---

## 8. Contraintes techniques

| Contrainte | Valeur |
|---|---|
| **Adresse et port** | `127.0.0.1:8080` |
| **Concurrence** | 8 threads, workers ou cœurs si le modèle le permet (à documenter) |
| **Mode d'exécution** | Serveur de production, binaire compilé ou jar ; jamais un serveur de développement |
| **Dépendances** | Minimum, versions exactes notées dans le rapport |
| **Chargement des données** | Une fois au démarrage, gardées en mémoire (PHP, qui redémarre à chaque requête, relit les fichiers) |
| **Écriture** | Verrou, fichier temporaire du même dossier, puis renommage atomique |
| **Journaux** | Désactivés |

> Chaque version se lance depuis **son propre dossier**, avec son sous-dossier `data/`.

---

## 9. Critères d'acceptation

Une version est **acceptée** si et seulement si les 10 contrôles suivants passent. Sinon, elle est **rejetée** et doit être corrigée avant toute mesure.

### 9.1 Contrôles de conformité fonctionnelle

| # | Requête | Résultat attendu | Statut |
|---|---|---|---|
| 1 | `GET /cours?page=1` | `total` 5000, 20 éléments de 5 champs, premier cours « Cours 1 : Anatomie » | ☐ |
| 2 | `GET /cours/recherche?q=algo` | `total` 50, premiers `id` : 10, 14, 68 | ☐ |
| 3 | `GET /cours/1/moyenne` | `nombre_resultats` 5, `moyenne` 11.76 | ☐ |
| 4 | `GET /cours/999999/moyenne` | 404 | ☐ |
| 5 | `GET /cours/abc/moyenne` | 400 | ☐ |
| 6 | `GET /cours?page=0` | 400 | ☐ |
| 7 | `GET /cours/recherche?q=` | 400 | ☐ |
| 8 | `DELETE /cours` | 405 | ☐ |
| 9 | `GET /inconnu` | 404 (JSON) | ☐ |
| 10 | `POST /cours` deux fois (même titre) | 201 puis 409 | ☐ |

### 9.2 Contrôles de conformité des données

| # | Contrôle | Statut |
|---|---|---|
| 11 | `certutil -hashfile data\cours.json SHA256` = `46e2b87…` | ☐ |
| 12 | `certutil -hashfile data\resultats.json SHA256` = `e983209…` | ☐ |

### 9.3 Exemples de tests (PowerShell)

> Pour le POST, **passer le corps par un fichier sans BOM** et utiliser `--data-binary`.

```powershell
curl.exe -s "http://127.0.0.1:8080/cours/1/moyenne"
curl.exe -s -i "http://127.0.0.1:8080/inconnu"

[IO.File]::WriteAllText(
  "$PWD\nouveau.json",
  '{"titre":"Test unique","description":"d","categorie":"Informatique","auteur":"Moi"}',
  (New-Object Text.UTF8Encoding $false)
)

curl.exe -s -i -X POST -H "Content-Type: application/json" --data-binary "@nouveau.json" http://127.0.0.1:8080/cours
```

---

## 10. Protocole de mesure

### 10.1 Outil

[k6](https://grafana.com/docs/k6/) **2.4.0**, exécuté **sur la même machine** que le serveur.

> **Un seul serveur actif à la fois.** PC branché sur le secteur.

### 10.2 Scénario (`test_mix.js`)

Les utilisateurs virtuels enchaînent des requêtes **sans pause**, en lecture seule :

| Part | Route | Paramètre tiré au hasard |
|---|---|---|
| **50 %** | `GET /cours?page=n` | page de 1 à 250 |
| **30 %** | `GET /cours/recherche?q=mot` | `algo`, `bio`, `réseaux`, `génétique`, `cours 1`, `données` |
| **20 %** | `GET /cours/{id}/moyenne` | id de 1 à 5 000 |

### 10.3 Campagne

- **Paliers :** 10, 50, 100, 200 et 500 utilisateurs simultanés.
- **Essais de 10 s :** 40 essais à 10 utilisateurs, 20 essais aux autres paliers.
- **Avant la série :** vérifier que le serveur répond, faire un test à blanc (10 utilisateurs, 10 s) et, pour Java, un échauffement non noté.
- **Un fichier de résumé JSON par essai**, nommé `<techno>_vu<N>_<essai>.json`.

**Exemple :**

```powershell
k6 run -e VUS=50 -e DUREE=10s --summary-export="resultats\php_vu50_01.json" test_mix.js
```

### 10.4 Métriques relevées

- Temps de réponse **moyen** et **p95** (ms)
- **Débit** (requêtes/s)
- **Taux d'erreurs** (%)
- **CPU / mémoire** du processus (quand relevés)

### 10.5 Données brutes

Les fichiers JSON sont agrégés en un CSV par technologie :

```
donnees_brutes_<techno>.csv      (une ligne par essai)
```

---

## 11. Lancement par technologie

| Technologie | Préparation | Lancement |
|---|---|---|
| **PHP 8.5** | aucune (extension `mbstring` active) | `php -S 127.0.0.1:8080 router.php` |
| **Go** | `go build -o server.exe .` | `.\server.exe` |
| **Java 21 (Spring Boot)** | `mvn -q clean package` | `java -Xms512m -Xmx2g -jar target\catalogue.jar` |
| **Python (Flask, Waitress)** | `pip install -r requirements.txt` | `waitress-serve --host=127.0.0.1 --port=8080 --threads=8 server:app` |

---

## 12. Livrables

- Le **code source** de chaque version.
- Les **fichiers de résultats bruts** (JSON par essai).
- Les **CSV agrégés** par technologie.
- Le **rapport au format article**, avec ses sources LaTeX.
- Les **protocoles de mesure** (ce document).

---

## 13. Risques et limites connues

### 13.1 Risques identifiés

| Risque | Impact | Mitigation |
|---|---|---|
| Le serveur d'une version s'arrête pendant la campagne | Perte de données pour ce palier | Sauvegarde des JSON au fur et à mesure ; documentation du point de rupture |
| Une version n'est pas conforme (routes manquantes, HTML au lieu de JSON) | Mesures invalides | Les 10 contrôles d'acceptation (section 9) doivent passer **avant** toute mesure |
| Les données sont modifiées par un POST | Mesures faussées | Restauration et revérification de l'empreinte SHA-256 après chaque test d'écriture |
| k6 et le serveur se partagent le CPU | Débit sous-estimé | Documenté comme limite ; idéalement, mesurer depuis une autre machine |
| Coupure d'électricité | Campagne interrompue | Relancer uniquement les paliers manquants |

### 13.2 Limites structurelles (à documenter dans le rapport)

- Une seule machine, avec k6 et le serveur en concurrence pour le processeur.
- Stockage JSON : non représentatif d'une application de production.
- Essais courts (10 s) et une seule campagne par version.
- Les versions ne gèrent pas la concurrence de la même façon :
  - **PHP** est mono-processus sous Windows.
  - **Go** et **Tomcat** utilisent plusieurs cœurs.
  - **Node** n'en utilise qu'un.
  - **Python/Waitress** utilise 8 threads.
  
  Ces différences structurelles font partie de ce qui est comparé, et doivent être mentionnées dans l'interprétation.

### 13.3 Incident documenté (Python)

La campagne Python a été marquée par un incident : le premier `server.py` généré ne contenait qu'une seule route et renvoyait du HTML sur les trois autres. Le smoke test k6 a révélé **20,44 % d'erreurs**. Le serveur a été corrigé, mais les mesures recueillies avant correction sont incohérentes (débit qui chute, temps qui diminue). **Elles ont été écartées de la comparaison finale.** Cet incident illustre l'importance des contrôles d'acceptation (section 9) : ils auraient dû être passés avant la campagne.

---

## 14. Démarrage rapide

```powershell
# 1. Vérifier les données
certutil -hashfile data\cours.json SHA256
certutil -hashfile data\resultats.json SHA256

# 2. Installer les dépendances
pip install -r requirements.txt

# 3. Lancer le serveur
waitress-serve --host=127.0.0.1 --port=8080 --threads=8 server:app

# 4. Vérifier dans un autre terminal
curl.exe -s "http://127.0.0.1:8080/cours/1/moyenne"

# 5. Lancer un test de charge
k6 run -e VUS=10 -e DUREE=10s --summary-export="resultats\python_vu10_01.json" test_mix.js
```

---

**Fin du document.**
