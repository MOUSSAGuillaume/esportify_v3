-- MOTS DE PASSE: à remplacer par des hashes réels via PHP plus tard
-- Ici juste pour seed : on met une valeur placeholder (on corrigera en phase auth)

INSERT INTO users (email, password_hash, pseudo, role) VALUES
('admin@local.test', 'PLACEHOLDER_HASH', 'Admin', 'ADMIN'),
('orga@local.test', 'PLACEHOLDER_HASH', 'Orga', 'ORGANIZER'),
('player@local.test', 'PLACEHOLDER_HASH', 'Player', 'PLAYER');

INSERT INTO events (organizer_id, title, description, start_at, end_at, max_players, status)
VALUES
(2, 'Tournoi Rocket League', 'Tournoi 3v3 - niveau intermédiaire', NOW() + INTERVAL 2 DAY, NOW() + INTERVAL 2 DAY + INTERVAL 2 HOUR, 24, 'VALIDATED'),
(2, 'Tournoi Valorant', '5v5 - inscriptions ouvertes', NOW() + INTERVAL 5 DAY, NOW() + INTERVAL 5 DAY + INTERVAL 3 HOUR, 20, 'PENDING');