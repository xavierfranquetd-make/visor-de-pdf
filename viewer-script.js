/**
 * Google Drive PDF Viewer - JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Detectar cuando un iframe está completamente cargado
        $('.gdrive-pdf-iframe').on('load', function() {
            $(this).closest('.gdrive-pdf-viewer').addClass('loaded');
        });
        
        // Manejar errores de carga
        $('.gdrive-pdf-iframe').on('error', function() {
            var $viewer = $(this).closest('.gdrive-pdf-viewer');
            $viewer.html('<div class="gdrive-error">Error al cargar el PDF. Por favor, verifica que el archivo sea accesible.</div>');
        });
        
        // Lazy loading mejorado - cargar iframes cuando estén cerca del viewport
        if ('IntersectionObserver' in window) {
            var lazyLoadObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var $iframe = $(entry.target).find('iframe');
                        if ($iframe.attr('data-src')) {
                            $iframe.attr('src', $iframe.attr('data-src'));
                            $iframe.removeAttr('data-src');
                        }
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                rootMargin: '200px'
            });
            
            $('.gdrive-pdf-item').each(function() {
                lazyLoadObserver.observe(this);
            });
        }
        
        // Agregar contador de página si hay múltiples PDFs
        var $pdfItems = $('.gdrive-pdf-item');
        if ($pdfItems.length > 1) {
            $pdfItems.each(function(index) {
                var counter = '<span class="gdrive-pdf-counter">' + (index + 1) + ' de ' + $pdfItems.length + '</span>';
                $(this).find('.gdrive-pdf-header').append(counter);
            });
        }
        
        // Smooth scroll cuando se hace clic en un PDF
        $('.gdrive-pdf-item').on('click', '.gdrive-pdf-title', function() {
            var $viewer = $(this).closest('.gdrive-pdf-item').find('.gdrive-pdf-viewer');
            $('html, body').animate({
                scrollTop: $viewer.offset().top - 100
            }, 500);
        });

        
        // Manejar botón de refrescar caché
        $('#gdrive-refresh-cache').on('click', function() {
            var $button = $(this);
            var $message = $('.gdrive-refresh-message');
            
            // Prevenir múltiples clics
            if ($button.hasClass('loading')) {
                return;
            }
            
            // Añadir estado de carga
            $button.addClass('loading').prop('disabled', true);
            $message.removeClass('success error').text('Actualizando...');
            
            // Realizar petición AJAX
            $.ajax({
                url: gdriveAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gdrive_refresh_frontend',
                    nonce: gdriveAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $message.addClass('success').text(response.data.message + ' (' + response.data.files_count + ' PDFs encontrados)');
                        
                        // Recargar página después de 1 segundo para mostrar nuevos PDFs
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $message.addClass('error').text(response.data.message || 'Error al actualizar');
                        $button.removeClass('loading').prop('disabled', false);
                    }
                },
                error: function() {
                    $message.addClass('error').text('Error de conexión. Inténtalo de nuevo.');
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        });


        
        console.log('Google Drive PDF Viewer inicializado correctamente');
    });
    
})(jQuery);
