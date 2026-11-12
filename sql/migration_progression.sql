-- Migration pour une installation existante : sauvegarder la base avant import.
-- Les lectures sont conservées par apprenant et par leçon.
USE technoworld_ed;

CREATE TABLE IF NOT EXISTS lecon_lue (
    utilisateur_id INT UNSIGNED NOT NULL,
    lecon_id INT UNSIGNED NOT NULL,
    date_lecture TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (utilisateur_id, lecon_id),
    KEY idx_lecon_lue_lecon (lecon_id),
    CONSTRAINT fk_lecon_lue_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_lecon_lue_lecon
        FOREIGN KEY (lecon_id) REFERENCES lecon (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
