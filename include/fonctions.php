<?php
require_once __DIR__ . '/../config.php';

function getAnthropicResponse($user_message) {
    $api_key = ANTHROPIC_API_KEY;
    $url = 'https://api.anthropic.com/v1/messages'; // URL de l'API Anthropic (vérifie la documentation officielle)

    // Préparer les données à envoyer à l'API
    $data = [
        'model' => 'claude-3.7-sonnet-20250219', // Modèle à utiliser (vérifie les modèles disponibles dans la doc)
        'max_tokens' => 1000, // Limite de tokens pour la réponse
        'messages' => [
            [
                'role' => 'user',
                'content' => $user_message
            ]
        ]
    ];

    // Initialiser cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01' // Vérifie la version de l'API dans la documentation
    ]);

    // Exécuter la requête
    $response = curl_exec($ch);

    // Vérifier les erreurs
    if (curl_errno($ch)) {
        return "Erreur lors de l'appel à l'API : " . curl_error($ch);
    }

    // Fermer la connexion cURL
    curl_close($ch);

    // Décoder la réponse JSON
    $response_data = json_decode($response, true);

    // Vérifier si la réponse contient une erreur
    if (isset($response_data['error'])) {
        return "Erreur API : " . $response_data['error']['message'];
    }

    // Extraire la réponse du modèle
    return $response_data['content'][0]['text'] ?? "Désolé, je n'ai pas pu générer une réponse.";
}

// Fonction pour obtenir ou créer une conversation
function getOrCreateConversation($user_id) {
    global $CONNEXION;
    $query = $CONNEXION->prepare("SELECT id FROM conversations WHERE user_id = ? AND ended_at IS NULL");
    $query->bind_param('i', $user_id);
    $query->execute();
    $result = $query->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id'];
    } else {
        $query = $CONNEXION->prepare("INSERT INTO conversations (user_id) VALUES (?)");
        $query->bind_param('i', $user_id);
        $query->execute();
        return $CONNEXION->insert_id;
    }
}

// Fonction pour enregistrer un message
function saveMessage($conversation_id, $sender, $message_text) {
    global $CONNEXION;
    $query = $CONNEXION->prepare("INSERT INTO messages (conversation_id, sender, message_text) VALUES (?, ?, ?)");
    $query->bind_param('iss', $conversation_id, $sender, $message_text);
    $query->execute();
}

function saveMessage($conversation_id, $sender, $message) {
    global $CONNEXION;
    $query = $CONNEXION->prepare("
        INSERT INTO messages (conversation_id, sender, message_text) 
        VALUES (?, ?, ?)
    ");
    $query->bind_param('iss', $conversation_id, $sender, $message);
    return $query->execute();
}

// Fonction pour terminer une conversation
function endConversation($conversation_id) {
    global $CONNEXION;
    $query = $CONNEXION->prepare("
        UPDATE conversations 
        SET ended_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $query->bind_param('i', $conversation_id);
    return $query->execute();
}