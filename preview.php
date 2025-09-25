<?php
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$categoryid = required_param('categoryid', PARAM_INT);
$system_type = optional_param('system_type', 'collaborate', PARAM_TEXT);
$prefix = optional_param('prefix', 'Salle de Travaux Dirigés', PARAM_TEXT);
$suffix = optional_param('suffix', 'SOCIO - P10L3S5', PARAM_TEXT);
$show_category_id = optional_param('show_category_id', 1, PARAM_INT);
$show_category_name = optional_param('show_category_name', 0, PARAM_INT);

$PAGE->set_url('/local/batchpreview/preview.php', array('categoryid' => $categoryid));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('preview_title', 'local_batchpreview', $categoryid));
$PAGE->set_heading(get_string('preview_title', 'local_batchpreview', $categoryid));
$PAGE->set_pagelayout('admin');

$PAGE->requires->css('/local/batchpreview/styles/styles.css');
$PAGE->requires->js('/local/batchpreview/js/preview_direct.js');

echo $OUTPUT->header();

// Fonction pour obtenir les données de prévisualisation
function get_preview_data($categoryid, $system_type, $prefix, $suffix, $show_category_id, $show_category_name) {
    global $DB;
    
    // Déterminer la table et les colonnes selon le système
    $table_info = get_system_table_info($system_type);
    
    $sql = "SELECT 
                c.id as room_id,
                c.name as current_name,
                course.id as course_id,
                course.fullname as course_name,
                cat.id as category_id,
                cat.name as category_name,
                cat.path as category_path
            FROM {{$table_info['table']}} c
            JOIN {course} course ON course.id = c.course
            JOIN {course_categories} cat ON course.category = cat.id
            WHERE (cat.path LIKE ? OR cat.path LIKE ? OR cat.id = ?)
            ORDER BY cat.id, course.fullname";
    
    $path1 = '%/' . $categoryid . '/%';
    $path2 = '%/' . $categoryid;
    
    $records = $DB->get_records_sql($sql, array($path1, $path2, $categoryid));
    
    $results = array();
    foreach ($records as $record) {
        $new_name_parts = array($prefix, $record->course_name, $suffix);
        
        if ($show_category_id) {
            $new_name_parts[] = '(Cat: ' . $record->category_id . ')';
        }
        
        if ($show_category_name) {
            $new_name_parts[] = '[' . $record->category_name . ']';
        }
        
        $new_name = implode(' - ', array_filter($new_name_parts));
        
        $results[] = array(
            'room_id' => $record->room_id,
            'current_name' => $record->current_name,
            'new_name' => $new_name,
            'course_name' => $record->course_name,
            'category_name' => $record->category_name,
            'category_id' => $record->category_id,
            'system_type' => $system_type
        );
    }
    
    return $results;
}

// Fonction pour obtenir les informations de table selon le système
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

$preview_data = get_preview_data($categoryid, $system_type, $prefix, $suffix, $show_category_id, $show_category_name);

echo html_writer::start_div('preview-container');

// Boutons d'action en haut
echo html_writer::start_div('action-buttons');
echo html_writer::link(
    new moodle_url('/local/batchpreview/index.php'),
    get_string('reset', 'local_batchpreview'),
    array('class' => 'btn btn-secondary')
);
echo html_writer::end_div();

