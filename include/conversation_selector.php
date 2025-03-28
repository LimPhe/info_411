<?php


 
function selectConversation($user_id, $connexion) {
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conversation_action'])) {
        
        
        if ($_POST['conversation_action'] === 'new') {
            
            $query = $connexion->prepare("
                INSERT INTO conversations (user_id) 
                VALUES (?)
            ");
            $query->bind_param('i', $user_id);
            $query->execute();
            $conversation_id = $connexion->insert_id;
            
            
            header("Location: index.php?conversation_id=" . $conversation_id);
            exit();
        } 
        
        elseif ($_POST['conversation_action'] === 'load' && isset($_POST['conversation_id'])) {
            $conv_id = intval($_POST['conversation_id']);
            
            
            $query = $connexion->prepare("
                SELECT id FROM conversations 
                WHERE id = ? AND user_id = ?
            ");
            $query->bind_param('ii', $conv_id, $user_id);
            $query->execute();
            $result = $query->get_result();
            
            if ($result->num_rows > 0) {
                header("Location: index.php?conversation_id=" . $conv_id);
                exit();
            }
        }
    }
    

    return 0;
}


 

function displayConversationSelector($user_id, $connexion) {

    $query = $connexion->prepare("
        SELECT c.id, 
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
    $result = $query->get_result();
    
    ?>
    <div class="conversation-selector">
        <h2>Sélectionner une conversation</h2>
        <form method="post" action="index.php">
            <div class="selector-options">
                <div class="option">
                    <input type="radio" id="new_conversation" name="conversation_action" value="new" checked>
                    <label for="new_conversation">Créer une nouvelle conversation</label>
                </div>
                
                <div class="option">
                    <input type="radio" id="load_conversation" name="conversation_action" value="load">
                    <label for="load_conversation">Charger une conversation existante</label>
                </div>
            </div>
            
            <?php if ($result->num_rows > 0): ?>
                <div class="conversation-list" id="conversation_list" style="display: none;">
                    <table>
                        <thead>
                            <tr>
                                <th>Sélectionner</th>
                                <th>ID</th>
                                <th>Date de début</th>
                                <th>Statut</th>
                                <th>Messages</th>
                                <th>Premier message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <input type="radio" name="conversation_id" value="<?php echo $row['id']; ?>">
                                    </td>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($row['started_at'])); ?></td>
                                    <td><?php echo $row['status']; ?></td>
                                    <td><?php echo $row['message_count']; ?></td>
                                    <td><?php echo htmlspecialchars(substr($row['first_message'] ?? '', 0, 30) . '...'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-conversations">Aucune conversation existante.</p>
            <?php endif; ?>
            
            <button type="submit" class="btn-primary">Continuer</button>
        </form>
    </div>
    
    <script>
        
        document.addEventListener('DOMContentLoaded', function() {
            const newConvRadio = document.getElementById('new_conversation');
            const loadConvRadio = document.getElementById('load_conversation');
            const conversationList = document.getElementById('conversation_list');
            
            function toggleConversationList() {
                if (loadConvRadio.checked) {
                    conversationList.style.display = 'block';
                } else {
                    conversationList.style.display = 'none';
                }
            }
            
            newConvRadio.addEventListener('change', toggleConversationList);
            loadConvRadio.addEventListener('change', toggleConversationList);

            toggleConversationList();
        });
    </script>
    
    <style>
        .conversation-selector {
            max-width: 800px;
            margin: 2rem auto;
            padding: 1.5rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        .conversation-selector h2 {
            color: #2a5298;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .selector-options {
            margin-bottom: 1.5rem;
        }
        
        .option {
            margin-bottom: 0.8rem;
        }
        
        .conversation-list {
            margin: 1.5rem 0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .conversation-list table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .conversation-list th, .conversation-list td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .conversation-list th {
            background-color: #f5f5f5;
        }
        
        .no-conversations {
            color: #666;
            font-style: italic;
        }
        
        .btn-primary {
            display: block;
            width: 100%;
            padding: 1rem;
            background: #2a5298;
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #1e3c72;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
    <?php
}
?>