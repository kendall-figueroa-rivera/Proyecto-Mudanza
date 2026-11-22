/**
 * Archivo: mi_cuenta.js
 * Modulo: Frontend - Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Validaciones de la pagina "Mi cuenta" (lado cliente, jQuery).
 *              La validacion definitiva siempre ocurre tambien en el servidor
 *              (AuthController.php); esto solo da respuesta inmediata.
 */

$(function () {

    // El telefono es opcional; si se escribe debe tener numeros, espacios, + o guiones (8 a 20)
    function telefonoValido(valor) {
        return valor === '' || /^[0-9+\-\s]{8,20}$/.test(valor);
    }

    // Valida el formulario de datos personales antes de enviarlo
    $('#formPerfil').on('submit', function (e) {
        let valido = true;
        const $nombre = $('#nombre');
        const $telefono = $('#telefono');

        $nombre.removeClass('is-invalid');
        $telefono.removeClass('is-invalid');

        if ($.trim($nombre.val()) === '') {
            $nombre.addClass('is-invalid');
            valido = false;
        }
        if (!telefonoValido($.trim($telefono.val()))) {
            $telefono.addClass('is-invalid');
            valido = false;
        }
        if (!valido) e.preventDefault();
    });

    // Valida el formulario de cambio de contrasena antes de enviarlo
    $('#formContrasena').on('submit', function (e) {
        let valido = true;
        const $actual = $('#password_actual');
        const $nueva = $('#password_nueva');
        const $confirmar = $('#password_confirmar');
        const $mensajeNueva = $nueva.siblings('.invalid-feedback');

        $actual.removeClass('is-invalid');
        $nueva.removeClass('is-invalid');
        $confirmar.removeClass('is-invalid');

        if ($actual.val() === '') {
            $actual.addClass('is-invalid');
            valido = false;
        }
        if ($nueva.val().length < 8) {
            $mensajeNueva.text('La contraseña nueva debe tener al menos 8 caracteres.');
            $nueva.addClass('is-invalid');
            valido = false;
        } else if ($nueva.val() === $actual.val()) {
            $mensajeNueva.text('La contraseña nueva debe ser distinta de la actual.');
            $nueva.addClass('is-invalid');
            valido = false;
        }
        if ($nueva.val() !== $confirmar.val()) {
            $confirmar.addClass('is-invalid');
            valido = false;
        }
        if (!valido) e.preventDefault();
    });

});
