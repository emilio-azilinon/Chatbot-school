<?php
// Script de test pour vérifier la logique du chatbot
require_once 'chat.php';

// Fonctions de test
function testMessage($message, $expectedContains = '') {
    echo "\n=== Test: '$message' ===\n";
    $result = processMessage($message);
    echo "Réponse: $result\n";

    if ($expectedContains && str_contains($result, $expectedContains)) {
        echo "✅ Test réussi\n";
    } elseif ($expectedContains) {
        echo "❌ Test échoué - attendu: '$expectedContains'\n";
    }
    echo str_repeat("-", 50) . "\n";
}

// Tests des différentes fonctionnalités
testMessage("Quelles formations proposez-vous ?", "filières");
testMessage("Est-ce que l'informatique existe ?", "génie logiciel"); // Maintenant détecte filiere_informatique
testMessage("Comment m'inscrire ?", "Préinscription");
testMessage("Combien coûtent les frais ?", "tarifs");
testMessage("Où se trouve l'école ?", "Cotonou");
testMessage("Quels documents faut-il ?", "BAC");
testMessage("Prix de l'informatique", "tarifs");
testMessage("Contact téléphone", "WhatsApp");
testMessage("Gestion et droit disponibles", "comptabilité"); // Maintenant détecte filiere_gestion
testMessage("Je veux savoir les frais et comment contacter", "tarifs"); // Devrait détecter frais en priorité

// Tests des améliorations
testMessage("pas informatique", ""); // Test négation - devrait réduire score
testMessage("où", ""); // Test mot générique seul - devrait être filtré
testMessage("frais", "tarifs"); // Test message court
testMessage("koi les filiere", "filières"); // Test faute - devrait détecter question_filiere
testMessage("combien pour gestion", "tarifs"); // Test combinaison
testMessage("inscri", "Préinscription"); // Test faute simple
?>