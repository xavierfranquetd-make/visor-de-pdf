<?php
/**
 * Plugin Name: Google Drive PDF Viewer
 * Plugin URI: https://example.com/gdrive-pdf-viewer
 * Description: Muestra automáticamente PDFs de una carpeta de Google Drive incrustados en tu página de WordPress
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: gdrive-pdf-viewer
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('GDRIVE_PDF_VIEWER_VERSION', '1.0.0');
define('GDRIVE_PDF_VIEWER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDRIVE_PDF_VIEWER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Incluir archivos necesarios
require_once GDRIVE_PDF_VIEWER_PLUGIN_DIR . 'includes/gdrive-api.php';
require_once GDRIVE_PDF_VIEWER_PLUGIN_DIR . 'includes/admin-settings.php';

/**
 * Activación del plugin
 */
function gdrive_pdf_viewer_activate() {
    // Guardar opciones por defecto
    $default_options = array(
        'api_key' => 'AIzaSyDAWeAsHPxW4wo8ihFf0OkNLPBpHXK5xJw',
        'folder_id' => '1a6jaQanVlMzz3QAiqRARp-z9OEccRI-S',
        'cache_duration' => 3600, // 1 hora
        'order_by' => 'recent' // recent o alphabetical
    );
    
    if (!get_option('gdrive_pdf_viewer_options')) {
        add_option('gdrive_pdf_viewer_options', $default_options);
    }
}
register_activation_hook(__FILE__, 'gdrive_pdf_viewer_activate');

/**
 * Desactivación del plugin
 */
function gdrive_pdf_viewer_deactivate() {
    // Limpiar caché
    delete_transient('gdrive_pdf_list_cache');
}
register_deactivation_hook(__FILE__, 'gdrive_pdf_viewer_deactivate');

/**
 * Registrar shortcode [gdrive_pdf_viewer]
 */
function gdrive_pdf_viewer_shortcode($atts) {
    $atts = shortcode_atts(array(
        'height' => '800px',
        'limit' => 0 // 0 = todos
    ), $atts);
    
    // Obtener PDFs de Google Drive
    $pdf_files = gdrive_get_pdf_files();
    
    if (is_wp_error($pdf_files)) {
        return '<div class="gdrive-error">Error al cargar los PDFs: ' . esc_html($pdf_files->get_error_message()) . '</div>';
    }
    
    if (empty($pdf_files)) {
        return '<div class="gdrive-empty">No se encontraron archivos PDF en la carpeta.</div>';
    }
    
    // Aplicar límite si está especificado
    if ($atts['limit'] > 0) {
        $pdf_files = array_slice($pdf_files, 0, $atts['limit']);
    }
    
    // Generar HTML
    ob_start();
    ?>
    <div class="gdrive-pdf-container">
        <?php foreach ($pdf_files as $index => $file): ?>
            <div class="gdrive-pdf-item" data-file-id="<?php echo esc_attr($file['id']); ?>">
                <div class="gdrive-pdf-header">
                    <h3 class="gdrive-pdf-title"><?php echo esc_html($file['name']); ?></h3>
                    <span class="gdrive-pdf-date"><?php echo esc_html($file['modified']); ?></span>
                </div>
                <div class="gdrive-pdf-viewer" style="height: <?php echo esc_attr($atts['height']); ?>;">
                    <iframe 
                        src="https://drive.google.com/file/d/<?php echo esc_attr($file['id']); ?>/preview" 
                        width="100%" 
                        height="100%" 
                        frameborder="0" 
                        allow="autoplay"
                        loading="lazy"
                        class="gdrive-pdf-iframe">
                    </iframe>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gdrive_pdf_viewer', 'gdrive_pdf_viewer_shortcode');

/**
 * Shortcode para botón de refrescar caché [gdrive_refresh_button]
 */
function gdrive_refresh_button_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => 'Actualizar lista de PDFs',
        'style' => 'default' // default, minimal, primary
    ), $atts);
    
    $button_class = 'gdrive-refresh-btn';
    if ($atts['style'] === 'minimal') {
        $button_class .= ' gdrive-refresh-btn-minimal';
    } elseif ($atts['style'] === 'primary') {
        $button_class .= ' gdrive-refresh-btn-primary';
    }
    
    ob_start();
    ?>
    <div class="gdrive-refresh-container">
        <button type="button" class="<?php echo esc_attr($button_class); ?>" id="gdrive-refresh-cache">
            <span class="gdrive-refresh-icon">🔄</span>
            <span class="gdrive-refresh-text"><?php echo esc_html($atts['text']); ?></span>
        </button>
        <span class="gdrive-refresh-message"></span>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gdrive_refresh_button', 'gdrive_refresh_button_shortcode');

/**
 * AJAX handler para refrescar caché desde el frontend
 */
function gdrive_ajax_refresh_cache_frontend() {
    check_ajax_referer('gdrive_refresh_frontend', 'nonce');
    
    gdrive_clear_cache();
    $files = gdrive_get_pdf_files(true);
    
    if (is_wp_error($files)) {
        wp_send_json_error(array(
            'message' => 'Error al actualizar: ' . $files->get_error_message()
        ));
    }
    
    wp_send_json_success(array(
        'message' => 'Lista actualizada correctamente',
        'files_count' => count($files)
    ));
}
add_action('wp_ajax_gdrive_refresh_frontend', 'gdrive_ajax_refresh_cache_frontend');
add_action('wp_ajax_nopriv_gdrive_refresh_frontend', 'gdrive_ajax_refresh_cache_frontend');

/**
 * Encolar estilos del plugin
 */
function gdrive_pdf_viewer_enqueue_styles() {
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'gdrive_pdf_viewer') || has_shortcode($post->post_content, 'gdrive_refresh_button'))) {
        wp_enqueue_style(
            'gdrive-pdf-viewer-style',
            GDRIVE_PDF_VIEWER_PLUGIN_URL . 'assets/css/viewer-style.css',
            array(),
            GDRIVE_PDF_VIEWER_VERSION
        );
    }
}
add_action('wp_enqueue_scripts', 'gdrive_pdf_viewer_enqueue_styles');

/**
 * Encolar scripts del plugin
 */
function gdrive_pdf_viewer_enqueue_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'gdrive_pdf_viewer') || has_shortcode($post->post_content, 'gdrive_refresh_button'))) {
        wp_enqueue_script(
            'gdrive-pdf-viewer-script',
            GDRIVE_PDF_VIEWER_PLUGIN_URL . 'assets/js/viewer-script.js',
            array('jquery'),
            GDRIVE_PDF_VIEWER_VERSION,
            true
        );
        
        // Pasar datos AJAX al script
        wp_localize_script('gdrive-pdf-viewer-script', 'gdriveAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdrive_refresh_frontend')
        ));
    }
}
add_action('wp_enqueue_scripts', 'gdrive_pdf_viewer_enqueue_scripts');

/**
 * Añadir enlace de configuración en la página de plugins
 */
function gdrive_pdf_viewer_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=gdrive-pdf-viewer-settings">Configuración</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'gdrive_pdf_viewer_settings_link');