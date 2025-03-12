-- feed_base.sql

-- Insertion d'un utilisateur de test
INSERT INTO users (username, email) VALUES 
('test_user', 'test@example.com');

-- Insertion de réponses prédéfinies pour le chatbot
INSERT INTO predefined_responses (keyword, response_text) VALUES 
('bonjour', 'Bonjour ! Comment puis-je vous aider aujourd’hui ?'),
('aide', 'Je suis ici pour répondre à vos questions. Essayez de me demander quelque chose de spécifique !'),
('merci', 'De rien ! À bientôt !'),
('erreur', 'Désolé, je ne comprends pas. Pouvez-vous reformuler ?');