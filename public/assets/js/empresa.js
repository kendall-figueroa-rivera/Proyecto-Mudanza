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

    // Valida la ruta: origen/destino (al crear), distancia mayor que cero y precios no negativos
    $('#formTarifa').on('submit', function (e) {
        let valido = true;
        const num = (id) => parseFloat($.trim($(id).val()).replace(',', '.'));
        ['#origen', '#destino', '#provincia_origen', '#provincia_destino'].forEach(function (id) {
            if ($(id).length) {
                valido = marcarCampo($(id), $.trim($(id).val()) !== '') && valido;
            }
        });
        valido = marcarCampo($('#distancia_km'), !isNaN(num('#distancia_km')) && num('#distancia_km') > 0) && valido;
        valido = marcarCampo($('#precio_base'), !isNaN(num('#precio_base')) && num('#precio_base') >= 0) && valido;
        valido = marcarCampo($('#precio_por_km'), !isNaN(num('#precio_por_km')) && num('#precio_por_km') >= 0) && valido;
        if (!valido) e.preventDefault();
    });

    // Pide confirmacion antes de eliminar una ruta
    $('.form-eliminar-ruta').on('submit', function (e) {
        if (!confirm('Eliminar la ruta ' + $(this).data('ruta') + '?')) {
            e.preventDefault();
        }
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
