<?php
// Headers HTTP seulement si appelé directement via HTTP
if (isset($_SERVER['REQUEST_METHOD'])) {
    header('Content-Type: application/json; charset=UTF-8');
}

// Démarrer la session pour le contexte (seulement si pas déjà démarrée et pas de headers envoyés)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Fonctions de traitement (disponibles globalement pour les tests)
function processMessage($message) {
    // Normalisation du texte
    $normalized = normalizeText($message);
    
    // Détection des négations
    $hasNegation = detectNegation($normalized);
    
    // Détection des intentions avec scores
    $intentions = detectIntentions($normalized);
    
    // Filtrage et priorité des intentions
    $filteredIntentions = filterIntentions($intentions, $normalized, $hasNegation);
    
    // Génération de la réponse basée sur les intentions filtrées
    return generateResponse($filteredIntentions, $normalized, $message);
}

// Normalisation : minuscules, suppression accents et ponctuation
function normalizeText($text) {
    $text = strtolower($text);
    $text = preg_replace('/[àâäå]/u', 'a', $text);
    $text = preg_replace('/[éèêë]/u', 'e', $text);
    $text = preg_replace('/[îï]/u', 'i', $text);
    $text = preg_replace('/[ôö]/u', 'o', $text);
    $text = preg_replace('/[ûü]/u', 'u', $text);
    $text = preg_replace('/[ç]/u', 'c', $text);
    $text = preg_replace('/[?!.,;:\'-]/u', '', $text);
    $text = preg_replace('/\s+/', ' ', trim($text));
    return $text;
}

// Détection des négations
function detectNegation($text) {
    $negationWords = ['pas', 'jamais', 'non', 'aucun', 'rien', 'personne'];
    foreach ($negationWords as $neg) {
        if (str_contains($text, $neg)) {
            return true;
        }
    }
    return false;
}

// Détection des intentions avec scores (nombre de mots-clés trouvés)
function detectIntentions($text) {
    $intentions = [];
    
    // Mots-clés étendus pour chaque domaine avec synonymes et variantes
    $keywords = [
        'filiere_informatique' => ['informatique', 'info', 'dev', 'programmation', 'logiciel', 'developpement', 'code', 'web', 'mobile', 'appli'],
        'filiere_gestion' => ['gestion', 'management', 'commerce', 'business', 'entreprise', 'finance', 'compta', 'marketing', 'vente'],
        'filiere_droit' => ['droit', 'juridique', 'justice', 'loi', 'avocat', 'notaire', 'tribunal', 'legal'],
        'filiere_sciences' => ['science', 'sciences', 'biologie', 'chimie', 'physique', 'math', 'mathematiques', 'labo', 'recherche'],
        'filiere_communication' => ['communication', 'journalisme', 'marketing', 'publicite', 'media', 'presse', 'journal'],
        
        'inscription' => ['inscription', 'inscrire', 'enroller', 'admission', 'candidature', 'inscri', 'admettre', 'entrer'],
        'frais' => ['frais', 'cout', 'prix', 'tarif', 'payer', 'combien', 'montant', 'coût', 'euros', 'cfa'],
        'contact' => ['contact', 'telephone', 'tel', 'whatsapp', 'mail', 'email', 'numero', 'num', 'appel', 'sms'],
        'localisation' => ['ou', 'adresse', 'lieu', 'localisation', 'ville', 'ecole', 'campus', 'emplacement', 'position'],
        'documents' => ['document', 'piece', 'dossier', 'bac', 'acte', 'certificat', 'papier', 'justificatif', 'preuve'],
        
        'question_filiere' => ['quelle', 'quelles', 'quel', 'disponible', 'existe', 'propose', 'offre', 'avez', 'faites', 'enseign', 'koi', 'filiere', 'filieres', 'formations'],
        'question_inscription' => ['comment', 'proces', 'etape', 'modalite', 'faire', 'procedure', 'marche'],
        'question_frais' => ['combien', 'prix', 'tarif', 'coute', 'payer', 'coûte', 'reviens'],
        'question_contact' => ['comment contacter', 'numero', 'telephone', 'joindre', 'appeler'],
        'question_localisation' => ['ou se trouve', 'adresse', 'lieu', 'situe', 'localise']
    ];
    
    // Calcul des scores pour chaque intention
    foreach ($keywords as $intention => $mots) {
        $score = 0;
        foreach ($mots as $mot) {
            if (str_contains($text, $mot)) {
                $score++;
            }
        }
        if ($score > 0) {
            $intentions[$intention] = $score;
        }
    }
    
    return $intentions;
}

