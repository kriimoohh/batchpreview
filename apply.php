<?php
// Fichier: apply.php - Version corrigée complète
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Debug: Activer l'affichage des erreurs en développement
if ($CFG->debug >= DEBUG_DEVELOPER) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Log de debug initial
error_log('apply.php: Début du script - Method: ' . $_SERVER['REQUEST_METHOD']);

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/batchpreview/apply.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Application des modifications');
$PAGE->set_heading('Application des modifications');
$PAGE->set_pagelayout('admin');

// Headers JSON obligatoires
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Fonction pour envoyer une réponse JSON et arrêter
function send_json_response($data, $http_code = 200) {
    if ($http_code !== 200) {
        http_response_code($http_code);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    error_log('apply.php: Réponse envoyée - ' . json_encode($data));
    exit;
}

// CORRECTION: Fonction get_system_table_info déplacée ici pour éviter les dépendances
function get_system_table_info($system_type) {
    $systems = array(
        'collaborate' => array(
            'table' => 'collaborate',
            'name_column' => 'name',
            'display_name' => 'Collaborate'
        ),
        'bigbluebuttonbn' => array(
            'table' => 'bigbluebuttonbn',
            'name_column' => 'name',
            'display_name' => 'BigBlueButton'
        )
    );
    
    return isset($systems[$system_type]) ? $systems[$system_type] : $systems['collaborate'];
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response([
        'success' => false, 
        'error' => 'Méthode non supportée. Utilisez POST.'
    ], 405);
}

try {
    // Récupérer les données POST
    $raw_input = file_get_contents('php://input');
    error_log('apply.php: Raw input reçu - ' . substr($raw_input, 0, 200) . '...');
    
    if (empty($raw_input)) {
        send_json_response([
            'success' => false, 
            'error' => 'Aucune donnée reçue. Vérifiez que la requête contient des données JSON.'
        ], 400);
    }
    
    $input = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json_response([
            'success' => false, 
            'error' => 'Données JSON invalides: ' . json_last_error_msg()
        ], 400);
    }
    
    // Validation des paramètres avec valeurs par défaut
    $categoryid = isset($input['categoryid']) ? intval($input['categoryid']) : 0;
    $system_type = isset($input['system_type']) ? trim($input['system_type']) : '';
    $changes = isset($input['changes']) ? $input['changes'] : array();
    
    error_log('apply.php: Paramètres validés - categoryid: ' . $categoryid . ', system_type: ' . $system_type . ', changes: ' . count($changes));
    
    // Validation des paramètres
    if ($categoryid <= 0) {
        send_json_response([
            'success' => false, 
            'error' => 'ID de catégorie invalide: ' . $categoryid
        ], 400);
    }
    
    if (empty($system_type)) {
        send_json_response([
            'success' => false, 
            'error' => 'Type de système manquant'
        ], 400);
    }
    
    if (empty($changes) || !is_array($changes)) {
        send_json_response([
            'success' => false, 
            'error' => 'Aucune modification à appliquer ou format invalide'
        ], 400);
    }
    
    // Vérifier que la catégorie existe
    if (!$DB->record_exists('course_categories', array('id' => $categoryid))) {
        send_json_response([
            'success' => false, 
            'error' => 'Catégorie inexistante: ' . $categoryid
        ], 400);
    }
    
    // Obtenir les informations de la table
    $table_info = get_system_table_info($system_type);
    if (!$table_info) {
        send_json_response([
            'success' => false, 
            'error' => 'Type de système invalide: ' . $system_type
        ], 400);
    }
    
    $table_name = $table_info['table'];
    
    // Vérifier que la table existe
    if (!$DB->get_manager()->table_exists($table_name)) {
        send_json_response([
            'success' => false, 
            'error' => 'Table inexistante: ' . $table_name
        ], 400);
    }
    
    error_log('apply.php: Validation OK, début transaction');
    
    // Démarrer la transaction
    $transaction = $DB->start_delegated_transaction();
    
    $updated_count = 0;
    $errors = array();
    $processed_ids = array();
    
    foreach ($changes as $index => $change) {
        try {
            // Validation des données de changement
            if (!isset($change['room_id']) || !isset($change['new_name'])) {
                $errors[] = "Modification #$index: données manquantes (room_id ou new_name)";
                continue;
            }
            
            $room_id = intval($change['room_id']);
            $new_name = trim($change['new_name']);
            
            if ($room_id <= 0) {
                $errors[] = "Modification #$index: ID invalide ($room_id)";
                continue;
            }
            
            if (empty($new_name)) {
                $errors[] = "Modification #$index: nom vide pour l'enregistrement $room_id";
                continue;
            }
            
            // Éviter les doublons
            if (in_array($room_id, $processed_ids)) {
                $errors[] = "Modification #$index: doublon détecté pour l'ID $room_id";
                continue;
            }
            $processed_ids[] = $room_id;
            
            // Vérifier que l'enregistrement existe et appartient à la bonne catégorie
            $sql = "SELECT c.id, c.name, course.category, cat.path, cat.name as category_name
                    FROM {{$table_name}} c
                    JOIN {course} course ON course.id = c.course
                    JOIN {course_categories} cat ON course.category = cat.id
                    WHERE c.id = ? AND (
                        cat.path LIKE ? OR 
                        cat.path LIKE ? OR 
                        cat.id = ?
                    )";
            
            $path1 = '%/' . $categoryid . '/%';
            $path2 = '%/' . $categoryid;
            
            $record = $DB->get_record_sql($sql, array($room_id, $path1, $path2, $categoryid));
            
            if (!$record) {
                $errors[] = "Enregistrement $room_id introuvable ou hors catégorie $categoryid";
                continue;
            }
            
            // Vérifier si le changement est nécessaire
            if ($record->name === $new_name) {
                error_log("apply.php: Pas de changement nécessaire pour l'ID $room_id");
                continue;
            }
            
            // Mettre à jour le nom
            $update_result = $DB->set_field($table_name, 'name', $new_name, array('id' => $room_id));
            
            if ($update_result) {
                $updated_count++;
                error_log("apply.php: Mise à jour réussie pour l'ID $room_id: '{$record->name}' -> '$new_name'");
            } else {
                $errors[] = "Échec de mise à jour pour l'enregistrement $room_id";
            }
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors du traitement de l'ID $room_id: " . $e->getMessage();
            error_log('apply.php: Erreur sur ID ' . $room_id . ' - ' . $e->getMessage());
        }
    }
    
    // Décider si on valide ou annule la transaction
    if (empty($errors) && $updated_count > 0) {
        $transaction->allow_commit();
        
        // Log de l'activité (simplifié)
        error_log('apply.php: Transaction validée - ' . $updated_count . ' modifications appliquées');
        
        send_json_response([
            'success' => true, 
            'updated_count' => $updated_count,
            'message' => "$updated_count modification(s) appliquée(s) avec succès",
            'processed_count' => count($processed_ids)
        ]);
        
    } else {
        $transaction->rollback();
        
        if (empty($errors) && $updated_count === 0) {
            send_json_response([
                'success' => false, 
                'error' => 'Aucune modification n\'était nécessaire (tous les noms sont déjà à jour)',
                'updated_count' => 0
            ]);
        } else {
            send_json_response([
                'success' => false, 
                'errors' => $errors,
                'updated_count' => $updated_count,
                'error' => 'Certaines modifications ont échoué. Transaction annulée.'
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log('apply.php: Exception globale - ' . $e->getMessage());
    error_log('apply.php: Stack trace - ' . $e->getTraceAsString());
    
    if (isset($transaction)) {
        try {
            $transaction->rollback();
        } catch (Exception $rollback_exception) {
            error_log('apply.php: Erreur lors du rollback - ' . $rollback_exception->getMessage());
        }
    }
    
    send_json_response([
        'success' => false, 
        'error' => 'Erreur serveur: ' . $e->getMessage(),
        'debug_info' => ($CFG->debug >= DEBUG_DEVELOPER) ? $e->getTraceAsString() : null
    ], 500);
}
?>