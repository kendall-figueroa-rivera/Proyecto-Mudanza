-- Archivo: procedures.sql
-- Modulo: Base de datos
-- Responsable: Daniel Gómez Mora
-- Descripcion: Procedimientos almacenados obligatorios
--              segun requisito tecnico del curso.


USE plataforma_mudanzas;
GO


CREATE OR ALTER PROCEDURE sp_registrar_usuario
    @id_rol        INT,
    @nombre        VARCHAR(100),
    @correo        VARCHAR(150),
    @password_hash VARCHAR(255),
    @telefono      VARCHAR(20) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM roles WHERE id_rol = @id_rol)
    BEGIN
        THROW 50001, N'El rol indicado no existe.', 1;
    END

    IF EXISTS (SELECT 1 FROM usuarios WHERE correo = @correo)
    BEGIN
        THROW 50002, N'El correo ya está registrado.', 1;
    END

    INSERT INTO usuarios (id_rol, nombre, correo, password_hash, telefono)
    VALUES (@id_rol, @nombre, @correo, @password_hash, @telefono);

    SELECT SCOPE_IDENTITY() AS id_usuario;
END
GO


CREATE OR ALTER PROCEDURE sp_generar_proforma
    @id_cliente      INT,
    @id_empresa      INT,
    @id_zona_origen  INT,
    @id_zona_destino INT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @id_tarifa INT,
            @distancia_km DECIMAL(8,2),
            @precio_base DECIMAL(10,2),
            @precio_km DECIMAL(10,2),
            @monto DECIMAL(12,2);

    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = @id_cliente AND activo = 1)
    BEGIN
        THROW 50011, N'El cliente no existe o está inactivo.', 1;
    END

    IF NOT EXISTS (SELECT 1 FROM empresa_zona
                   WHERE id_empresa = @id_empresa AND id_zona = @id_zona_origen)
    BEGIN
        THROW 50012, N'La empresa no tiene cobertura en la zona de origen.', 1;
    END

    IF NOT EXISTS (SELECT 1 FROM empresa_zona
                   WHERE id_empresa = @id_empresa AND id_zona = @id_zona_destino)
    BEGIN
        THROW 50014, N'La empresa no tiene cobertura en la zona de destino.', 1;
    END

    SELECT @id_tarifa    = id_tarifa,
           @distancia_km = distancia_km,
           @precio_base  = precio_base,
           @precio_km    = precio_por_km
    FROM tarifas
    WHERE id_empresa = @id_empresa
      AND id_zona_origen = @id_zona_origen
      AND id_zona_destino = @id_zona_destino
      AND activo = 1;

    IF @id_tarifa IS NULL
    BEGIN
        THROW 50013, N'No hay una tarifa activa para esa ruta.', 1;
    END

    SET @monto = @precio_base + (@precio_km * @distancia_km);

    INSERT INTO proformas (id_cliente, id_empresa, id_tarifa, id_zona_origen,
                           id_zona_destino, distancia_km, monto_total)
    VALUES (@id_cliente, @id_empresa, @id_tarifa, @id_zona_origen,
            @id_zona_destino, @distancia_km, @monto);

    SELECT SCOPE_IDENTITY() AS id_proforma,
           @distancia_km    AS distancia_km,
           @monto           AS monto_total;
END
GO


CREATE OR ALTER PROCEDURE sp_crear_solicitud
    @id_cliente        INT,
    @id_proforma       INT,
    @direccion_origen  VARCHAR(255),
    @direccion_destino VARCHAR(255),
    @fecha_mudanza     DATE,
    @descripcion       VARCHAR(500) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @id_usuario_empresa INT,
            @id_solicitud INT;

    IF @fecha_mudanza < CAST(GETDATE() AS DATE)
    BEGIN
        THROW 50020, N'La fecha de mudanza no puede ser anterior a hoy.', 1;
    END

    SELECT @id_usuario_empresa = e.id_usuario
    FROM proformas p
    INNER JOIN empresas e ON e.id_empresa = p.id_empresa
    WHERE p.id_proforma = @id_proforma
      AND p.id_cliente = @id_cliente
      AND p.estado = 'vigente';

    IF @id_usuario_empresa IS NULL
    BEGIN
        THROW 50021, N'La proforma no existe, no es del cliente o ya no está vigente.', 1;
    END

    BEGIN TRY
        BEGIN TRANSACTION;

        INSERT INTO solicitudes (id_proforma, id_cliente, direccion_origen,
                                 direccion_destino, fecha_mudanza, descripcion)
        VALUES (@id_proforma, @id_cliente, @direccion_origen,
                @direccion_destino, @fecha_mudanza, @descripcion);

        SET @id_solicitud = SCOPE_IDENTITY();

        UPDATE proformas SET estado = 'aceptada' WHERE id_proforma = @id_proforma;

        INSERT INTO notificaciones (id_usuario, mensaje)
        VALUES (@id_usuario_empresa,
                CONCAT(N'Nueva solicitud de mudanza #', @id_solicitud, N' pendiente de respuesta.'));

        COMMIT TRANSACTION;

        SELECT @id_solicitud AS id_solicitud;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO


CREATE OR ALTER PROCEDURE sp_responder_solicitud
    @id_solicitud INT,
    @id_empresa   INT,
    @respuesta    VARCHAR(10),          -- 'aceptada' o 'rechazada'
    @motivo       VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @id_cliente INT;

    IF @respuesta NOT IN ('aceptada', 'rechazada')
    BEGIN
        THROW 50030, N'La respuesta debe ser aceptada o rechazada.', 1;
    END

    SELECT @id_cliente = s.id_cliente
    FROM solicitudes s
    INNER JOIN proformas p ON p.id_proforma = s.id_proforma
    WHERE s.id_solicitud = @id_solicitud
      AND p.id_empresa = @id_empresa
      AND s.estado = 'pendiente';

    IF @id_cliente IS NULL
    BEGIN
        THROW 50031, N'La solicitud no existe, no pertenece a la empresa o ya fue respondida.', 1;
    END

    BEGIN TRY
        BEGIN TRANSACTION;

        UPDATE solicitudes
        SET estado = @respuesta,
            motivo_respuesta = @motivo,
            fecha_respuesta = GETDATE()
        WHERE id_solicitud = @id_solicitud;

        INSERT INTO notificaciones (id_usuario, mensaje)
        VALUES (@id_cliente,
                CONCAT(N'Tu solicitud #', @id_solicitud, N' fue ', @respuesta, N'.'));

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO


CREATE OR ALTER PROCEDURE sp_calcular_calificacion_promedio
    @id_empresa INT
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM empresas WHERE id_empresa = @id_empresa)
    BEGIN
        THROW 50040, N'La empresa no existe.', 1;
    END

    SELECT @id_empresa AS id_empresa,
           ISNULL(CAST(AVG(CAST(calificacion AS DECIMAL(4,2))) AS DECIMAL(3,2)), 0) AS promedio,
           COUNT(*) AS total_resenas
    FROM resenas
    WHERE id_empresa = @id_empresa;
END
GO