// Filtrage et priorité des intentions
function filterIntentions($intentions, $text, $hasNegation) {
    // Si négation détectée, réduire les scores des intentions positives
    if ($hasNegation) {
        foreach ($intentions as $key => $score) {
            $intentions[$key] = max(0, $score - 2); // Pénalité pour négation
        }
    }
    
    // Priorités : certaines intentions sont plus importantes
    $priorities = [
        'question_filiere' => 10,
        'question_inscription' => 15,
        'question_frais' => 8,
        'question_contact' => 7,
        'question_localisation' => 6,
        'filiere_informatique' => 5,
        'filiere_gestion' => 5,
        'filiere_droit' => 5,
        'filiere_sciences' => 5,
        'filiere_communication' => 5,
        'inscription' => 4,
        'frais' => 4,
        'contact' => 4,
        'localisation' => 3,
        'documents' => 3
    ];
    
    // Appliquer les priorités
    foreach ($intentions as $key => $score) {
        $intentions[$key] = $score + ($priorities[$key] ?? 0);
    }
    
    // Trier par score décroissant et prendre les 2 meilleures
    arsort($intentions);
    return array_slice($intentions, 0, 2, true);
}

// Génération de la réponse basée sur les intentions détectées
function generateResponse($intentions, $originalText) {
    $responses = [];
    
    // Contexte : utiliser la dernière intention si message court
    $context = $_SESSION['last_intention'] ?? null;
    $isShortMessage = strlen($originalText) < 20 && !preg_match('/\s/', $originalText);
    
    if ($isShortMessage && $context && empty($intentions)) {
        // Utiliser le contexte pour les messages courts
        $intentions = [$context => 1];
    }
    
    // Mémoriser l'intention principale pour le contexte futur
    if (!empty($intentions)) {
        $_SESSION['last_intention'] = array_key_first($intentions);
    }
    
    // Logique de génération basée sur les intentions prioritaires (elseif pour éviter multiples réponses)
    
    // Combinaisons spéciales : frais + filière = réponse frais
    if ((isset($intentions['question_frais']) || isset($intentions['frais'])) && 
        (isset($intentions['filiere_informatique']) || isset($intentions['filiere_gestion']) || 
         isset($intentions['filiere_droit']) || isset($intentions['filiere_sciences']) || 
         isset($intentions['filiere_communication']))) {
        $responses[] = 'Les frais varient par filière. Contactez-nous pour les tarifs exacts : +229 XXXX XXXX';
    }
    
    // Questions sur les filières (priorité haute)
    elseif (isset($intentions['question_filiere'])) {
        if (isset($intentions['filiere_informatique'])) {
            $responses[] = 'OUI ✅ Informatique est disponible ! Génie logiciel, réseaux, systèmes, base de données.';
        } elseif (isset($intentions['filiere_gestion'])) {
            $responses[] = 'OUI ✅ Gestion est disponible ! Comptabilité, marketing, management, finance.';
        } elseif (isset($intentions['filiere_droit'])) {
            $responses[] = 'OUI ✅ Droit est disponible ! Droit civil, pénal, commercial, administrative.';
        } elseif (isset($intentions['filiere_sciences'])) {
            $responses[] = 'OUI ✅ Sciences est disponible ! Biologie, chimie, physique, mathématiques.';
        } elseif (isset($intentions['filiere_communication'])) {
            $responses[] = 'OUI ✅ Communication est disponible ! Journalisme, marketing, publicité.';
        } else {
            $responses[] = 'Nos filières : Informatique, Gestion, Droit, Sciences, Communication. Laquelle vous intéresse ?';
        }
    }
    
    // Si pas de question filière mais filière spécifique mentionnée
    elseif (isset($intentions['filiere_informatique'])) {
        // Si utilisateur choisit licence ou master après une question filière
        if (preg_match('/(licence|master|bac)/i', $originalText)) {
            $niveau = preg_match('/master/i', $originalText) ? 'Master' : 'Licence';
            $responses[] = "✅ Parfait ! Informatique en $niveau 🎓\n📝 Inscription en ligne\n💰 Frais : +229 XXXX XXXX\n📞 Tél : +229 XXXX XXXX";
        } else {
            $responses[] = '💻 Informatique : génie logiciel, réseaux, systèmes. Licence ou Master ?';
        }
    }
    elseif (isset($intentions['filiere_gestion'])) {
        // Si utilisateur choisit licence ou master après une question filière
        if (preg_match('/(licence|master|bac)/i', $originalText)) {
            $niveau = preg_match('/master/i', $originalText) ? 'Master' : 'Licence';
            $responses[] = "✅ Parfait ! Gestion en $niveau 🎓\n📝 Inscription en ligne\n💰 Frais : +229 XXXX XXXX\n📞 Tél : +229 XXXX XXXX";
        } else {
            $responses[] = '📊 Gestion : comptabilité, marketing, management. Licence ou Master ?';
        }
    }
    elseif (isset($intentions['filiere_droit'])) {
        // Si utilisateur choisit licence ou master après une question filière
        if (preg_match('/(licence|master|bac)/i', $originalText)) {
            $niveau = preg_match('/master/i', $originalText) ? 'Master' : 'Licence';
            $responses[] = "✅ Parfait ! Droit en $niveau 🎓\n📝 Inscription en ligne\n💰 Frais : +229 XXXX XXXX\n📞 Tél : +229 XXXX XXXX";
        } else {
            $responses[] = '⚖️ Droit : droit civil, pénal, commercial. Licence ou Master ?';
        }
    }
    elseif (isset($intentions['filiere_sciences'])) {
        // Si utilisateur choisit licence ou master après une question filière
        if (preg_match('/(licence|master|bac)/i', $originalText)) {
            $niveau = preg_match('/master/i', $originalText) ? 'Master' : 'Licence';
            $responses[] = "✅ Parfait ! Sciences en $niveau 🎓\n📝 Inscription en ligne\n💰 Frais : +229 XXXX XXXX\n📞 Tél : +229 XXXX XXXX";
        } else {
            $responses[] = '🔬 Sciences : biologie, chimie, physique. Licence ou Master ?';
        }
    }
    elseif (isset($intentions['filiere_communication'])) {
        // Si utilisateur choisit licence ou master après une question filière
        if (preg_match('/(licence|master|bac)/i', $originalText)) {
            $niveau = preg_match('/master/i', $originalText) ? 'Master' : 'Licence';
            $responses[] = "✅ Parfait ! Communication en $niveau 🎓\n📝 Inscription en ligne\n💰 Frais : +229 XXXX XXXX\n📞 Tél : +229 XXXX XXXX";
        } else {
            $responses[] = '📢 Communication : journalisme, marketing, publicité. Licence ou Master ?';
        }
    }
    
    // Questions sur l'inscription (priorité haute - condition spéciale)
    elseif ((isset($intentions['question_inscription']) || isset($intentions['inscription'])) && 
            !isset($intentions['filiere_communication'])) { // Éviter conflit bizarre
        $responses[] = "Pour s'inscrire :\n1️⃣ Préinscription en ligne\n2️⃣ Dépôt du dossier\n3️⃣ Paiement des frais\n\nQuelle filière ?";
    }
    
    // Questions sur les frais (priorité moyenne)
    elseif (isset($intentions['question_frais']) || isset($intentions['frais'])) {
        $responses[] = 'Les frais varient par filière. Contactez-nous pour les tarifs exacts : +229 XXXX XXXX';
    }
    
    // Questions sur le contact (priorité moyenne)
    elseif (isset($intentions['question_contact']) || isset($intentions['contact'])) {
        $responses[] = "📞 Contact : Lundi-Vendredi 8h-17h\nTél : +229 XXXX XXXX\nWhatsApp : +229 XXXX XXXX";
    }
    
    // Questions sur la localisation (priorité moyenne)
    elseif (isset($intentions['question_localisation']) || isset($intentions['localisation'])) {
        $responses[] = "📍 Nous sommes à Cotonou / Abomey-Calavi (Bénin). Adresse précise sur demande.";
    }
    
    // Documents requis (priorité moyenne)
    elseif (isset($intentions['documents'])) {
        $responses[] = "📋 Documents pour l'inscription :\n✓ BAC ou équivalent\n✓ Pièce d'identité\n✓ Acte de naissance\n✓ Photos 3x4\n✓ Certificat médical";
    }
    
    // Si aucune intention détectée, réponse par défaut
    if (empty($responses)) {
        $responses[] = "👋 Je peux vous aider sur :\n📚 Filières disponibles\n📝 Processus d'inscription\n💰 Frais et tarifs\n📞 Contact et localisation\n📋 Documents requis\n\nQue souhaitez-vous savoir ?";
    }
    
    // Limitation à 1-2 réponses maximum
    $responses = array_slice($responses, 0, 2);
    
    // Combiner les réponses (éviter les doublons)
    return implode("\n\n", array_unique($responses));
}

// Traitement principal seulement si appelé directement via HTTP
if (isset($_SERVER['REQUEST_METHOD'])) {
    $input = json_decode(file_get_contents('php://input'), true);
    $message = isset($input['message']) ? trim($input['message']) : '';

    if (!$message) {
        echo json_encode(['reply' => 'Salut 👋 Dis-moi ce que tu veux savoir : inscription, filières, contact...']);
        exit;
    }

    $reply = processMessage($message);
    echo json_encode(['reply' => $reply]);
}
?>
