<?php
/**
 * Google Drive API Integration
 * Maneja la conexión y obtención de archivos desde Google Drive
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Limpiar caché de archivos PDF
 * @return bool True si se limpió correctamente
 */
function gdrive_clear_cache() {
    return delete_transient('gdrive_pdf_list_cache');
}

/**
 * Obtener archivos PDF de la carpeta de Google Drive
 * @param bool $force_refresh Forzar actualización ignorando caché
 * @return array|WP_Error Array de archivos o WP_Error en caso de fallo
 */
function gdrive_get_pdf_files($force_refresh = false) {
    // Obtener opciones del plugin
    $options = get_option('gdrive_pdf_viewer_options');
    
    if (empty($options['folder_id'])) {
        return new WP_Error('no_folder_id', 'ID de carpeta no configurado');
    }
    
    if (empty($options['api_key'])) {
        return new WP_Error('no_api_key', 'API Key no configurada');
    }
    
    // Intentar obtener desde caché si no se fuerza la actualización
    if (!$force_refresh) {
        $cached_files = get_transient('gdrive_pdf_list_cache');
        if ($cached_files !== false && is_array($cached_files)) {
            return $cached_files;
        }
    }
    
    // Construir query para obtener PDFs
    $folder_id = $options['folder_id'];
    $api_key = $options['api_key'];
    $query = urlencode("'$folder_id' in parents and mimeType='application/pdf' and trashed=false");
    
    // Determinar orden
    $order_by = 'modifiedTime desc';
    if (isset($options['order_by']) && $options['order_by'] === 'alphabetical') {
        $order_by = 'name';
    }
    
    // URL de la API de Google Drive
    $api_url = "https://www.googleapis.com/drive/v3/files?q=$query&orderBy=$order_by&fields=files(id,name,modifiedTime,webViewLink,webContentLink)&key=" . $api_key;
    
    // Realizar petición a la API
    $response = wp_remote_get($api_url, array(
        'timeout' => 30,
        'headers' => array(
            'Accept' => 'application/json'
        )
    ));
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($status_code !== 200) {
        $error_data = json_decode($body, true);
        $error_message = 'Error desconocido';
        if (isset($error_data['error']['message'])) {
            $error_message = $error_data['error']['message'];
        }
        return new WP_Error('api_error', 'Error en la API de Google Drive (' . $status_code . '): ' . $error_message);
    }
    
    $data = json_decode($body, true);
    
    if (!isset($data['files'])) {
        return new WP_Error('invalid_response', 'Respuesta inválida de Google Drive API');
    }
    
    // Formatear archivos
    $files = array();
    foreach ($data['files'] as $file) {
        $link = '';
        if (isset($file['webViewLink'])) {
            $link = $file['webViewLink'];
        }
        
        $files[] = array(
            'id' => $file['id'],
            'name' => $file['name'],
            'modified' => date_i18n(get_option('date_format'), strtotime($file['modifiedTime'])),
            'link' => $link
        );
    }
    
    // Guardar en caché
    $cache_duration = 3600;
    if (isset($options['cache_duration'])) {
        $cache_duration = intval($options['cache_duration']);
    }
    
    set_transient('gdrive_pdf_list_cache', $files, $cache_duration);
    
    return $files;
}