<?php
/**
 * Plugin Name: OmniWebSolutions simple calendar
 * Description: Une simple extension de calendrier interactif par OmniWebSolutions.fr
 * Version: 1.0
 * Author: Vilguax
 * Author URI: https://omniwebsolutions.fr
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/


// Sécurité: Empêcher l'accès direct aux fichiers
defined('ABSPATH') or die('Access denied.');

// Inclusion du fichier back-office.php
require_once plugin_dir_path( __FILE__ ) . 'back-office.php';

// Hook d'activation pour créer la table de réservation
register_activation_hook(__FILE__, 'ows_cal_create_reservation_table');

// Fonction pour créer la table lors de l'activation du plugin
function ows_cal_create_reservation_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    $check_table_query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
    if($wpdb->get_var($check_table_query) != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            reservation_date date NOT NULL,
            time_slot varchar(5) NOT NULL, 
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}


// Fonction pour afficher le calendrier
function ows_cal_display_simple_calendar() {
    ob_start(); ?>
    <div id="calendar-container" data-user-id="<?php echo esc_attr(get_current_user_id()); ?>">
        <div id="calendar-header">
            <button id="prev-month">Mois précédent</button>
            <span id="current-month-year"></span>
            <button id="next-month">Mois suivant</button>
        </div>
        <table id="calendar-table"></table>
        <div id="timeslot-container"></div>
        <button id="reserve-button" style="display:none;">Réserver</button>
        <div id="error-message"></div>
    </div>
    <script defer src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'calendar.js'); ?>"></script>
    <link rel="stylesheet" href="<?php echo esc_url(plugin_dir_url(__FILE__) . 'calendar.css'); ?>">
    <?php
    return ob_get_clean();
}

// Shortcode pour afficher le calendrier
add_shortcode('simple_calendar', 'ows_cal_display_simple_calendar');

// Fonction pour enregistrer les scripts et les styles
function ows_cal_enqueue_my_scripts() {
        wp_enqueue_script('my-calendar', plugin_dir_url(__FILE__) . 'calendar.js', array('jquery'), null, true);
        wp_localize_script('my-calendar', 'my_script_vars', array('ajaxurl' => admin_url('admin-ajax.php'), 
        'nonce' => wp_create_nonce('action_fetch_reserved_slots'),
        'nonce_reserve_slots' => wp_create_nonce('action_reserve_slots')
        ));
}

add_action('wp_enqueue_scripts', 'ows_cal_enqueue_my_scripts');
add_action('wp_ajax_reserve_slots', 'ows_cal_reserve_slots_handler');

function ows_cal_reserve_slots_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'action_reserve_slots')) {
        wp_send_json_error('Nonce non valide.');
        exit;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission non accordée.');
        exit;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    $user_id = intval($_POST['userId']);
    $date = sanitize_text_field($_POST['date']);
    $slots = json_decode(stripslashes($_POST['slots']));

    foreach ($slots as $slot) {
        $time_slot = sanitize_text_field($slot->time);
        $is_reserved = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE reservation_date = %s AND time_slot = %s",
            $date, $time_slot
        ));
        if(!$is_reserved) {
            $success = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'reservation_date' => $date,
                    'time_slot' => $time_slot
                ),
                array('%d', '%s', '%s')
            );
            if($success) {
                ows_cal_send_reservation_email($user_id, $date, $time_slot);
            }
        }
    }

    $site_url = get_site_url();
    $redirect_url = $site_url . '/calendar/remerciement/';
    wp_send_json_success(array('redirect_url' => $redirect_url));
}

add_action('wp_ajax_fetch_reserved_slots', 'ows_cal_fetch_reserved_slots_handler');

function ows_cal_fetch_reserved_slots_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'action_fetch_reserved_slots')) {
        wp_send_json_error('Nonce non valide.');
        exit;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission non accordée.');
        exit;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    $date = sanitize_text_field($_POST['date']);

    $reserved_slots = $wpdb->get_col($wpdb->prepare(
        "SELECT time_slot FROM $table_name WHERE reservation_date = %s",
        $date
    ));

    if (false === $reserved_slots) {
        error_log('Erreur lors de la récupération des créneaux réservés.');
        wp_send_json_error('Could not get reserved slots');
    } else {
        wp_send_json_success($reserved_slots);
    }
}

// Fonction pour envoyer un email de notification de réservation à l'adresse d'administration de part l'adresse reservation@domaine
function ows_cal_send_reservation_email($user_id, $date, $time_slot) {
    $user_info = get_userdata($user_id);
    $to = get_option('admin_email');
    $subject = 'Nouvelle réservation !';
    $message = "L'utilisateur " . $user_info->user_login . " a fait une réservation pour le " . $date . " à " . $time_slot . ".";
    
    $site_url = get_site_url();
    $domain = parse_url($site_url, PHP_URL_HOST);
    $headers[] = 'From: Formulaire de réservation <reservation@' . $domain . '>';
    
    if(!wp_mail($to, $subject, $message, $headers)){
        error_log('L\'email de notification de réservation n\'a pas pu être envoyé.');
    } else {
        error_log('Email de notification de réservation envoyé avec succès.');
    }
}

// Ajoute le menu "Réservation" dans le back-office
add_action( 'admin_menu', 'ows_cal_back_office_page' );