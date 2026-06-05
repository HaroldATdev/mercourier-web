<?php
/**
 * Plugin Name: Bloquear Correos Masivos
 * Description: Bloquea correos lentos de asignación para que los contenedores carguen rápido.
 */

add_filter( 'pre_wp_mail', function( $return, $args ) {
    $subject = isset($args['subject']) ? strtolower($args['subject']) : '';
    
    // Palabras clave de seguridad (se permiten)
    $allow_words = array(
        'contraseña', 'password', 'restablecer', 'reset', 'usuario', 'user', 'admin'
    );
    
    foreach ( $allow_words as $word ) {
        if ( strpos( $subject, $word ) !== false ) {
            return null; // Permitir el envío del correo crucial
        }
    }
    
    // Si no es crucial, interceptamos y engañamos al plugin devolviendo 'true' (simulando envío exitoso)
    // Esto evita que WordPress se congele intentando conectar al servidor de correos.
    return true; 
}, 99, 2 );
