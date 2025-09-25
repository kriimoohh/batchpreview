<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Ajouter le plugin au menu d'administration principal
 */
function local_batchpreview_extend_navigation_user_settings($navigation, $user, $usercontext, $course, $coursecontext) {
    // Cette fonction est pour les paramètres utilisateur - pas nécessaire ici
}

/**
 * Ajouter le plugin au menu de navigation globale
 */
function local_batchpreview_extend_navigation(global_navigation $navigation) {
    global $USER, $PAGE;
    
    // Vérifier que l'utilisateur est connecté et a les bonnes permissions
    if (isloggedin() && has_capability('moodle/site:config', context_system::instance())) {
        $node = $navigation->add(
            get_string('batchpreview', 'local_batchpreview'),
            new moodle_url('/local/batchpreview/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'batchpreview',
            new pix_icon('i/report', '')
        );
        $node->showinflatnavigation = true;
    }
}

/**
 * PRINCIPALE: Ajouter le plugin au menu d'administration
 */
function local_batchpreview_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $CFG, $PAGE;

    // Vérifier que nous avons les bonnes permissions
    if (has_capability('moodle/site:config', context_system::instance())) {
        
        // Ajouter dans la section "Administration du site"
        if ($settingnode = $settingsnav->find('root', navigation_node::TYPE_SITE_ADMIN)) {
            
            // Créer le nœud pour notre plugin
            $node = navigation_node::create(
                get_string('batchpreview', 'local_batchpreview'),
                new moodle_url('/local/batchpreview/index.php'),
                navigation_node::TYPE_SETTING,
                null,
                'batchpreview',
                new pix_icon('i/report', get_string('batchpreview', 'local_batchpreview'))
            );
            
            // Ajouter le nœud à l'administration
            $settingnode->add_node($node);
        }
    }
}

/**
 * ALTERNATIVE: Hook pour le menu d'administration (Moodle 3.7+)
 */
function local_batchpreview_extend_navigation_category_settings($navigation, context_coursecat $context) {
    // Ajouter dans les paramètres de catégorie si nécessaire
    if (has_capability('moodle/site:config', context_system::instance())) {
        $url = new moodle_url('/local/batchpreview/index.php');
        $node = navigation_node::create(
            get_string('batchpreview', 'local_batchpreview'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'batchpreview'
        );
        $navigation->add_node($node);
    }
}

/**
 * Définir les capacités nécessaires
 */
function local_batchpreview_get_required_capabilities() {
    return array('moodle/site:config');
}

/**
 * Callback pour ajouter des liens dans le bloc d'administration
 */
function local_batchpreview_admin_setting_links() {
    global $CFG;
    
    if (has_capability('moodle/site:config', context_system::instance())) {
        return array(
            new admin_externalpage(
                'batchpreview',
                get_string('batchpreview', 'local_batchpreview'),
                new moodle_url('/local/batchpreview/index.php'),
                'moodle/site:config'
            )
        );
    }
    
    return array();
}