if (empty($preview_data)) {
    echo html_writer::tag('div', 
        get_string('no_results', 'local_batchpreview'), 
        array('class' => 'alert alert-info')
    );
} else {
    // Statistiques
    echo html_writer::start_div('stats-section');
    echo html_writer::tag('h3', 'Résumé des modifications');
    echo html_writer::tag('div', 
        count($preview_data) . ' salles Collaborate seront modifiées',
        array('class' => 'stat-item')
    );
    echo html_writer::end_div();
    
    // Tableau de prévisualisation
    echo html_writer::start_div('preview-table-container');
    echo html_writer::tag('h3', get_string('affected_collaborate', 'local_batchpreview'));
    
    $table_info = get_system_table_info($system_type);
    
    $table = new html_table();
    $table->head = array(
        'ID ' . $table_info['display_name'],
        get_string('current_name', 'local_batchpreview'),
        get_string('new_name', 'local_batchpreview'),
        get_string('course_name', 'local_batchpreview'),
        get_string('category_name', 'local_batchpreview'),
        'Système'
    );
    $table->attributes['class'] = 'generaltable preview-table';
    $table->attributes['id'] = 'preview-table';
    
    foreach ($preview_data as $row) {
        $current_name_input = html_writer::tag('input', '', array(
            'type' => 'text',
            'value' => $row['current_name'],
            'class' => 'current-name-input',
            'data-room-id' => $row['room_id'],
            'readonly' => 'readonly'
        ));
        
        $new_name_input = html_writer::tag('input', '', array(
            'type' => 'text',
            'value' => $row['new_name'],
            'class' => 'new-name-input',
            'data-room-id' => $row['room_id']
        ));
        
        $table->data[] = array(
            $row['room_id'],
            $current_name_input,
            $new_name_input,
            $row['course_name'],
            $row['category_name'] . ' (ID: ' . $row['category_id'] . ')',
            $table_info['display_name']
        );
    }
    
    echo html_writer::table($table);
    echo html_writer::end_div();
    
    // Génération du SQL
    echo html_writer::start_div('sql-section');
    echo html_writer::tag('h3', get_string('sql_code', 'local_batchpreview'));
    
    $sql_code = generate_sql_code($categoryid, $system_type, $prefix, $suffix, $show_category_id, $show_category_name);
    
    echo html_writer::tag('div', 
        get_string('execute_warning', 'local_batchpreview'),
        array('class' => 'alert alert-warning')
    );
    
    echo html_writer::start_div('sql-container');
    echo html_writer::tag('textarea', $sql_code, array(
        'id' => 'sql-code',
        'readonly' => 'readonly',
        'rows' => 10,
        'class' => 'sql-textarea'
    ));
    echo html_writer::end_div();
    
    // Boutons pour le SQL
    echo html_writer::start_div('sql-buttons');
    echo html_writer::tag('button', 
        get_string('copy_sql', 'local_batchpreview'),
        array('id' => 'copy-sql-btn', 'class' => 'btn btn-secondary')
    );
    echo html_writer::tag('button', 
        get_string('download_sql', 'local_batchpreview'),
        array('id' => 'download-sql-btn', 'class' => 'btn btn-secondary')
    );
    // NOUVEAU: Instruction pour l'utilisateur
    echo html_writer::tag('div',
        '<i class="fa fa-info-circle"></i> ' . get_string('editable_names', 'local_batchpreview'),
        array('class' => 'alert alert-info', 'style' => 'margin: 15px 0; font-size: 0.9em;')
    );
    echo html_writer::tag('button', 
    '<i class="fa fa-check"></i> ' . get_string('apply_changes', 'local_batchpreview'),
    array(
        'id' => 'apply-changes-btn', 
        'class' => 'btn btn-primary btn-lg',
        'data-categoryid' => $categoryid,
        'data-system-type' => $system_type,
        'style' => 'margin-top: 10px;'
    )
);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

// Fonction pour générer le code SQL (format MySQL)
function generate_sql_code($categoryid, $system_type, $prefix, $suffix, $show_category_id, $show_category_name) {
    $table_info = get_system_table_info($system_type);
    $table_name = 'mdl_' . $table_info['table'];
    
    $name_parts = array("'$prefix'", "mdl_course.fullname");
    
    if (!empty($suffix)) {
        $name_parts[] = "'$suffix'";
    }
    
    if ($show_category_id) {
        $name_parts[] = "'(Cat: '";
        $name_parts[] = "mdl_course_categories.id";
        $name_parts[] = "')'";
    }
    
    if ($show_category_name) {
        $name_parts[] = "'['";
        $name_parts[] = "mdl_course_categories.name";
        $name_parts[] = "']'";
    }
    
    $concat_parts = "CONCAT(" . implode(", ' - ', ", $name_parts) . ")";
    
    $sql = "-- ================================================================\n";
    $sql .= "-- Code SQL généré automatiquement par Batch Preview Module\n";
    $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Système: " . $table_info['display_name'] . "\n";
    $sql .= "-- Catégorie ciblée: $categoryid\n";
    $sql .= "-- ================================================================\n\n";
    $sql .= "-- IMPORTANT: Sauvegardez votre base de données avant d'exécuter!\n\n";
    
    // Requête de vérification MySQL
    $sql .= "-- 1. Requête de vérification (à exécuter en premier)\n";
    $sql .= "SELECT \n";
    $sql .= "    {$table_name}.id as room_id,\n";
    $sql .= "    {$table_name}.name as current_name,\n";
    $sql .= "    {$concat_parts} as new_name,\n";
    $sql .= "    mdl_course.fullname as course_name,\n";
    $sql .= "    mdl_course_categories.name as category_name\n";
    $sql .= "FROM {$table_name}\n";
    $sql .= "    INNER JOIN mdl_course ON mdl_course.id = {$table_name}.course\n";
    $sql .= "    INNER JOIN mdl_course_categories ON mdl_course.category = mdl_course_categories.id\n";
    $sql .= "WHERE (\n";
    $sql .= "    mdl_course_categories.path LIKE '%/$categoryid/%' OR\n";
    $sql .= "    mdl_course_categories.path LIKE '%/$categoryid' OR\n";
    $sql .= "    mdl_course_categories.id = $categoryid\n";
    $sql .= ")\n";
    $sql .= "ORDER BY mdl_course_categories.id, mdl_course.fullname;\n\n";
    
    // Requête de mise à jour MySQL
    $sql .= "-- 2. Requête de mise à jour (à exécuter après vérification)\n";
    $sql .= "UPDATE {$table_name}\n";
    $sql .= "    INNER JOIN mdl_course ON mdl_course.id = {$table_name}.course\n";
    $sql .= "    INNER JOIN mdl_course_categories ON mdl_course.category = mdl_course_categories.id\n";
    $sql .= "SET {$table_name}.name = {$concat_parts}\n";
    $sql .= "WHERE (\n";
    $sql .= "    mdl_course_categories.path LIKE '%/$categoryid/%' OR\n";
    $sql .= "    mdl_course_categories.path LIKE '%/$categoryid' OR\n";
    $sql .= "    mdl_course_categories.id = $categoryid\n";
    $sql .= ");\n\n";
    
    $sql .= "-- ================================================================";
    
    return $sql;
}

echo $OUTPUT->footer();
?>