<?php
/**
 * Fichier: settings.php
 * Ajoute une page dans l'administration de Moodle
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Créer une page d'administration externe
    $ADMIN->add(
        'localplugins', 
        new admin_externalpage(
            'local_batchpreview',
            get_string('batchpreview', 'local_batchpreview'),
            new moodle_url('/local/batchpreview/index.php'),
            'moodle/site:config'
        )
    );
    
    // Alternative: Ajouter dans une catégorie spécifique
    /*
    $ADMIN->add(
        'reports', 
        new admin_externalpage(
            'local_batchpreview',
            get_string('batchpreview', 'local_batchpreview'),
            new moodle_url('/local/batchpreview/index.php'),
            'moodle/site:config'
        )
    );
    */
}