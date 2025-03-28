<?php
require_once 'include/connexion.php';
require_once 'include/fonctions.php';
require_once 'include/conversation_selector.php';

session_start();

// Make sure the user exists before proceeding
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// Check if user exists and create if needed
$check_user = $CONNEXION->prepare("SELECT id FROM users WHERE id = ?");
$check_user->bind_param('i', $user_id);
$check_user->execute();
$user_result = $check_user->get_result();

if ($user_result->num_rows == 0) {
    // User doesn't exist, create a default user
    $create_user = $CONNEXION->prepare("INSERT INTO users (id, username, email) VALUES (?, 'default_user', 'default@example.com')");
    $create_user->bind_param('i', $user_id);
    $create_user->execute();
}

// Continue with the rest of your code
function preventFormResubmission() {
    if (isset($_SESSION['form_token'])) {
        unset($_SESSION['form_token']);
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['form_token'] = $token;
    return $token;
}


$conversation_id = 0;
if (isset($_GET['conversation_id'])) {
    $conv_id = intval($_GET['conversation_id']);
    

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


if (isset($_GET['action']) && $_GET['action'] === 'new_conversation') {
    $query = $CONNEXION->prepare("INSERT INTO conversations (user_id) VALUES (?)");
    $query->bind_param('i', $user_id);
    $query->execute();
    $conversation_id = $CONNEXION->insert_id;
    

    header("Location: index.php?conversation_id=" . $conversation_id);
    exit();
}


if ($conversation_id > 0 && 
    $_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['message']) && 
    isset($_POST['form_token']) && 
    isset($_SESSION['form_token']) && 
    $_POST['form_token'] === $_SESSION['form_token']) {
    
    $user_message = mysqli_real_escape_string($CONNEXION, $_POST['message']);
    $response = getAnthropicResponse($user_message);


    saveMessage($conversation_id, 'user', $user_message);

    saveMessage($conversation_id, 'bot', $response);

    header("Location: index.php?conversation_id=" . $conversation_id);
    exit();
}


$form_token = preventFormResubmission();


$query = $CONNEXION->prepare("
    SELECT 
        c.id, 
        c.started_at, 
        IFNULL(c.ended_at, 'En cours') as status,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count,
        (SELECT LEFT(message_text, 30) FROM messages WHERE conversation_id = c.id ORDER BY sent_at ASC LIMIT 1) as first_message
    FROM conversations c
    WHERE c.user_id = ?
    ORDER BY c.started_at DESC
");
$query->bind_param('i', $user_id);
$query->execute();
$conversations = $query->get_result();

include 'include/header.php';
?>

<header>
    <h1>CHAT TPG*</h1>
</header>

<div class="app-container">

    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Conversations</h2>
            <a href="index.php?action=new_conversation" class="btn-new">
                <span>+</span> Nouvelle
            </a>
        </div>
        
        <div class="conversation-list">
            <?php if ($conversations->num_rows > 0): ?>
                <?php while ($row = $conversations->fetch_assoc()): ?>
                    <a href="index.php?conversation_id=<?php echo $row['id']; ?>" 
                       class="conversation-item <?php echo ($conversation_id == $row['id']) ? 'active' : ''; ?>">
                        <div class="conv-info">
                            <span class="conv-date"><?php echo date('d/m H:i', strtotime($row['started_at'])); ?></span>
                            <span class="conv-messages"><?php echo $row['message_count']; ?> messages</span>
                        </div>
                        <p class="conv-preview">
                            <?php 
                            $preview = $row['first_message'] ?? 'Nouvelle conversation';
                            echo htmlspecialchars(substr($preview, 0, 30) . (strlen($preview) > 30 ? '...' : '')); 
                            ?>
                        </p>
                        <span class="conv-status <?php echo ($row['status'] == 'En cours') ? 'status-active' : 'status-closed'; ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-conversations">
                    <p>Aucune conversation</p>
                    <p>Cliquez sur "Nouvelle" pour commencer</p>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <div class="main-content">
        <?php if ($conversation_id > 0): ?>

            <div id="chatbox">
                <?php

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
                        $sender = $row['sender'] === 'user' ? 'Vous' : 'Bot';
                        $message_class = $row['sender'] === 'user' ? 'user-message' : 'bot-message';
                        ?>
                        <div class="message-container <?php echo $message_class; ?>">
                            <div class="message-content">
                                <div class="message-header">
                                    <strong><?php echo $sender; ?></strong>
                                    <span class="message-time"><?php echo date('H:i', strtotime($row['sent_at'])); ?></span>
                                </div>
                                <p><?php echo nl2br(htmlspecialchars($row['message_text'])); ?></p>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='welcome-message'>Commencez à discuter avec le bot!</div>";
                }
                ?>
            </div>
            <form method="post" action="index.php?conversation_id=<?php echo $conversation_id; ?>" class="message-form">
                <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                <input type="text" name="message" placeholder="Tapez votre message..." required autofocus>
                <button type="submit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </form>
        <?php else: ?>

            <div class="welcome-screen">
                <div class="welcome-content">
                    <h2>Bienvenue sur CHAT TPG*</h2>
                    <p>Sélectionnez une conversation dans la barre latérale ou créez-en une nouvelle pour commencer à discuter avec le bot.</p>
                    <a href="index.php?action=new_conversation" class="btn-primary">Démarrer une nouvelle conversation</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>

    const chatbox = document.getElementById('chatbox');
    if (chatbox) {
        chatbox.scrollTop = chatbox.scrollHeight;
    }
</script>

<?php include 'include/footer.php'; ?>