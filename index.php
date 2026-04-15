<?php
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Chatbot école</title>
    <link rel="stylesheet" href="public/style.css" />
  </head>
  <body>
    <div class="app-shell">
      <header>
        <h1>Bot Scolarité</h1>
        <p>Pose ta question sur l’inscription, les filières ou le contact.</p>
      </header>

      <main id="chat-window">
        <div class="message bot">Salut 👋 Je suis le chatbot de l’école. Que veux-tu savoir ?</div>
        <div id="typing-indicator" class="message bot typing" style="display: none;">
          <div class="typing-dots">
            <span></span><span></span><span></span>
          </div>
        </div>
      </main>

      <div class="quick-actions">
        <button data-msg="Inscription">Inscription</button>
        <button data-msg="Filières">Filières</button>
        <button data-msg="Contact">Contact</button>
      </div>

      <form id="chat-form">
        <input id="chat-input" type="text" placeholder="Écris ta question..." autocomplete="off" />
        <button type="submit" id="send-button">Envoyer</button>
      </form>
    </div>

    <script>
      const chatWindow = document.getElementById('chat-window');
      const chatForm = document.getElementById('chat-form');
      const chatInput = document.getElementById('chat-input');
      const quickButtons = document.querySelectorAll('.quick-actions button');
      const typingIndicator = document.getElementById('typing-indicator');
      const sendButton = document.getElementById('send-button');

      function updateSendButton() {
        sendButton.disabled = !chatInput.value.trim();
      }

      chatInput.addEventListener('input', updateSendButton);
      updateSendButton(); // Initial check

      function addMessage(text, role) {
        const message = document.createElement('div');
        message.className = `message ${role}`;
        message.textContent = text;
        chatWindow.appendChild(message);
        chatWindow.scrollTop = chatWindow.scrollHeight;
      }

      async function sendMessage(text) {
        addMessage(text, 'user');
        chatInput.value = '';
        chatInput.focus();

        // Afficher l'indicateur de frappe
        typingIndicator.style.display = 'flex';

        try {
          const response = await fetch('chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
          });
          const data = await response.json();

          // Masquer l'indicateur et ajouter la réponse
          typingIndicator.style.display = 'none';
          addMessage(data.reply, 'bot');
        } catch (error) {
          typingIndicator.style.display = 'none';
          addMessage('Désolé, le service est indisponible pour le moment. Réessaie dans un instant.', 'bot');
        }
      }

      chatForm.addEventListener('submit', event => {
        event.preventDefault();
        const text = chatInput.value.trim();
        if (text) {
          sendMessage(text);
        }
      });

      quickButtons.forEach(button => {
        button.addEventListener('click', () => sendMessage(button.dataset.msg));
      });
    </script>
  </body>
</html>

