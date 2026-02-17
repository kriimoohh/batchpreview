<?php
namespace local_batchpreview\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class category_form extends \moodleform {
    
    public function definition() {
        $mform = $this->_form;
        
        // En-tête
        $mform->addElement('header', 'general', get_string('batchpreview', 'local_batchpreview'));
        
        // Sélecteur de système
        $system_options = array(
            'collaborate' => 'Collaborate',
            'bigbluebuttonbn' => 'BigBlueButton'
        );
        $mform->addElement('select', 'system_type', 'Type de système', $system_options);
        $mform->setDefault('system_type', 'collaborate');
        $mform->addHelpButton('system_type', 'system_type', 'local_batchpreview');
        
        // ID de catégorie
        $mform->addElement('text', 'categoryid', get_string('categoryid', 'local_batchpreview'), 
                          array('size' => 10, 'placeholder' => 'Ex: 59'));
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', 'Requis', 'required', null, 'client');
        $mform->addHelpButton('categoryid', 'categoryid', 'local_batchpreview');
        
        // Préfixe personnalisé (optionnel)
        $mform->addElement('text', 'prefix', 'Préfixe personnalisé',
                          array('size' => 50, 'maxlength' => 100, 'placeholder' => 'Salle de Travaux Dirigés'));
        $mform->setType('prefix', PARAM_TEXT);
        $mform->setDefault('prefix', 'Salle de Travaux Dirigés');
        $mform->addRule('prefix', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        // Suffixe personnalisé (optionnel)
        $mform->addElement('text', 'suffix', 'Suffixe personnalisé',
                          array('size' => 30, 'maxlength' => 100, 'placeholder' => 'SOCIO - P10L3S5'));
        $mform->setType('suffix', PARAM_TEXT);
        $mform->setDefault('suffix', 'SOCIO - P10L3S5');
        $mform->addRule('suffix', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');
        
        // Options d'affichage
        $mform->addElement('header', 'display_options', 'Options d\'affichage');
        
        $mform->addElement('checkbox', 'show_category_id', 'Inclure l\'ID de catégorie dans le nom');
        $mform->setDefault('show_category_id', 1);
        
        $mform->addElement('checkbox', 'show_category_name', 'Inclure le nom de catégorie');
        $mform->setDefault('show_category_name', 0);
        
        // Boutons
        $this->add_action_buttons(false, get_string('preview', 'local_batchpreview'));
    }
    
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        
        // Vérifier que la catégorie existe
        if (!empty($data['categoryid'])) {
            if (!$DB->record_exists('course_categories', array('id' => $data['categoryid']))) {
                $errors['categoryid'] = get_string('invalid_category', 'local_batchpreview');
            }
        }
        
        // Vérifier que le système sélectionné a des tables disponibles
        if (!empty($data['system_type'])) {
            $table_name = $data['system_type'];
            if (!$DB->get_manager()->table_exists($table_name)) {
                $errors['system_type'] = get_string('invalid_system', 'local_batchpreview');
            }
        }
        
        return $errors;
    }
}