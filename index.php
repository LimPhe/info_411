<?php
require_once 'include/connexion.php';
require_once 'include/fonctions.php';
// Inclure le nouveau fichier contenant les fonctions de sélection de conversation
require_once 'include/conversation_selector.php';

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

// Vérifier si une conversation est spécifiée dans l'URL
$conversation_id = 0;
if (isset($_GET['conversation_id'])) {
    $conv_id = intval($_GET['conversation_id']);
    
    // Vérifier que la conversation existe et appartient à l'utilisateur
    $query = $CONNEXION->prepare("
        SELECT id FROM conversations 
        WHERE id = ? AND user_id = ?
    ");
    $query->bind_param('ii', $conv_id, $user_id);
    $query->execute();
    $result = $query->get_result();
    
    if ($result->num_rows > 0) {
        $conversation_id = $conv_id;
    }
}

// Si l'utilisateur a explicitement demandé à changer de conversation
if (isset($_GET['action']) && $_GET['action'] === 'change_conversation') {
    $conversation_id = 0;  // Forcer l'affichage du sélecteur
}

// Traiter l'envoi d'un message uniquement si une conversation est sélectionnée
if ($conversation_id > 0 && 
    $_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['message']) && 
    isset($_POST['form_token']) && 
    isset($_SESSION['form_token']) && 
    $_POST['form_token'] === $_SESSION['form_token']) {
    
    $user_message = mysqli_real_escape_string($CONNEXION, $_POST['message']);
    $response = getAnthropicResponse($user_message);

    // Enregistrer le message de l'utilisateur
    saveMessage($conversation_id, 'user', $user_message);

    // Enregistrer la réponse du bot
    saveMessage($conversation_id, 'bot', $response);
    
    // Rediriger pour éviter le renvoi du formulaire
    header("Location: index.php?conversation_id=" . $conversation_id);
    exit();
}

// Vérifier si nous devons afficher le sélecteur ou une conversation
if ($conversation_id == 0) {
    // Nous devons afficher le sélecteur et/ou traiter la sélection
    selectConversation($user_id, $CONNEXION);
}

// Générer un nouveau token pour le formulaire
$form_token = preventFormResubmission();

include 'include/header.php';
?>

<h1>CHAT TPG*</h1>

<?php if ($conversation_id == 0): ?>
    <!-- Afficher le sélecteur de conversation -->
    <?php displayConversationSelector($user_id, $CONNEXION); ?>
<?php else: ?>
    <!-- Afficher la conversation sélectionnée -->
    <div id="chatbox">
        <?php
        // Afficher l'historique des messages pour la conversation sélectionnée
        $query = $CONNEXION->prepare("
            SELECT sender, message_text, sent_at 
            FROM messages 
            WHERE conversation_id = ? 
            ORDER BY sent_at
        ");
        $query->bind_param('i', $conversation_id);
        $query->execute();
        $result = $query->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $sender_class = $row['sender'] === 'user' ? 'user-message' : 'bot-message';
                $sender = $row['sender'] === 'user' ? 'Vous' : 'Bot';
                echo "<p class='message $sender_class'>";
                echo "<strong>$sender :</strong> " . htmlspecialchars($row['message_text']);
                echo " <em>(" . date('H:i', strtotime($row['sent_at'])) . ")</em>";
                echo "</p>";
            }
        } else {
            echo "<p class='no-messages'>Aucun message dans cette conversation. Commencez à discuter !</p>";
        }
        ?>
    </div>
    <form method="post" action="index.php?conversation_id=<?php echo $conversation_id; ?>">
        <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
        <input type="text" name="message" placeholder="Tapez votre message..." required>
        <button type="submit">Envoyer</button>
    </form>
    
    <div class="conversation-actions">
        <a href="index.php?action=change_conversation" class="btn-secondary">Changer de conversation</a>
        <!-- Option pour terminer la conversation pourrait être ajoutée ici -->
    </div>
    
    <style>
        .conversation-actions {
            width: 90%;
            max-width: 800px;
            margin: 1rem auto;
            text-align: center;
        }
        
        .btn-secondary {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            background: #f5f5f5;
            color: #2a5298;
            border: 1px solid #ddd;
            border-radius: 25px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #e9e9e9;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .no-messages {
            text-align: center;
            color: #777;
            font-style: italic;
            padding: 2rem 0;
        }
    </style>
<?php endif; ?>

<?php include 'include/footer.php'; ?>

<script>
    // Faire défiler automatiquement vers le bas du chat
    const chatbox = document.getElementById('chatbox');
    if (chatbox) {
        chatbox.scrollTop = chatbox.scrollHeight;
    }
</script>

</body>
</html>