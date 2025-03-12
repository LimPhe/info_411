<?php
require_once 'include/connexion.php';
require_once 'include/fonctions.php';

session_start();

// Si un message est envoyé
if (isset($_POST['message'])) {
    $user_message = mysqli_real_escape_string($CONNEXION, $_POST['message']);
    $response = getAnthropicResponse($user_message);

    // Enregistrer le message de l'utilisateur et la réponse du bot dans la base de données
    // (Pour l'instant, on suppose qu'il n'y a qu'un utilisateur et une conversation pour simplifier)
    $user_id = 1; // À adapter avec un vrai système d'authentification
    $conversation_id = getOrCreateConversation($user_id);

    // Enregistrer le message de l'utilisateur
    saveMessage($conversation_id, 'user', $user_message);

    // Enregistrer la réponse du bot
    saveMessage($conversation_id, 'bot', $response);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Chatbot</title>
    <link rel="stylesheet" href="css/site.css">
</head>
<body>
    <?php include 'include/header.php'; ?>
    <?php include 'include/menu.php'; ?>

    <h1>Bienvenue sur notre Chatbot</h1>
    <div id="chatbox">
        <?php
        // Afficher l'historique des messages pour la conversation actuelle
        if (isset($conversation_id)) {
            $query = $CONNEXION->prepare("SELECT sender, message_text, sent_at FROM messages WHERE conversation_id = ? ORDER BY sent_at");
            $query->bind_param('i', $conversation_id);
            $query->execute();
            $result = $query->get_result();
            while ($row = $result->fetch_assoc()) {
                $sender = $row['sender'] === 'user' ? 'Vous' : 'Bot';
                echo "<p><strong>$sender :</strong> {$row['message_text']} <em>({$row['sent_at']})</em></p>";
            }
        }
        ?>
    </div>
    <form method="post" action="">
        <input type="text" name="message" placeholder="Tapez votre message..." required>
        <button type="submit">Envoyer</button>
    </form>

    <?php include 'include/footer.php'; ?>
    <script src="js/monscriptquitue.js"></script>
</body>
</html>