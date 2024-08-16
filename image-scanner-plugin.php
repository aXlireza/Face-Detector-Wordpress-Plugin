<?php
/*
Plugin Name: Image Scanner Plugin
Description: A plugin that simulates image scanning and analysis.
Version: 1.4
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue necessary scripts and styles
function isp_enqueue_scripts() {
    global $post;

    // Check if the post contains the shortcode
    if (isset($post->post_content) && has_shortcode($post->post_content, 'image_scanner')) { 
     
        wp_enqueue_script(
            'mediapipe-tasks-vision',
            'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision/vision_bundle.js',
            array(),
            null,
            true // Load in footer
        );
    
        // Adding the 'type' attribute to treat this script as a module
        add_filter('script_loader_tag', function($tag, $handle) {
            if ('mediapipe-tasks-vision' === $handle) {
                return str_replace('<script ', '<script type="module" ', $tag);
            } else if ('isp-script' === $handle) {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);

        wp_enqueue_style('isp-style', plugin_dir_url(__FILE__) . 'css/style.css');
        wp_enqueue_script('isp-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery', 'mediapipe-tasks-vision'), null, true);
        wp_localize_script('isp-script', 'isp_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }
}
add_action('wp_enqueue_scripts', 'isp_enqueue_scripts');

function isp_enqueue_admin_scripts() {
    wp_enqueue_style('isp-admin-style', plugin_dir_url(__FILE__) . 'css/admin.css');
    wp_enqueue_script('isp-admin-script', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'isp_enqueue_admin_scripts');

// Create shortcode
function isp_image_scanner_shortcode() {
    $warning_message = get_option('isp_warning_message', '');
    ob_start();
    ?>
    <div id="isp-container">
        <div id="isp-warning">
            <?php echo wpautop($warning_message); ?>
        </div>
        <form id="isp-form" enctype="multipart/form-data">
            <label for="isp-image">Choose Image</label>
            <input type="file" id="isp-image" name="isp-image" required>
            <button type="submit" style="display: none;">Scan Image</button>
        </form>
        <div id="isp-scanning">
            <div id="scanning-effect"></div>
            <img id="uploaded-image" src="" alt="Uploaded Image">
        </div>
        <div id="isp-result">
            <p id="isp-analysis"></p>
            <form id="isp-user-info">
                <input type="hidden" name="image_url" id="image-url" value="">
                <input type="text" name="name" placeholder="Name" required>
                <input type="number" name="age" placeholder="Age" required>
                <input type="text" name="contact" placeholder="Email or Phone" required>
                <button type="submit">Submit</button>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('image_scanner', 'isp_image_scanner_shortcode');

// Handle the image upload and analysis
function isp_handle_image_upload() {
    if (isset($_FILES['isp-image'])) {
        $uploaded_file = wp_handle_upload($_FILES['isp-image'], array('test_form' => false));
        if (isset($uploaded_file['file'])) {
            sleep(5); // Simulate scanning delay

            $messages = get_option('isp_analysis_messages', array());
            if (!is_array($messages)) {
                $messages = array();
            }
            if (empty($messages)) {
                $messages = array(
                    'Analysis Complete: Image is clear and sharp.',
                    'Analysis Complete: Image is blurry.',
                    'Analysis Complete: Image has good lighting.',
                    'Analysis Complete: Image has poor lighting.'
                );
            }
            $random_message = wpautop($messages[array_rand($messages)]);

            echo json_encode(array('success' => true, 'message' => $random_message, 'image_url' => $uploaded_file['url']));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Image upload failed.'));
        }
    }
    wp_die();
}
add_action('wp_ajax_isp_image_upload', 'isp_handle_image_upload');
add_action('wp_ajax_nopriv_isp_image_upload', 'isp_handle_image_upload');

// Handle the user info submission
function isp_handle_user_info() {
    if (isset($_POST['name']) && isset($_POST['age']) && isset($_POST['contact']) && isset($_POST['image_url'])) {
        $name = sanitize_text_field($_POST['name']);
        $age = intval($_POST['age']);
        $contact = sanitize_text_field($_POST['contact']);
        $image_url = esc_url_raw($_POST['image_url']);

        if (!empty($name) && $age > 0 && !empty($contact) && !empty($image_url)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'isp_user_info';
            $wpdb->insert($table_name, array(
                'name' => $name,
                'age' => $age,
                'contact' => $contact,
                'image_url' => $image_url,
            ));

            echo json_encode(array('success' => true, 'message' => 'Information saved successfully.'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Invalid input.'));
        }
    } else {
        echo json_encode(array('success' => false, 'message' => 'All fields are required.'));
    }
    wp_die();
}
add_action('wp_ajax_isp_user_info', 'isp_handle_user_info');
add_action('wp_ajax_nopriv_isp_user_info', 'isp_handle_user_info');

// Create database table for user information
function isp_create_user_info_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'isp_user_info';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        age smallint NOT NULL,
        contact text NOT NULL,
        image_url text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'isp_create_user_info_table');

// Add admin menu
function isp_add_admin_menu() {
    add_menu_page('Image Scanner Plugin', 'Image Scanner', 'edit_pages', 'image-scanner-plugin', 'isp_admin_page', 'dashicons-admin-tools', 6);
}
add_action('admin_menu', 'isp_add_admin_menu');

// Admin page content
function isp_admin_page() {
    ?>
    <div class="wrap">
        <h1>Image Scanner Plugin</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=image-scanner-plugin&tab=messages" class="nav-tab <?php echo isp_get_current_tab() == 'messages' ? 'nav-tab-active' : ''; ?>">Messages</a>
            <a href="?page=image-scanner-plugin&tab=user_info" class="nav-tab <?php echo isp_get_current_tab() == 'user_info' ? 'nav-tab-active' : ''; ?>">User Info</a>
        </h2>
        <div class="tab-content">
            <?php
            if (isp_get_current_tab() == 'messages') {
                isp_messages_page();
            } else {
                isp_user_info_page();
            }
            ?>
        </div>
    </div>
    <?php
}

function isp_get_current_tab() {
    return isset($_GET['tab']) ? $_GET['tab'] : 'messages';
}

// Messages settings page
function isp_messages_page() {
    ?>
    <form action="options.php" method="post">
        <?php
        settings_fields('isp_options');
        do_settings_sections('isp_options');
        submit_button();
        ?>
    </form>
    <h2>Analysis Messages</h2>
    <form id="isp-analysis-messages-form" method="post" action="options.php">
        <?php
        settings_fields('isp_analysis_messages_group');
        $messages = get_option('isp_analysis_messages', array());
        if (!is_array($messages)) {
            $messages = array();
        }
        ?>
        <div id="isp-analysis-messages-container">
            <?php foreach ($messages as $index => $message): ?>
                <div class="isp-message">
                    <textarea name="isp_analysis_messages[]"><?php echo esc_textarea($message); ?></textarea>
                    <button type="button" class="isp-remove-message">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="isp-add-message">Add Message</button>
        <?php submit_button(); ?>
    </form>
    <?php
}

function isp_warning_message_render() {
    $warning_message = get_option('isp_warning_message', '');
    ?>
    <textarea cols='50' rows='5' name='isp_warning_message'><?php echo esc_textarea($warning_message); ?></textarea>
    <p class="description">Enter the warning message to be displayed constantly to the user.</p>
    <?php
}

function isp_settings_section_callback() {
    echo __('Set the messages that will be displayed after image analysis and a constant warning message.', 'isp');
}

function isp_settings_init() {
    register_setting('isp_analysis_messages_group', 'isp_analysis_messages');
    register_setting('isp_options', 'isp_warning_message');

    add_settings_section(
        'isp_section',
        __('Image Scanner Settings', 'isp'),
        'isp_settings_section_callback',
        'isp_options'
    );

    add_settings_field(
        'isp_warning_message',
        __('Warning Message', 'isp'),
        'isp_warning_message_render',
        'isp_options',
        'isp_section'
    );
}
add_action('admin_init', 'isp_settings_init');

// User info page
function isp_user_info_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'isp_user_info';
    $results = $wpdb->get_results("SELECT * FROM $table_name");
    ?>
    <h2>User Information</h2>
    <table class="widefat">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Age</th>
                <th>Contact</th>
                <th>Image</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $row) : ?>
                <tr>
                    <td><?php echo $row->id; ?></td>
                    <td><?php echo $row->name; ?></td>
                    <td><?php echo $row->age; ?></td>
                    <td><?php echo $row->contact; ?></td>
                    <td><img src="<?php echo $row->image_url; ?>" alt="User Image" style="max-width: 100px;"></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
?>
