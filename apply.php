<?php
// Fichier: apply.php - Version corrigée complète
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

// CSRF: Accepter le sesskey depuis le header HTTP (requêtes AJAX JSON)
if (!empty($_SERVER['HTTP_X_SESSKEY'])) {
    $_REQUEST['sesskey'] = $_SERVER['HTTP_X_SESSKEY'];
}
require_sesskey();

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
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
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
    
    return isset($systems[$system_type]) ? $systems[$system_type] : null;
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
    $system_type = isset($input['system_type']) ? clean_param(trim($input['system_type']), PARAM_ALPHANUMEXT) : '';
    $changes = isset($input['changes']) ? $input['changes'] : array();

    // Validation des paramètres
    if ($categoryid <= 0) {
        send_json_response([
            'success' => false,
            'error' => 'ID de catégorie invalide'
        ], 400);
    }

    // Validation whitelist du system_type.
    $allowed_systems = array('collaborate', 'bigbluebuttonbn');
    if (!in_array($system_type, $allowed_systems)) {
        send_json_response([
            'success' => false,
            'error' => 'Type de système invalide'
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
            'error' => 'Type de système invalide'
        ], 400);
    }

    $table_name = $table_info['table'];

    // Vérifier que la table existe
    if (!$DB->get_manager()->table_exists($table_name)) {
        send_json_response([
            'success' => false,
            'error' => 'Table du système non disponible'
        ], 400);
    }
    
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
            $new_name = clean_param(trim($change['new_name']), PARAM_TEXT);

            if ($room_id <= 0) {
                $errors[] = "Modification #$index: ID invalide";
                continue;
            }

            if (empty($new_name)) {
                $errors[] = "Modification #$index: nom vide pour l'enregistrement $room_id";
                continue;
            }

            if (core_text::strlen($new_name) > 255) {
                $errors[] = "Modification #$index: nom trop long pour l'enregistrement $room_id (max 255 caractères)";
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
                continue;
            }

            // Mettre à jour le nom
            $update_result = $DB->set_field($table_name, 'name', $new_name, array('id' => $room_id));

            if ($update_result) {
                $updated_count++;
            } else {
                $errors[] = "Échec de mise à jour pour l'enregistrement $room_id";
            }
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors du traitement de l'ID $room_id";
            error_log('apply.php: Erreur sur ID ' . $room_id . ' - ' . $e->getMessage());
        }
    }
    
    // Décider si on valide ou annule la transaction
    if (empty($errors) && $updated_count > 0) {
        $transaction->allow_commit();
        
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
    error_log('apply.php: Exception - ' . $e->getMessage());

    if (isset($transaction)) {
        try {
            $transaction->rollback();
        } catch (Exception $rollback_exception) {
            error_log('apply.php: Erreur rollback - ' . $rollback_exception->getMessage());
        }
    }

    send_json_response([
        'success' => false,
        'error' => 'Une erreur serveur est survenue. Consultez les logs pour plus de détails.'
    ], 500);
}
?>