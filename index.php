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
    'Cet outil vous permet de prévisualiser les modifications qui seront apportées aux salles Collaborate avant de les appliquer.',
    array('class' => 'description')
);

// Afficher le formulaire
$mform->display();

// Si le formulaire est soumis, rediriger vers la page de prévisualisation
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

echo html_writer::end_div();

// CORRECTION: JavaScript simple pour l'animation (optionnel)
echo '<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    console.log("Index page JavaScript loaded");
    
    // Animation simple pour les fieldsets si jQuery est disponible
    function initAnimation() {
        if (typeof $ !== "undefined") {
            $(".mform fieldset").hide().fadeIn(800);
            console.log("Animation des fieldsets activée");
        } else {
            console.log("jQuery non disponible pour l\'animation");
        }
    }
    
    // Essayer après un délai
    setTimeout(initAnimation, 500);
});
</script>';

echo $OUTPUT->footer();
?>