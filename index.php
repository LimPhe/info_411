<?php
require_once 'include/connexion.php';
require_once 'include/fonctions.php';

session_start();

// Vérifier si l'utilisateur est connecté (à adapter selon votre système d'authentification)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Valeur par défaut pour l'exemple

// Fonction pour empêcher le renvoi du formulaire au refresh
function preventFormResubmission() {
    if (isset($_SESSION['form_token'])) {
        unset($_SESSION['form_token']);
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['form_token'] = $token;
    return $token;
}

// Gestion des conversations
function getCurrentConversation($user_id, $connexion) {
    // Vérifier s'il existe une conversation active (non terminée)
    $query = $connexion->prepare("
        SELECT id 
        FROM conversations 
        WHERE user_id = ? AND ended_at IS NULL 
        ORDER BY started_at DESC 
        LIMIT 1
    ");
    $query->bind_param('i', $user_id);
    $query->execute();
    $result = $query->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id'];
    }
    
    // Si aucune conversation active, en créer une nouvelle
    $query = $connexion->prepare("
        INSERT INTO conversations (user_id) 
        VALUES (?)
    ");
    $query->bind_param('i', $user_id);
    $query->execute();
    return $connexion->insert_id;
}

// Si un message est envoyé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['message']) && 
    isset($_POST['form_token']) && 
    $_POST['form_token'] === $_SESSION['form_token']) {
    
    $conversation_id = getCurrentConversation($user_id, $CONNEXION);
    $user_message = mysqli_real_escape_string($CONNEXION, $_POST['message']);
    $response = getAnthropicResponse($user_message);

    // Enregistrer le message de l'utilisateur
    saveMessage($conversation_id, 'user', $user_message);

    // Enregistrer la réponse du bot
    saveMessage($conversation_id, 'bot', $response);
    
    // Rediriger pour éviter le renvoi du formulaire
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Générer un nouveau token pour le formulaire
$form_token = preventFormResubmission();

// Obtenir la conversation actuelle
$conversation_id = getCurrentConversation($user_id, $CONNEXION);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot</title>
    <link rel="stylesheet" href="css/site.css">
    <style>
        #chatbox {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ccc;
            margin-bottom: 10px;
        }
        .message {
            margin: 5px 0;
            padding: 5px;
        }
        .user-message {
            background-color: #e3f2fd;
        }
        .bot-message {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <?php include 'include/header.php'; ?>

    <h1>CHAT TPG*</h1>
    <div id="chatbox">
        <?php
        // Afficher l'historique des messages pour la conversation actuelle
        if (isset($conversation_id)) {
            $query = $CONNEXION->prepare("
                SELECT sender, message_text, sent_at 
                FROM messages 
                WHERE conversation_id = ? 
                ORDER BY sent_at
            ");
            $query->bind_param('i', $conversation_id);
            $query->execute();
            $result = $query->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $sender_class = $row['sender'] === 'user' ? 'user-message' : 'bot-message';
                $sender = $row['sender'] === 'user' ? 'Vous' : 'Bot';
                echo "<div class='message $sender_class'>";
                echo "<strong>$sender :</strong> " . htmlspecialchars($row['message_text']);
                echo " <em>(" . date('H:i', strtotime($row['sent_at'])) . ")</em>";
                echo "</div>";
            }
        }
        ?>
    </div>
    <form method="post" action="">
        <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
        <input type="text" name="message" placeholder="Tapez votre message..." required>
        <button type="submit">Envoyer</button>
    </form>

    <?php include 'include/footer.php'; ?>
    <script src="js/monscriptquitue.js"></script>
    <script>
        // Faire défiler automatiquement vers le bas du chat
        const chatbox = document.getElementById('chatbox');
        chatbox.scrollTop = chatbox.scrollHeight;
    </script>
</body>
</html>