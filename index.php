<?php
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/batchpreview/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('batchpreview', 'local_batchpreview'));
$PAGE->set_heading(get_string('batchpreview', 'local_batchpreview'));
$PAGE->set_pagelayout('admin');

// Inclure CSS seulement
$PAGE->requires->css('/local/batchpreview/styles/styles.css');

// CORRECTION: Supprimer l'appel AMD problématique
// $PAGE->requires->js_call_amd('local_batchpreview/preview', 'init');

// Créer le formulaire
$mform = new \local_batchpreview\form\category_form();

// Traiter la soumission avant l'affichage.
if ($data = $mform->get_data()) {
    $params = array(
        'categoryid' => $data->categoryid,
        'system_type' => $data->system_type,
        'prefix' => $data->prefix,
        'suffix' => $data->suffix,
        'show_category_id' => isset($data->show_category_id) ? 1 : 0,
        'show_category_name' => isset($data->show_category_name) ? 1 : 0
    );

    redirect(new moodle_url('/local/batchpreview/preview.php', $params));
}

echo $OUTPUT->header();

// Titre avec icône
echo html_writer::start_div('batch-preview-container');
echo html_writer::tag('div',
    html_writer::tag('i', '', array('class' => 'fa fa-search-plus')) . ' ' .
    get_string('batchpreview', 'local_batchpreview'),
    array('class' => 'main-title')
);

// Description
echo html_writer::tag('div',
    get_string('description', 'local_batchpreview'),
    array('class' => 'description')
);

// Afficher le formulaire
$mform->display();

echo html_writer::end_div();

echo $OUTPUT->footer();
?>