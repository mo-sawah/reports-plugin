<?php
/**
 * Plugin Name:       Reports Plugin
 * Description:       Adds a custom post type for "Reports" with custom fields and a download form shortcode.
 * Version:           1.1.1
 * Author:            Mohamed Sawah
 * Author URI:        https://sawahsolutions.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       reports-plugin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// 1. PLUGIN ACTIVATION (DATABASE TABLE CREATION)
// =============================================================================
function rp_activate_plugin() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reports_leads';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        submission_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        first_name varchar(100) DEFAULT '' NOT NULL,
        last_name varchar(100) DEFAULT '' NOT NULL,
        job_title varchar(100) DEFAULT '' NOT NULL,
        company varchar(100) DEFAULT '' NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(50) DEFAULT '' NOT NULL,
        country varchar(100) DEFAULT '' NOT NULL,
        report_id bigint(20) UNSIGNED NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'rp_activate_plugin' );

// =============================================================================
// 2. REGISTER CPT AND TAXONOMIES
// =============================================================================
function rp_register_post_type() {
    $labels = array('name' => _x( 'Reports', 'Post Type General Name', 'reports-plugin' ), 'singular_name' => _x( 'Report', 'Post Type Singular Name', 'reports-plugin' ), 'menu_name' => __( 'Reports', 'reports-plugin' ), 'name_admin_bar' => __( 'Report', 'reports-plugin' ), 'archives' => __( 'Report Archives', 'reports-plugin' ), 'attributes' => __( 'Report Attributes', 'reports-plugin' ), 'parent_item_colon' => __( 'Parent Report:', 'reports-plugin' ), 'all_items' => __( 'All Reports', 'reports-plugin' ), 'add_new_item' => __( 'Add New Report', 'reports-plugin' ), 'add_new' => __( 'Add New', 'reports-plugin' ), 'new_item' => __( 'New Report', 'reports-plugin' ), 'edit_item' => __( 'Edit Report', 'reports-plugin' ), 'update_item' => __( 'Update Report', 'reports-plugin' ), 'view_item' => __( 'View Report', 'reports-plugin' ), 'view_items' => __( 'View Reports', 'reports-plugin' ), 'search_items' => __( 'Search Report', 'reports-plugin' ));
    $args = array('label' => __( 'Report', 'reports-plugin' ), 'description' => __( 'For company reports and documents.', 'reports-plugin' ), 'labels' => $labels, 'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ), 'taxonomies' => array( 'report_category', 'report_topic' ), 'hierarchical' => false, 'public' => true, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 20, 'menu_icon' => 'dashicons-chart-area', 'show_in_admin_bar' => true, 'show_in_nav_menus' => true, 'can_export' => true, 'has_archive' => true, 'exclude_from_search' => false, 'publicly_queryable' => true, 'capability_type' => 'post', 'show_in_rest' => true);
    register_post_type( 'report', $args );
}
add_action( 'init', 'rp_register_post_type', 0 );

function rp_register_taxonomies() {
    // Categories
    $category_labels = array('name' => _x( 'Categories', 'taxonomy general name', 'reports-plugin' ), 'singular_name' => _x( 'Category', 'taxonomy singular name', 'reports-plugin' ), 'search_items' => __( 'Search Categories', 'reports-plugin' ), 'all_items' => __( 'All Categories', 'reports-plugin' ), 'parent_item' => __( 'Parent Category', 'reports-plugin' ), 'parent_item_colon' => __( 'Parent Category:', 'reports-plugin' ), 'edit_item' => __( 'Edit Category', 'reports-plugin' ), 'update_item' => __( 'Update Category', 'reports-plugin' ), 'add_new_item' => __( 'Add New Category', 'reports-plugin' ), 'new_item_name' => __( 'New Category Name', 'reports-plugin' ), 'menu_name' => __( 'Categories', 'reports-plugin' ));
    $category_args = array('hierarchical' => true, 'labels' => $category_labels, 'show_ui' => true, 'show_admin_column' => true, 'query_var' => true, 'show_in_rest' => true, 'rewrite' => array( 'slug' => 'report-category' ));
    register_taxonomy( 'report_category', array( 'report' ), $category_args );
    
    // Topics
    $topic_labels = array('name' => _x( 'Topics', 'taxonomy general name', 'reports-plugin' ), 'singular_name' => _x( 'Topic', 'taxonomy singular name', 'reports-plugin' ), 'search_items' => __( 'Search Topics', 'reports-plugin' ), 'popular_items' => __( 'Popular Topics', 'reports-plugin' ), 'all_items' => __( 'All Topics', 'reports-plugin' ), 'edit_item' => __( 'Edit Topic', 'reports-plugin' ), 'update_item' => __( 'Update Topic', 'reports-plugin' ), 'add_new_item' => __( 'Add New Topic', 'reports-plugin' ), 'new_item_name' => __( 'New Topic Name', 'reports-plugin' ), 'separate_items_with_commas' => __( 'Separate topics with commas', 'reports-plugin' ), 'add_or_remove_items' => __( 'Add or remove topics', 'reports-plugin' ), 'choose_from_most_used' => __( 'Choose from the most used topics', 'reports-plugin' ), 'not_found' => __( 'No topics found.', 'reports-plugin' ), 'menu_name' => __( 'Topics', 'reports-plugin' ));
    $topic_args = array('hierarchical' => false, 'labels' => $topic_labels, 'show_ui' => true, 'show_admin_column' => true, 'query_var' => true, 'show_in_rest' => true, 'rewrite' => array( 'slug' => 'report-topic' ));
    register_taxonomy( 'report_topic', array( 'report' ), $topic_args );
}
add_action( 'init', 'rp_register_taxonomies' );

// =============================================================================
// 3. CUSTOM FIELDS (META BOX)
// =============================================================================
function rp_add_meta_box() {
    add_meta_box('rp_details', 'Report Details', 'rp_meta_box_callback', 'report', 'normal', 'high');
}
add_action( 'add_meta_boxes', 'rp_add_meta_box' );

function rp_meta_box_callback( $post ) {
    wp_nonce_field( 'rp_save_meta_box_data', 'rp_meta_box_nonce' );
    $download_link = get_post_meta( $post->ID, '_rp_download_link', true );
    ?>
    <style>.rp-meta-box table{width:100%}.rp-meta-box table td{padding:10px 5px}.rp-meta-box table input,.rp-meta-box table select{width:100%}.rp-meta-box label{font-weight:700}.rp-file-uploader{display:flex;align-items:center;gap:10px}</style>
    <div class="rp-meta-box">
        <p>Upload the report file or paste the direct download link below.</p>
        <table>
             <tr>
                <td><label for="rp_category">Category</label></td>
                <td>
                    <?php
                    // Get the terms associated with this post
                    $terms = wp_get_post_terms( $post->ID, 'report_category', array( 'fields' => 'ids' ) );
                    $selected_term = ! empty( $terms ) ? $terms[0] : 0;

                    wp_dropdown_categories( array(
                        'show_option_none' => 'Select a Category',
                        'taxonomy'         => 'report_category',
                        'name'             => 'rp_category',
                        'selected'         => $selected_term,
                        'hierarchical'     => true,
                        'class'            => 'widefat',
                        'value_field'      => 'term_id',
                    ) );
                    ?>
                </td>
            </tr>
            <tr>
                <td><label for="rp_download_link">Download File</label></td>
                <td>
                    <div class="rp-file-uploader">
                        <input type="text" id="rp_download_link" name="rp_download_link" value="<?php echo esc_url( $download_link ); ?>" style="flex-grow: 1;" placeholder="Select or upload a file, or paste a URL">
                        <button type="button" class="button" id="rp_upload_file_button">Upload File</button>
                        <button type="button" class="button button-secondary" id="rp_remove_file_button" style="<?php echo empty($download_link) ? 'display:none;' : ''; ?>">Remove File</button>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

function rp_save_meta_data( $post_id ) {
    if ( ! isset( $_POST['rp_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['rp_meta_box_nonce'], 'rp_save_meta_box_data' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( !isset($_POST['post_type']) || 'report' !== $_POST['post_type'] ) return;
    
    // Save the download link
    if ( isset( $_POST['rp_download_link'] ) ) {
        $value = esc_url_raw($_POST['rp_download_link']);
        update_post_meta( $post_id, '_rp_download_link', $value );
    }
    
    // Save the category
    if ( isset( $_POST['rp_category'] ) ) {
        $term_id = intval( $_POST['rp_category'] );
        if ( $term_id > 0 ) {
             wp_set_post_terms( $post_id, $term_id, 'report_category' );
        } else {
             // If "Select a Category" was chosen, remove existing terms
             wp_set_post_terms( $post_id, '', 'report_category' );
        }
    }
}
add_action( 'save_post', 'rp_save_meta_data' );

// =============================================================================
// 4. HELPER FUNCTION FOR COUNTRIES
// =============================================================================
function rp_get_countries_list() {
    return array(
        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa', 'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil', 'IO' => 'British Indian Ocean Territory', 'VG' => 'British Virgin Islands', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CX' => 'Christmas Island', 'CC' => 'Cocos (Keeling) Islands', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo - Brazzaville', 'CD' => 'Congo - Kinshasa', 'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Côte d’Ivoire', 'HR' => 'Croatia', 'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia', 'SZ' => 'Eswatini', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands', 'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia', 'TF' => 'French Southern Territories', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada', 'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HN' => 'Honduras', 'HK' => 'Hong Kong SAR China', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macao SAR China', 'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico', 'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar (Burma)', 'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island', 'KP' => 'North Korea', 'MK' => 'North Macedonia', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestinian Territories', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn Islands', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RE' => 'Réunion', 'RO' => 'Romania', 'RU' => 'Russia', 'RW' => 'Rwanda', 'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'São Tomé & Príncipe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SX' => 'Sint Maarten', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia & South Sandwich Islands', 'KR' => 'South Korea', 'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'BL' => 'St. Barthélemy', 'SH' => 'St. Helena', 'KN' => 'St. Kitts & Nevis', 'LC' => 'St. Lucia', 'MF' => 'St. Martin', 'PM' => 'St. Pierre & Miquelon', 'VC' => 'St. Vincent & Grenadines', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard & Jan Mayen', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad & Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TC' => 'Turks & Caicos Islands', 'TV' => 'Tuvalu', 'UM' => 'U.S. Outlying Islands', 'VI' => 'U.S. Virgin Islands', 'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City', 'VE' => 'Venezuela', 'VN' => 'Vietnam', 'WF' => 'Wallis & Futuna', 'EH' => 'Western Sahara', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
    );
}

// =============================================================================
// 5. SHORTCODES
// =============================================================================
function rp_register_shortcodes() {
    add_shortcode( 'report_download_form', 'rp_download_form_shortcode_callback' );
    add_shortcode( 'report_content', 'rp_content_shortcode_callback' );
}
add_action( 'init', 'rp_register_shortcodes' );

function rp_content_shortcode_callback() {
    if ( is_singular( 'report' ) ) {
        // Get the raw content from the editor
        $content = get_post_field( 'post_content', get_the_ID() );
        // Apply WordPress filters like wpautop to format it correctly
        return '<div class="rp-formatted-content">' . apply_filters( 'the_content', $content ) . '</div>';
    }
    return ''; // Return nothing if not on a single report page
}


function rp_download_form_shortcode_callback() {
    if ( is_admin() || ! is_singular( 'report' ) ) return '';
    $post_id = get_the_ID();
    $download_link = get_post_meta( $post_id, '_rp_download_link', true );
    if ( empty( $download_link ) ) return '<!-- Report download link not set. -->';
    
    wp_enqueue_style( 'rp-style', plugin_dir_url( __FILE__ ) . 'assets/css/reports-style.css', array(), '1.1.1' );
    wp_enqueue_script( 'rp-script', plugin_dir_url( __FILE__ ) . 'assets/js/reports-script.js', array( 'jquery' ), '1.1.1', true );
    wp_localize_script( 'rp-script', 'rp_ajax_object', array('ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'rp_lead_nonce' ), 'post_id' => $post_id));
    
    $color_options = get_option('rp_color_settings');
    $content_options = get_option('rp_content_settings');

    $bg_color = !empty($color_options['background_color']) ? $color_options['background_color'] : '#FFFFFF';
    $border_color = !empty($color_options['border_color']) ? $color_options['border_color'] : '#e0e0e0';
    $text_color = !empty($color_options['text_color']) ? $color_options['text_color'] : '#555555';
    $primary_color = !empty($color_options['primary_color']) ? $color_options['primary_color'] : '#0073e6';
    $link_color = !empty($color_options['link_color']) ? $color_options['link_color'] : '#0073e6';
    $privacy_url = !empty($content_options['privacy_policy_url']) ? esc_url($content_options['privacy_policy_url']) : '#';

    $site_name = get_bloginfo('name');
    $disclaimer_text = sprintf(
        'By clicking the button below, I am confirming that I would like additional information and offers related to Informa %s’s products and services. I also acknowledge that I have read and agree to Informa %s’s <a href="%s" target="_blank">privacy policy</a>.',
        esc_html($site_name),
        esc_html($site_name),
        $privacy_url
    );


    ob_start();
    ?>
    <style>
        :root {
            --rp-bg-color: <?php echo esc_attr($bg_color); ?>;
            --rp-border-color: <?php echo esc_attr($border_color); ?>;
            --rp-text-color: <?php echo esc_attr($text_color); ?>;
            --rp-primary-color: <?php echo esc_attr($primary_color); ?>;
            --rp-link-color: <?php echo esc_attr($link_color); ?>;
        }
    </style>
    <div id="rp-download-box" class="rp-download-box" data-download-url="<?php echo esc_url($download_link); ?>">
        <div id="rp-form-container">
            <form id="rp-lead-form" class="rp-lead-form">
                <div class="rp-form-field"><input type="text" name="rp_first_name" placeholder="First Name" required></div>
                <div class="rp-form-field"><input type="text" name="rp_last_name" placeholder="Last Name" required></div>
                <div class="rp-form-field"><input type="text" name="rp_job_title" placeholder="Job Title" required></div>
                <div class="rp-form-field"><input type="text" name="rp_company" placeholder="Company" required></div>
                <div class="rp-form-field"><input type="email" name="rp_email" placeholder="Email" required></div>
                <div class="rp-form-field"><input type="tel" name="rp_phone" placeholder="Phone"></div>
                <div class="rp-form-field">
                    <select name="rp_country" required>
                        <option value="" disabled selected>Country</option>
                        <?php foreach (rp_get_countries_list() as $name) : ?>
                            <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="rp-disclaimer"><?php echo $disclaimer_text; ?></p>
                <button type="submit">Download E-book</button>
            </form>
            <div id="rp-form-error" class="rp-form-error" style="display: none;"></div>
        </div>
        <div id="rp-loader" class="rp-loader" style="display: none;"></div>
        <div id="rp-result" class="rp-result" style="display: none;"></div>
    </div>
    <?php
    return ob_get_clean();
}

// =============================================================================
// 6. AJAX HANDLER FOR SAVING LEADS
// =============================================================================
function rp_save_lead_callback() {
    check_ajax_referer( 'rp_lead_nonce', 'nonce' );
    global $wpdb;
    $table_name = $wpdb->prefix . 'reports_leads';

    // Check required fields
    if ( empty($_POST['email']) || empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['job_title']) || empty($_POST['company']) || empty($_POST['country']) ) {
        wp_send_json_error( 'Please fill in all required fields.' );
    }

    $email = sanitize_email( $_POST['email'] );
    $post_id = intval( $_POST['post_id'] );
    
    if ( !is_email( $email ) || !$post_id ) {
        wp_send_json_error( 'Invalid data provided.' );
    }

    $data = array(
        'submission_date' => current_time( 'mysql' ),
        'email'           => $email,
        'report_id'       => $post_id,
        'first_name'      => sanitize_text_field( $_POST['first_name'] ),
        'last_name'       => sanitize_text_field( $_POST['last_name'] ),
        'job_title'       => sanitize_text_field( $_POST['job_title'] ),
        'company'         => sanitize_text_field( $_POST['company'] ),
        'phone'           => sanitize_text_field( $_POST['phone'] ),
        'country'         => sanitize_text_field( $_POST['country'] ),
    );
    
    $result = $wpdb->insert($table_name, $data);

    if ($result) {
        wp_send_json_success( 'Lead saved!' );
    } else {
        wp_send_json_error( 'Could not save lead to the database.' );
    }
}
add_action( 'wp_ajax_rp_save_lead', 'rp_save_lead_callback' );
add_action( 'wp_ajax_nopriv_rp_save_lead', 'rp_save_lead_callback' );

// =============================================================================
// 7. ADMIN MENU & PAGES (LEADS & SETTINGS)
// =============================================================================
function rp_admin_menu() {
    add_submenu_page('edit.php?post_type=report', 'Collected Emails', 'Collected Emails', 'manage_options', 'rp-leads', 'rp_leads_page_callback');
    add_submenu_page('edit.php?post_type=report', 'Settings', 'Settings', 'manage_options', 'rp-settings', 'rp_settings_page_callback');
}
add_action( 'admin_menu', 'rp_admin_menu' );

function rp_settings_page_callback() {
    ?>
    <div class="wrap rp-settings-wrap">
        <h1>Reports Plugin Settings</h1>
        <form method="post" action="options.php">
            <div class="rp-settings-container">
                <div class="rp-settings-main">
                    <?php
                        settings_fields( 'rp_settings_group' );
                        do_settings_sections( 'rp-settings-page' );
                        submit_button();
                    ?>
                </div>
                <div class="rp-settings-sidebar">
                    <div id="rp-preview-container">
                        <h2>Preview</h2>
                        <div id="rp-preview-box" class="rp-download-box" style="margin: 0;">
                             <div class="rp-lead-form">
                                <div class="rp-form-field"><input type="text" disabled placeholder="First Name"></div>
                                <div class="rp-form-field"><input type="text" disabled placeholder="Last Name"></div>
                                <p class="rp-disclaimer" style="text-align: left;">... I also acknowledge that I have read and agree to Informa (site-name)’s <a href="#" id="rp-preview-link">privacy policy</a>.</p>
                                <button type="button" id="rp-preview-button">Download E-book</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function rp_settings_init() {
    // Register settings group
    register_setting( 'rp_settings_group', 'rp_color_settings' );
    register_setting( 'rp_settings_group', 'rp_content_settings' );

    // Colors Section
    add_settings_section( 'rp_colors_section', 'Form Colors', null, 'rp-settings-page' );
    $color_fields = [
        'background_color' => ['label' => 'Background Color', 'default' => '#FFFFFF', 'description' => 'Used for the form container background.'],
        'border_color'     => ['label' => 'Border Color', 'default' => '#e0e0e0', 'description' => 'Used for form borders and container outlines.'],
        'title_color'      => ['label' => 'Title Color', 'default' => '#333333', 'description' => 'Used for the main title "Download Report".'],
        'text_color'       => ['label' => 'Text Color', 'default' => '#555555', 'description' => 'Used for body text, descriptions, and labels.'],
        'primary_color'    => ['label' => 'Primary Color', 'default' => '#0073e6', 'description' => 'Used for the main button background.'],
        'secondary_color'  => ['label' => 'Secondary Color', 'default' => '#005a9c', 'description' => 'Used for button hover effects and active states.'],
        'link_color'       => ['label' => 'Link Color', 'default' => '#0073e6', 'description' => 'Used for all links in the form.'],
    ];
    foreach($color_fields as $id => $field){
        add_settings_field($id, $field['label'], 'rp_color_field_callback', 'rp-settings-page', 'rp_colors_section', ['id' => $id, 'default' => $field['default'], 'description' => $field['description']]);
    }

    // Content Section
    add_settings_section( 'rp_content_section', 'Content & Links', null, 'rp-settings-page' );
    add_settings_field('privacy_policy_url', 'Privacy Policy URL', 'rp_text_field_callback', 'rp-settings-page', 'rp_content_section', ['id' => 'privacy_policy_url', 'description' => 'The destination for the "Privacy Policy" link in the form.']);

}
add_action( 'admin_init', 'rp_settings_init' );


function rp_color_field_callback($args) {
    $options = get_option('rp_color_settings');
    $id = esc_attr($args['id']);
    $default = esc_attr($args['default']);
    $description = esc_html($args['description']);
    $value = !empty($options[$id]) ? esc_attr($options[$id]) : $default;
    echo "<input type='text' name='rp_color_settings[{$id}]' value='{$value}' class='rp-color-picker' data-default-color='{$default}'>";
    echo "<p class='description'>{$description}</p>";
}

function rp_text_field_callback($args) {
    $options = get_option('rp_content_settings');
    $id = esc_attr($args['id']);
    $description = esc_html($args['description']);
    $value = !empty($options[$id]) ? esc_attr($options[$id]) : '';
    echo "<input type='url' name='rp_content_settings[{$id}]' value='{$value}' class='regular-text'>";
    echo "<p class='description'>{$description}</p>";
}


function rp_enqueue_admin_scripts( $hook_suffix ) {
    $screen = get_current_screen();
    if ( 'report_page_rp-settings' === $hook_suffix ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'rp-admin-script', plugin_dir_url( __FILE__ ) . 'assets/js/reports-admin.js', array( 'wp-color-picker' ), '1.1.0', true );
    }
    if ( 'post.php' == $hook_suffix || 'post-new.php' == $hook_suffix ) {
        if ( isset($screen->post_type) && 'report' == $screen->post_type ) {
            wp_enqueue_media();
            wp_enqueue_script( 'rp-post-edit-script', plugin_dir_url( __FILE__ ) . 'assets/js/reports-post-edit.js', array( 'jquery' ), '1.1.0', true );
        }
    }
}
add_action( 'admin_enqueue_scripts', 'rp_enqueue_admin_scripts' );

function rp_admin_settings_page_styles() {
    $screen = get_current_screen();
    if ( 'report_page_rp-settings' !== $screen->id ) return;
    ?>
    <style>
        .rp-settings-container { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .rp-settings-main .form-table { margin-top: 0; }
        .rp-settings-main h2 { padding-bottom: 10px; border-bottom: 1px solid #c3c4c7; }
        #rp-preview-container { background: #f6f7f7; padding: 20px; border-radius: 4px; position: sticky; top: 50px; }
        #rp-preview-container h2 { margin-top: 0; }
        #rp-preview-box { transition: all 0.3s ease; max-width: 100%; }
        .form-table th { padding: 20px 10px 20px 0; width: 150px; }
        .form-table td { padding: 15px 10px; }
        .rp-color-picker { width: 100px; }
        .wp-picker-container .wp-color-result.button { min-height: 40px; }
        .wp-picker-container .wp-color-result-text { line-height: 38px; }
    </style>
    <?php
}
add_action('admin_head', 'rp_admin_settings_page_styles');


function rp_leads_page_callback() {
    $leads_table = new RP_Leads_List_Table();
    $leads_table->prepare_items();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Collected Emails</h1>
        <a href="<?php echo admin_url('edit.php?post_type=report&page=rp-leads&action=export_csv'); ?>" class="page-title-action">Export to CSV</a>
        <form method="post">
            <?php $leads_table->display(); ?>
        </form>
    </div>
    <?php
}

// =============================================================================
// 8. WP_LIST_TABLE FOR LEADS
// =============================================================================
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class RP_Leads_List_Table extends WP_List_Table {
    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />', 
            'email' => 'Email', 
            'name' => 'Name',
            'company' => 'Company',
            'country' => 'Country',
            'report_title' => 'Report', 
            'submission_date' => 'Date'
        ];
    }
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reports_leads';
        $per_page = 20;
        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
        $orderby = !empty($_REQUEST['orderby']) ? esc_sql($_REQUEST['orderby']) : 'submission_date';
        $order = !empty($_REQUEST['order']) ? esc_sql($_REQUEST['order']) : 'DESC';
        $query = "SELECT l.id, l.email, l.submission_date, p.post_title as report_title, l.first_name, l.last_name, l.company, l.country FROM $table_name l LEFT JOIN {$wpdb->posts} p ON l.report_id = p.ID ORDER BY $orderby $order";
        $total_items = $wpdb->query($query);
        $current_page = $this->get_pagenum();
        $this->set_pagination_args(['total_items' => $total_items, 'per_page' => $per_page]);
        $paged_query = $query . " LIMIT " . (($current_page - 1) * $per_page) . ", $per_page";
        $this->items = $wpdb->get_results($paged_query, ARRAY_A);
    }
    public function column_default( $item, $column_name ) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : 'N/A';
    }
     public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="lead[]" value="%s" />', $item['id'] );
    }
    public function column_name( $item ) {
        return esc_html( trim( $item['first_name'] . ' ' . $item['last_name'] ) );
    }
    public function column_submission_date( $item ) {
        return date_i18n( 'j F Y', strtotime( $item['submission_date'] ) );
    }
    protected function get_sortable_columns() {
        return ['email' => ['email', false], 'submission_date' => ['submission_date', true], 'name' => ['last_name', false], 'company' => ['company', false], 'country' => ['country', false]];
    }
}

// =============================================================================
// 9. EXPORT TO CSV
// =============================================================================
function rp_export_leads_to_csv() {
    if (isset($_GET['page']) && $_GET['page'] == 'rp-leads' && isset($_GET['action']) && $_GET['action'] == 'export_csv') {
        if (!current_user_can('manage_options')) {
            return;
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'reports_leads';
        $results = $wpdb->get_results("SELECT l.first_name, l.last_name, l.email, l.job_title, l.company, l.phone, l.country, p.post_title, l.submission_date FROM $table_name l LEFT JOIN {$wpdb->posts} p ON l.report_id = p.ID ORDER BY l.submission_date DESC", ARRAY_A);
        if ($results) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=reports-leads-' . date('Y-m-d') . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, array('First Name', 'Last Name', 'Email', 'Job Title', 'Company', 'Phone', 'Country', 'Report Title', 'Submission Date'));
            foreach ($results as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();
        }
    }
}
add_action('admin_init', 'rp_export_leads_to_csv');


