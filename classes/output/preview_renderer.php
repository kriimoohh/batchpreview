<?php
namespace local_batchpreview\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use templatable;
use renderable;

// Inclure la fonction depuis preview.php pour éviter les doublons
require_once(__DIR__ . '/../../preview.php');

/**
 * Renderer pour le module batch preview
 */
class preview_renderer extends plugin_renderer_base {

    /**
     * Render le tableau de prévisualisation
     */
    public function render_preview_table(preview_table $table) {
        $data = $table->export_for_template($this);
        return $this->render_from_template('local_batchpreview/preview_table', $data);
    }

    /**
     * Render le générateur SQL
     */
    public function render_sql_generator(sql_generator $generator) {
        $data = $generator->export_for_template($this);
        return $this->render_from_template('local_batchpreview/sql_generator', $data);
    }

    /**
     * Render les statistiques
     */
    public function render_statistics($stats) {
        $context = [
            'total_rooms' => $stats['total_rooms'],
            'affected_courses' => $stats['affected_courses'],
            'category_name' => $stats['category_name'],
            'category_id' => $stats['category_id'],
            'system_type' => $stats['system_type'] ?? 'collaborate'
        ];
        
        return $this->render_from_template('local_batchpreview/statistics', $context);
    }
}

/**
 * Classe pour les données du tableau de prévisualisation
 */
class preview_table implements renderable, templatable {
    
    protected $data;
    protected $categoryid;
    protected $system_type;
    protected $options;
    
    public function __construct($data, $categoryid, $system_type = 'collaborate', $options = []) {
        $this->data = $data;
        $this->categoryid = $categoryid;
        $this->system_type = $system_type;
        $this->options = $options;
    }
    
    public function export_for_template(\renderer_base $output) {
        global $DB;
        
        // Obtenir le nom de la catégorie et les infos du système
        $category = $DB->get_record('course_categories', ['id' => $this->categoryid]);
        $table_info = get_system_table_info($this->system_type);
        
        $rows = [];
        foreach ($this->data as $item) {
            $rows[] = [
                'room_id' => $item['room_id'],
                'current_name' => $item['current_name'],
                'new_name' => $item['new_name'],
                'course_name' => $item['course_name'],
                'category_name' => $item['category_name'],
                'category_id' => $item['category_id'],
                'system_type' => $item['system_type'] ?? $this->system_type,
                'has_changes' => $item['current_name'] !== $item['new_name']
            ];
        }
        
        return [
            'rows' => $rows,
            'total_rows' => count($rows),
            'category_name' => $category ? $category->name : 'Inconnue',
            'category_id' => $this->categoryid,
            'system_name' => $table_info['display_name'],
            'system_type' => $this->system_type,
            'has_data' => !empty($rows),
            'show_category_id' => $this->options['show_category_id'] ?? true,
            'show_category_name' => $this->options['show_category_name'] ?? false,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Classe pour le générateur SQL
 */
class sql_generator implements renderable, templatable {
    
    protected $categoryid;
    protected $system_type;
    protected $prefix;
    protected $suffix;
    protected $options;
    protected $total_affected;
    
    public function __construct($categoryid, $system_type, $prefix, $suffix, $options, $total_affected = 0) {
        $this->categoryid = $categoryid;
        $this->system_type = $system_type;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
        $this->options = $options;
        $this->total_affected = $total_affected;
    }
    
    public function export_for_template(\renderer_base $output) {
        $sql_code = $this->generate_sql();
        $table_info = get_system_table_info($this->system_type);
        
        return [
            'sql_code' => $sql_code,
            'category_id' => $this->categoryid,
            'system_type' => $this->system_type,
            'system_name' => $table_info['display_name'],
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'total_affected' => $this->total_affected,
            'timestamp' => date('Y-m-d H:i:s'),
            'filename' => 'batch_update_' . $table_info['table'] . '_cat' . $this->categoryid . '_' . date('Ymd_His') . '.sql',
            'show_category_id' => $this->options['show_category_id'] ?? true,
            'show_category_name' => $this->options['show_category_name'] ?? false
        ];
    }
    
    /**
     * Génère le code SQL (format MySQL)
     */
    private function generate_sql() {
        $table_info = get_system_table_info($this->system_type);
        $table_name = 'mdl_' . $table_info['table'];
        
        $name_parts = ["'{$this->prefix}'", "mdl_course.fullname"];
        
        if (!empty($this->suffix)) {
            $name_parts[] = "'{$this->suffix}'";
        }
        
        if ($this->options['show_category_id']) {
            $name_parts[] = "'(Cat: '";
            $name_parts[] = "mdl_course_categories.id";
            $name_parts[] = "')'";
        }
        
        if ($this->options['show_category_name']) {
            $name_parts[] = "'['";
            $name_parts[] = "mdl_course_categories.name";
            $name_parts[] = "']'";
        }
        
        $concat_parts = "CONCAT(" . implode(", ' - ', ", $name_parts) . ")";
        
        $sql = "-- ================================================================\n";
        $sql .= "-- Code SQL généré automatiquement par Batch Preview Module\n";
        $sql .= "-- Date de génération: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Système: " . $table_info['display_name'] . "\n";
        $sql .= "-- Catégorie ciblée: {$this->categoryid}\n";
        $sql .= "-- Nombre d'enregistrements affectés: {$this->total_affected}\n";
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
        $sql .= "    mdl_course_categories.path LIKE '%/{$this->categoryid}/%' OR\n";
        $sql .= "    mdl_course_categories.path LIKE '%/{$this->categoryid}' OR\n";
        $sql .= "    mdl_course_categories.id = {$this->categoryid}\n";
        $sql .= ")\n";
        $sql .= "ORDER BY mdl_course_categories.id, mdl_course.fullname;\n\n";
        
        // Requête de mise à jour MySQL
        $sql .= "-- 2. Requête de mise à jour (à exécuter après vérification)\n";
        $sql .= "UPDATE {$table_name}\n";
        $sql .= "    INNER JOIN mdl_course ON mdl_course.id = {$table_name}.course\n";
        $sql .= "    INNER JOIN mdl_course_categories ON mdl_course.category = mdl_course_categories.id\n";
        $sql .= "SET {$table_name}.name = {$concat_parts}\n";
        $sql .= "WHERE (\n";
        $sql .= "    mdl_course_categories.path LIKE '%/{$this->categoryid}/%' OR\n";
        $sql .= "    mdl_course_categories.path LIKE '%/{$this->categoryid}' OR\n";
        $sql .= "    mdl_course_categories.id = {$this->categoryid}\n";
        $sql .= ");\n\n";
        
        $sql .= "-- ================================================================";
        
        return $sql;
    }
}