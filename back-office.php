<?php

// Sécurité: Empêcher l'accès direct aux fichiers
defined('ABSPATH') or die('Access denied.');

// Menu "Réservation" dans le Back-office
function ows_cal_back_office_page() {
    add_menu_page(
        'Réservation',
        'Réservation',
        'manage_options',
        'back-office.php',
        'ows_cal_back_office_page_html',
        'dashicons-calendar-alt',
        6
    );
}

add_action( 'admin_menu', 'ows_cal_back_office_page' );

function ows_cal_back_office_page_html() {
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Réservations</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column">ID</th>
                    <th scope="col" class="manage-column">Email</th>
                    <th scope="col" class="manage-column">Date</th>
                    <th scope="col" class="manage-column">Heure</th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'reservations';
                $reservations = $wpdb->get_results("SELECT * FROM {$table_name}");
                foreach ($reservations as $reservation) {
                    $user_info = get_userdata($reservation->user_id);
                    echo '<tr>';
                    echo '<td>' . esc_html($reservation->id) . '</td>';
                    echo '<td>' . esc_html($user_info->user_email) . '</td>';
                    echo '<td>' . esc_html($reservation->reservation_date) . '</td>';
                    echo '<td>' . esc_html($reservation->time_slot) . '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}
