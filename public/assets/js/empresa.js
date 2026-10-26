/**
 * Archivo: empresa.js
 * Modulo: Empresa
 * Responsable: [Santiago Correa Fallas]
 * Descripcion: Validaciones de los formularios de perfil y flota (lado
 *              cliente). La validacion definitiva ocurre tambien en el
 *              servidor (EmpresaController.php).
 */

$(function () {

    // Marca un campo como invalido o valido; retorna true si es valido
    function marcarCampo($campo, esValido) {
        $campo.toggleClass('is-invalid', !esValido);
        return esValido;
    }

    // Valida el perfil: nombre obligatorio y telefono con formato correcto si se escribe
    $('#formPerfil').on('submit', function (e) {
        let valido = true;
        const telefono = $.trim($('#telefono').val());
        valido = marcarCampo($('#nombre_comercial'), $.trim($('#nombre_comercial').val()) !== '') && valido;
        valido = marcarCampo($('#telefono'), telefono === '' || /^[0-9+\-\s]{8,20}$/.test(telefono)) && valido;
        if (!valido) e.preventDefault();
    });

    // Valida el vehiculo: placa, tipo y capacidad mayor que cero
    $('#formVehiculo').on('submit', function (e) {
        let valido = true;
        const capacidad = parseFloat($.trim($('#capacidad_kg').val()).replace(',', '.'));
        valido = marcarCampo($('#placa'), /^[A-Za-z0-9\-]{3,15}$/.test($.trim($('#placa').val()))) && valido;
        valido = marcarCampo($('#tipo_vehiculo'), $.trim($('#tipo_vehiculo').val()) !== '') && valido;
        valido = marcarCampo($('#capacidad_kg'), !isNaN(capacidad) && capacidad > 0) && valido;
        if (!valido) e.preventDefault();
    });

    // Pide confirmacion antes de eliminar un vehiculo
    $('.form-eliminar').on('submit', function (e) {
        if (!confirm('¿Eliminar el vehículo ' + $(this).data('placa') + ' de tu flota?')) {
            e.preventDefault();
        }
    });

    // Cuenta los caracteres usados en los campos que tienen limite
    $('[data-contador]').each(function () {
        const $campo = $(this);
        const $salida = $('#' + $campo.data('contador'));
        const maximo = parseInt($campo.attr('maxlength'), 10);
        function actualizar() {
            $salida.text($campo.val().length + ' / ' + maximo);
        }
        $campo.on('input', actualizar);
        actualizar();
    });

    // Quita la marca de error en cuanto el usuario corrige el campo
    $('form').on('input change', '.is-invalid', function () {
        $(this).removeClass('is-invalid');
    });

});
