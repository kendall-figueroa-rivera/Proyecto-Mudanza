/**
 * Archivo: cliente.js
 * Modulo: Cliente
 * Responsable: [nombre del integrante]
 * Descripcion: Validaciones de los formularios del modulo cliente y del
 *              contacto (lado cliente) y pequenas interacciones de la interfaz.
 *              La validacion definitiva siempre ocurre tambien en el servidor
 *              (ClienteController.php).
 */

$(function () {

    // Valida que el correo tenga un formato basico correcto
    function correoValido(valor) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor);
    }

    // Marca un campo como invalido o valido; retorna true si es valido
    function marcarCampo($campo, esValido) {
        $campo.toggleClass('is-invalid', !esValido);
        return esValido;
    }

    // Retorna la fecha de hoy en formato AAAA-MM-DD segun la hora local
    function fechaHoy() {
        const hoy = new Date();
        const mes = String(hoy.getMonth() + 1).padStart(2, '0');
        const dia = String(hoy.getDate()).padStart(2, '0');
        return hoy.getFullYear() + '-' + mes + '-' + dia;
    }

    // Valida el formulario de proforma: empresa, origen y destino obligatorios
    $('#formProforma').on('submit', function (e) {
        let valido = true;
        valido = marcarCampo($('#id_empresa'), $('#id_empresa').val() !== '') && valido;
        valido = marcarCampo($('#zona_origen'), $('#zona_origen').val() !== '') && valido;
        valido = marcarCampo($('#zona_destino'), $('#zona_destino').val() !== '') && valido;
        if (!valido) e.preventDefault();
    });

    // Valida la busqueda por ruta: ambas zonas son obligatorias
    $('#formRuta').on('submit', function (e) {
        let valido = true;
        valido = marcarCampo($('#origen'), $('#origen').val() !== '') && valido;
        valido = marcarCampo($('#destino'), $('#destino').val() !== '') && valido;
        if (!valido) e.preventDefault();
    });

    // Valida el formulario de solicitud: direcciones y fecha no anterior a hoy
    $('#fecha_mudanza').attr('min', fechaHoy());
    $('#formSolicitud').on('submit', function (e) {
        let valido = true;
        const fecha = $('#fecha_mudanza').val();
        valido = marcarCampo($('#direccion_origen'), $.trim($('#direccion_origen').val()) !== '') && valido;
        valido = marcarCampo($('#direccion_destino'), $.trim($('#direccion_destino').val()) !== '') && valido;
        valido = marcarCampo($('#fecha_mudanza'), fecha !== '' && fecha >= fechaHoy()) && valido;
        if (!valido) e.preventDefault();
    });

    // Pide confirmacion antes de cancelar una solicitud
    $('.form-cancelar').on('submit', function (e) {
        const numero = $(this).data('solicitud');
        if (!confirm('¿Cancelar la solicitud #' + numero + '? La empresa recibirá un aviso y no se puede deshacer.')) {
            e.preventDefault();
        }
    });

    // Muestra el texto de la calificacion elegida junto a las estrellas
    const textosEstrellas = {
        1: 'Muy mala', 2: 'Mala', 3: 'Regular', 4: 'Buena', 5: 'Excelente'
    };
    $('.selector-estrellas input').on('change', function () {
        $('.selector-estrellas').removeClass('is-invalid');
        $('#errorEstrellas').addClass('d-none');
        $('#textoEstrellas').text(textosEstrellas[$(this).val()] || '');
    });

    // Valida la resena: calificacion obligatoria y comentario de maximo 500 caracteres
    $('#formResena').on('submit', function (e) {
        let valido = true;
        if ($('.selector-estrellas input:checked').length === 0) {
            $('.selector-estrellas').addClass('is-invalid');
            $('#errorEstrellas').removeClass('d-none');
            valido = false;
        }
        valido = marcarCampo($('#comentario'), $('#comentario').val().length <= 500) && valido;
        if (!valido) e.preventDefault();
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

    // Valida el formulario de contacto a soporte
    $('#formContacto').on('submit', function (e) {
        let valido = true;
        const mensaje = $.trim($('#mensaje').val());
        valido = marcarCampo($('#nombre'), $.trim($('#nombre').val()) !== '') && valido;
        valido = marcarCampo($('#correo'), correoValido($('#correo').val())) && valido;
        valido = marcarCampo($('#asunto'), $.trim($('#asunto').val()) !== '') && valido;
        valido = marcarCampo($('#mensaje'), mensaje.length >= 10) && valido;
        if (!valido) e.preventDefault();
    });

    // Quita la marca de error en cuanto el usuario corrige el campo
    $('form').on('input change', '.is-invalid', function () {
        $(this).removeClass('is-invalid');
    });

});
