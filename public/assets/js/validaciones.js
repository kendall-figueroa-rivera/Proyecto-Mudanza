/**
 * Archivo: validaciones.js
 * Modulo: Frontend - Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Validaciones de los formularios de login y registro (lado cliente).
 *              La validacion definitiva siempre ocurre tambien en el servidor
 *              (AuthController.php), esto solo da feedback inmediato al usuario.
 */

$(function () {

    // Valida que el correo tenga un formato basico correcto
    function correoValido(valor) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor);
    }

    // Muestra u oculta los campos extra de empresa segun el tipo de cuenta elegido
    $('#tipo_cuenta').on('change', function () {
        if ($(this).val() === 'empresa') {
            $('#camposEmpresa').slideDown();
            $('#camposEmpresa input').prop('required', true);
        } else {
            $('#camposEmpresa').slideUp();
            $('#camposEmpresa input').prop('required', false);
        }
    });

    // Valida el formulario de login antes de enviarlo
    $('#formLogin').on('submit', function (e) {
        let valido = true;
        const $correo = $('#correo');
        const $password = $('#password');

        $correo.removeClass('is-invalid');
        $password.removeClass('is-invalid');

        if (!correoValido($correo.val())) {
            $correo.addClass('is-invalid');
            valido = false;
        }
        if ($password.val().length < 8) {
            $password.addClass('is-invalid');
            valido = false;
        }
        if (!valido) e.preventDefault();
    });

    // Valida el formulario de registro antes de enviarlo
    $('#formRegistro').on('submit', function (e) {
        let valido = true;
        const $correo = $('#correo');
        const $password = $('#password');
        const $confirm = $('#password_confirm');

        $correo.removeClass('is-invalid');
        $password.removeClass('is-invalid');
        $confirm.removeClass('is-invalid');

        if (!correoValido($correo.val())) {
            $correo.addClass('is-invalid');
            valido = false;
        }
        if ($password.val().length < 8) {
            $password.addClass('is-invalid');
            valido = false;
        }
        if ($password.val() !== $confirm.val()) {
            $confirm.addClass('is-invalid');
            valido = false;
        }
        if (!valido) e.preventDefault();
    });

});
