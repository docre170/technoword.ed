-- TechnoWorld Ed — schéma MySQL complet et données de démonstration.
-- Importer ce fichier une fois dans phpMyAdmin ou avec le client MySQL.

CREATE DATABASE IF NOT EXISTS technoworld_ed
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE technoworld_ed;

CREATE TABLE IF NOT EXISTS utilisateur (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('apprenant', 'enseignant', 'admin') NOT NULL DEFAULT 'apprenant',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_utilisateur_email (email),
    KEY idx_utilisateur_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorie (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categorie_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cours (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titre VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    statut ENUM('brouillon', 'en_attente', 'publie', 'refuse')
        NOT NULL DEFAULT 'en_attente',
    prix DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
    acces ENUM('gratuit', 'premium') NOT NULL DEFAULT 'gratuit',
    auteur_id INT UNSIGNED NOT NULL,
    categorie_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cours_statut_categorie (statut, categorie_id),
    KEY idx_cours_auteur (auteur_id),
    CONSTRAINT fk_cours_auteur
        FOREIGN KEY (auteur_id) REFERENCES utilisateur (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_cours_categorie
        FOREIGN KEY (categorie_id) REFERENCES categorie (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_cours_prix_non_negatif CHECK (prix >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lecon (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titre VARCHAR(200) NOT NULL,
    contenu LONGTEXT NOT NULL,
    ordre INT UNSIGNED NOT NULL,
    cours_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lecon_ordre_cours (cours_id, ordre),
    CONSTRAINT fk_lecon_cours
        FOREIGN KEY (cours_id) REFERENCES cours (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titre VARCHAR(200) NOT NULL,
    cours_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quiz_cours (cours_id),
    CONSTRAINT fk_quiz_cours
        FOREIGN KEY (cours_id) REFERENCES cours (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enonce TEXT NOT NULL,
    quiz_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_question_quiz (quiz_id),
    CONSTRAINT fk_question_quiz
        FOREIGN KEY (quiz_id) REFERENCES quiz (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reponse (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    texte TEXT NOT NULL,
    est_correcte TINYINT(1) NOT NULL DEFAULT 0,
    question_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reponse_question (question_id),
    CONSTRAINT fk_reponse_question
        FOREIGN KEY (question_id) REFERENCES question (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_reponse_est_correcte CHECK (est_correcte IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscription (
    utilisateur_id INT UNSIGNED NOT NULL,
    cours_id INT UNSIGNED NOT NULL,
    progression TINYINT UNSIGNED NOT NULL DEFAULT 0,
    date_inscription TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (utilisateur_id, cours_id),
    KEY idx_inscription_cours (cours_id),
    CONSTRAINT fk_inscription_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_inscription_cours
        FOREIGN KEY (cours_id) REFERENCES cours (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_inscription_progression CHECK (progression BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resultat (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id INT UNSIGNED NOT NULL,
    quiz_id INT UNSIGNED NOT NULL,
    score INT UNSIGNED NOT NULL,
    total INT UNSIGNED NOT NULL,
    date_passage TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resultat_utilisateur_quiz (utilisateur_id, quiz_id),
    KEY idx_resultat_quiz (quiz_id),
    CONSTRAINT fk_resultat_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_resultat_quiz
        FOREIGN KEY (quiz_id) REFERENCES quiz (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_resultat_score CHECK (score <= total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comptes de démonstration : mot de passe commun "TechnoDemo2026!".
-- Les INSERT conditionnels rendent le chargement des données répétable.
INSERT INTO utilisateur (nom, email, mot_de_passe, role)
SELECT 'Administrateur TechnoWorld', 'admin@technoworld.test',
       '$2y$12$UOiYh8PHWlSsbNsIF8irJ.kdo4F.WudmZx32yS7LygsvWczBEUyDW', 'admin'
WHERE NOT EXISTS (
    SELECT 1 FROM utilisateur WHERE email = 'admin@technoworld.test'
);

INSERT INTO utilisateur (nom, email, mot_de_passe, role)
SELECT 'Enseignant Demo', 'enseignant@technoworld.test',
       '$2y$12$UOiYh8PHWlSsbNsIF8irJ.kdo4F.WudmZx32yS7LygsvWczBEUyDW', 'enseignant'
WHERE NOT EXISTS (
    SELECT 1 FROM utilisateur WHERE email = 'enseignant@technoworld.test'
);

INSERT INTO utilisateur (nom, email, mot_de_passe, role)
SELECT 'Amina Etudiante', 'amina@technoworld.test',
       '$2y$12$UOiYh8PHWlSsbNsIF8irJ.kdo4F.WudmZx32yS7LygsvWczBEUyDW', 'apprenant'
WHERE NOT EXISTS (
    SELECT 1 FROM utilisateur WHERE email = 'amina@technoworld.test'
);

INSERT INTO utilisateur (nom, email, mot_de_passe, role)
SELECT 'Karim Etudiant', 'karim@technoworld.test',
       '$2y$12$UOiYh8PHWlSsbNsIF8irJ.kdo4F.WudmZx32yS7LygsvWczBEUyDW', 'apprenant'
WHERE NOT EXISTS (
    SELECT 1 FROM utilisateur WHERE email = 'karim@technoworld.test'
);

INSERT INTO categorie (nom)
SELECT 'Informatique'
WHERE NOT EXISTS (SELECT 1 FROM categorie WHERE nom = 'Informatique');

INSERT INTO categorie (nom)
SELECT 'Biomédecine'
WHERE NOT EXISTS (SELECT 1 FROM categorie WHERE nom = 'Biomédecine');

INSERT INTO categorie (nom)
SELECT 'Bio-informatique'
WHERE NOT EXISTS (SELECT 1 FROM categorie WHERE nom = 'Bio-informatique');

INSERT INTO cours (titre, description, statut, prix, acces, auteur_id, categorie_id)
SELECT
    'Les fondamentaux de Python',
    'Découvrez les bases de Python : variables, structures de contrôle et fonctions.',
    'publie', 0.00, 'gratuit', u.id, c.id
FROM utilisateur AS u
JOIN categorie AS c ON c.nom = 'Informatique'
WHERE u.email = 'enseignant@technoworld.test'
  AND NOT EXISTS (
      SELECT 1 FROM cours
      WHERE titre = 'Les fondamentaux de Python' AND auteur_id = u.id
  );

INSERT INTO cours (titre, description, statut, prix, acces, auteur_id, categorie_id)
SELECT
    'Introduction à la bio-informatique',
    'Explorez les notions essentielles de l’analyse informatique des données biologiques.',
    'publie', 0.00, 'gratuit', u.id, c.id
FROM utilisateur AS u
JOIN categorie AS c ON c.nom = 'Bio-informatique'
WHERE u.email = 'enseignant@technoworld.test'
  AND NOT EXISTS (
      SELECT 1 FROM cours
      WHERE titre = 'Introduction à la bio-informatique' AND auteur_id = u.id
  );

INSERT INTO lecon (titre, contenu, ordre, cours_id)
SELECT 'Premiers pas avec Python',
       'Python est un langage polyvalent. Dans cette leçon, prenez en main l’interpréteur et écrivez votre premier programme.',
       1, c.id
FROM cours AS c
WHERE c.titre = 'Les fondamentaux de Python'
  AND NOT EXISTS (
      SELECT 1 FROM lecon WHERE cours_id = c.id AND ordre = 1
  );

INSERT INTO lecon (titre, contenu, ordre, cours_id)
SELECT 'Variables et types',
       'Découvrez les variables, les nombres, les chaînes de caractères et les booléens en Python.',
       2, c.id
FROM cours AS c
WHERE c.titre = 'Les fondamentaux de Python'
  AND NOT EXISTS (
      SELECT 1 FROM lecon WHERE cours_id = c.id AND ordre = 2
  );

INSERT INTO lecon (titre, contenu, ordre, cours_id)
SELECT 'Données biologiques et séquences',
       'Une séquence biologique peut être représentée et analysée sous forme de données. Cette leçon présente les concepts de base.',
       1, c.id
FROM cours AS c
WHERE c.titre = 'Introduction à la bio-informatique'
  AND NOT EXISTS (
      SELECT 1 FROM lecon WHERE cours_id = c.id AND ordre = 1
  );

INSERT INTO lecon (titre, contenu, ordre, cours_id)
SELECT 'Principes de l’analyse informatique',
       'Découvrez comment les outils informatiques facilitent l’exploration des données issues du vivant.',
       2, c.id
FROM cours AS c
WHERE c.titre = 'Introduction à la bio-informatique'
  AND NOT EXISTS (
      SELECT 1 FROM lecon WHERE cours_id = c.id AND ordre = 2
  );

INSERT INTO quiz (titre, cours_id)
SELECT 'Quiz — bases de Python', c.id
FROM cours AS c
WHERE c.titre = 'Les fondamentaux de Python'
  AND NOT EXISTS (
      SELECT 1 FROM quiz WHERE cours_id = c.id AND titre = 'Quiz — bases de Python'
  );

INSERT INTO question (enonce, quiz_id)
SELECT 'Quel mot-clé permet de définir une fonction en Python ?', q.id
FROM quiz AS q
JOIN cours AS c ON c.id = q.cours_id
WHERE c.titre = 'Les fondamentaux de Python'
  AND q.titre = 'Quiz — bases de Python'
  AND NOT EXISTS (
      SELECT 1 FROM question
      WHERE quiz_id = q.id
        AND enonce = 'Quel mot-clé permet de définir une fonction en Python ?'
  );

INSERT INTO reponse (texte, est_correcte, question_id)
SELECT 'def', 1, qu.id
FROM question AS qu
JOIN quiz AS q ON q.id = qu.quiz_id
JOIN cours AS c ON c.id = q.cours_id
WHERE c.titre = 'Les fondamentaux de Python'
  AND qu.enonce = 'Quel mot-clé permet de définir une fonction en Python ?'
  AND NOT EXISTS (
      SELECT 1 FROM reponse
      WHERE question_id = qu.id AND texte = 'def'
  );

INSERT INTO reponse (texte, est_correcte, question_id)
SELECT 'function', 0, qu.id
FROM question AS qu
JOIN quiz AS q ON q.id = qu.quiz_id
JOIN cours AS c ON c.id = q.cours_id
WHERE c.titre = 'Les fondamentaux de Python'
  AND qu.enonce = 'Quel mot-clé permet de définir une fonction en Python ?'
  AND NOT EXISTS (
      SELECT 1 FROM reponse
      WHERE question_id = qu.id AND texte = 'function'
  );

INSERT INTO reponse (texte, est_correcte, question_id)
SELECT 'func', 0, qu.id
FROM question AS qu
JOIN quiz AS q ON q.id = qu.quiz_id
JOIN cours AS c ON c.id = q.cours_id
WHERE c.titre = 'Les fondamentaux de Python'
  AND qu.enonce = 'Quel mot-clé permet de définir une fonction en Python ?'
  AND NOT EXISTS (
      SELECT 1 FROM reponse
      WHERE question_id = qu.id AND texte = 'func'
  );
