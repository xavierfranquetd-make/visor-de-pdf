<?php
/**
 * Página de configuración del administrador
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Añadir página de configuración al menú de WordPress
 */
function gdrive_pdf_viewer_add_admin_menu() {
    add_options_page(
        'Google Drive PDF Viewer',
        'GDrive PDF Viewer',
        'manage_options',
        'gdrive-pdf-viewer-settings',
        'gdrive_pdf_viewer_settings_page'
    );
}
add_action('admin_menu', 'gdrive_pdf_viewer_add_admin_menu');

/**
 * Registrar configuraciones
 */
function gdrive_pdf_viewer_settings_init() {
    register_setting('gdrive_pdf_viewer', 'gdrive_pdf_viewer_options');
    
    add_settings_section(
        'gdrive_pdf_viewer_section',
        'Configuración de Google Drive',
        'gdrive_pdf_viewer_section_callback',
        'gdrive_pdf_viewer'
    );
    
    add_settings_field(
        'api_key',
        'API Key de Google Drive',
        'gdrive_pdf_viewer_api_key_render',
        'gdrive_pdf_viewer',
        'gdrive_pdf_viewer_section'
    );
    
    add_settings_field(
        'folder_id',
        'Folder ID',
        'gdrive_pdf_viewer_folder_id_render',
        'gdrive_pdf_viewer',
        'gdrive_pdf_viewer_section'
    );
    
    add_settings_field(
        'cache_duration',
        'Duración de Caché (segundos)',
        'gdrive_pdf_viewer_cache_duration_render',
        'gdrive_pdf_viewer',
        'gdrive_pdf_viewer_section'
    );
    
    add_settings_field(
        'order_by',
        'Ordenar por',
        'gdrive_pdf_viewer_order_by_render',
        'gdrive_pdf_viewer',
        'gdrive_pdf_viewer_section'
    );
}
add_action('admin_init', 'gdrive_pdf_viewer_settings_init');

/**
 * Callbacks de campos
 */
function gdrive_pdf_viewer_api_key_render() {
    $options = get_option('gdrive_pdf_viewer_options');
    ?>
    <input type="text" name="gdrive_pdf_viewer_options[api_key]" value="<?php echo esc_attr($options['api_key'] ?? ''); ?>" class="regular-text">
    <p class="description">Tu API Key de Google Cloud Console para acceder a Google Drive API</p>
    <?php
}

function gdrive_pdf_viewer_folder_id_render() {
    $options = get_option('gdrive_pdf_viewer_options');
    ?>
    <input type="text" name="gdrive_pdf_viewer_options[folder_id]" value="<?php echo esc_attr($options['folder_id'] ?? ''); ?>" class="regular-text">
    <p class="description">El ID de la carpeta de Google Drive (la parte después de /folders/ en la URL)</p>
    <?php
}

function gdrive_pdf_viewer_cache_duration_render() {
    $options = get_option('gdrive_pdf_viewer_options');
    $duration = $options['cache_duration'] ?? 3600;
    ?>
    <input type="number" name="gdrive_pdf_viewer_options[cache_duration]" value="<?php echo esc_attr($duration); ?>" class="small-text"> segundos
    <p class="description">Tiempo de caché para la lista de archivos (por defecto: 3600 = 1 hora)</p>
    <?php
}

function gdrive_pdf_viewer_order_by_render() {
    $options = get_option('gdrive_pdf_viewer_options');
    $order = $options['order_by'] ?? 'recent';
    ?>
    <select name="gdrive_pdf_viewer_options[order_by]">
        <option value="recent" <?php selected($order, 'recent'); ?>>Más reciente primero</option>
        <option value="alphabetical" <?php selected($order, 'alphabetical'); ?>>Orden alfabético</option>
    </select>
    <?php
}

function gdrive_pdf_viewer_section_callback() {
    echo '<p>Configura las credenciales de Google Drive y las opciones de visualización.</p>';
}

/**
 * Página de configuración HTML
 */
function gdrive_pdf_viewer_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Procesar acción de limpiar caché
    if (isset($_POST['gdrive_clear_cache']) && check_admin_referer('gdrive_clear_cache_action')) {
        gdrive_clear_cache();
        echo '<div class="notice notice-success is-dismissible"><p>Caché limpiado correctamente.</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Cómo usar este plugin</h2>
            <ol>
                <li>Configura tus credenciales de Google Drive abajo</li>
                <li>Añade el shortcode <code>[gdrive_pdf_viewer]</code> en cualquier página o entrada</li>
                <li>Los PDFs se mostrarán automáticamente incrustados</li>
            </ol>
            
            <h3>Parámetros opcionales del shortcode:</h3>
            <ul>
                <li><code>[gdrive_pdf_viewer height="600px"]</code> - Cambia la altura de cada PDF</li>
                <li><code>[gdrive_pdf_viewer limit="5"]</code> - Limita el número de PDFs mostrados</li>
            </ul>
        </div>
        
        <form action="options.php" method="post">
            <?php
            settings_fields('gdrive_pdf_viewer');
            do_settings_sections('gdrive_pdf_viewer');
            submit_button();
            ?>
        </form>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Gestión de Caché</h2>
            <p>El plugin almacena en caché la lista de archivos para mejorar el rendimiento.</p>
            <form method="post">
                <?php wp_nonce_field('gdrive_clear_cache_action'); ?>
                <input type="submit" name="gdrive_clear_cache" class="button button-secondary" value="Limpiar Caché Ahora">
            </form>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Estado de la Conexión</h2>
            <?php
            $files = gdrive_get_pdf_files();
            if (is_wp_error($files)) {
                echo '<p style="color: red;">❌ Error: ' . esc_html($files->get_error_message()) . '</p>';
            } else {
                echo '<p style="color: green;">✅ Conectado correctamente. Se encontraron ' . count($files) . ' archivos PDF.</p>';
            }
            ?>
        </div>
    </div>
    <?php
}

