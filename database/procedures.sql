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

    IF NOT EXISTS (SELECT 1 FROM empresas WHERE id_empresa = @id_empresa AND estado = 'activa')
    BEGIN
        THROW 50015, N'La empresa no está activa o aún no ha sido aprobada.', 1;
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


CREATE OR ALTER PROCEDURE sp_revisar_empresa
    @id_empresa INT,
    @id_admin   INT,
    @decision   VARCHAR(10),          -- 'activa' o 'rechazada'
    @motivo     VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @id_usuario_empresa INT;

    IF @decision NOT IN ('activa', 'rechazada')
    BEGIN
        THROW 50050, N'La decisión debe ser activa o rechazada.', 1;
    END

    IF NOT EXISTS (SELECT 1 FROM usuarios u
                   INNER JOIN roles r ON r.id_rol = u.id_rol
                   WHERE u.id_usuario = @id_admin
                     AND r.nombre = 'administrador' AND u.activo = 1)
    BEGIN
        THROW 50051, N'Solo un administrador activo puede revisar empresas.', 1;
    END

    IF @decision = 'rechazada' AND (@motivo IS NULL OR LTRIM(RTRIM(@motivo)) = '')
    BEGIN
        THROW 50052, N'Debe indicar el motivo del rechazo.', 1;
    END

    SELECT @id_usuario_empresa = id_usuario
    FROM empresas
    WHERE id_empresa = @id_empresa AND estado = 'pendiente';

    IF @id_usuario_empresa IS NULL
    BEGIN
        THROW 50053, N'La empresa no existe o ya fue revisada.', 1;
    END

    BEGIN TRY
        BEGIN TRANSACTION;

        UPDATE empresas
        SET estado = @decision,
            id_admin_revision = @id_admin,
            fecha_revision = GETDATE(),
            motivo_rechazo = CASE WHEN @decision = 'rechazada' THEN @motivo ELSE NULL END
        WHERE id_empresa = @id_empresa;

        INSERT INTO notificaciones (id_usuario, mensaje)
        VALUES (@id_usuario_empresa,
                CASE WHEN @decision = 'activa'
                     THEN N'Tu empresa fue aprobada. Ya puedes recibir solicitudes.'
                     ELSE CONCAT(N'Tu empresa fue rechazada. Motivo: ', @motivo) END);

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO


-- Pasa una solicitud aceptada a completada y notifica al cliente
CREATE OR ALTER PROCEDURE sp_completar_solicitud
    @id_solicitud INT,
    @id_empresa   INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @id_cliente INT,
            @estado     VARCHAR(15);

    SELECT @id_cliente = s.id_cliente,
           @estado     = s.estado
    FROM solicitudes s
    INNER JOIN proformas p ON p.id_proforma = s.id_proforma
    WHERE s.id_solicitud = @id_solicitud
      AND p.id_empresa = @id_empresa;

    IF @id_cliente IS NULL
    BEGIN
        THROW 50032, N'La solicitud no existe o no pertenece a la empresa.', 1;
    END

    IF @estado <> 'aceptada'
    BEGIN
        THROW 50033, N'Solo se puede completar una solicitud que esté aceptada.', 1;
    END

    BEGIN TRY
        BEGIN TRANSACTION;

        UPDATE solicitudes
        SET estado = 'completada'
        WHERE id_solicitud = @id_solicitud;

        INSERT INTO notificaciones (id_usuario, mensaje)
        VALUES (@id_cliente,
                CONCAT(N'Tu solicitud #', @id_solicitud, N' fue completada. Ya puedes calificarla.'));

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO


-- Activa o desactiva un usuario solo lo puede hacer un administrador activo
CREATE OR ALTER PROCEDURE sp_cambiar_estado_usuario
    @id_usuario INT,
    @id_admin   INT,
    @activo     BIT
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM usuarios u
                   INNER JOIN roles r ON r.id_rol = u.id_rol
                   WHERE u.id_usuario = @id_admin
                     AND r.nombre = 'administrador' AND u.activo = 1)
    BEGIN
        THROW 50060, N'Solo un administrador activo puede cambiar el estado de un usuario.', 1;
    END

    IF @id_usuario = @id_admin
    BEGIN
        THROW 50061, N'Un administrador no puede desactivar su propia cuenta.', 1;
    END

    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = @id_usuario)
    BEGIN
        THROW 50062, N'El usuario no existe.', 1;
    END

    UPDATE usuarios
    SET activo = @activo
    WHERE id_usuario = @id_usuario;
END
GO


-- Oculta o muestra una reseña solo lo puede hacer un administrador activo
CREATE OR ALTER PROCEDURE sp_moderar_resena
    @id_resena INT,
    @id_admin  INT,
    @estado    VARCHAR(10)          -- 'visible' u 'oculta'
AS
BEGIN
    SET NOCOUNT ON;

    IF @estado NOT IN ('visible', 'oculta')
    BEGIN
        THROW 50063, N'El estado debe ser visible u oculta.', 1;
    END

    IF NOT EXISTS (SELECT 1 FROM usuarios u
                   INNER JOIN roles r ON r.id_rol = u.id_rol
                   WHERE u.id_usuario = @id_admin
                     AND r.nombre = 'administrador' AND u.activo = 1)
    BEGIN
        THROW 50064, N'Solo un administrador activo puede moderar reseñas.', 1;
    END

    IF NOT EXISTS (SELECT 1 FROM resenas WHERE id_resena = @id_resena)
    BEGIN
        THROW 50065, N'La reseña no existe.', 1;
    END

    UPDATE resenas
    SET estado = @estado
    WHERE id_resena = @id_resena;
END
GO


-- Guarda un token de recuperacion si el correo existe y devuelve nombre y correo
-- Si el correo no existe no devuelve filas ni error (no revela correos registrados)
CREATE OR ALTER PROCEDURE sp_crear_token_recuperacion
    @correo  VARCHAR(150),
    @token   VARCHAR(255),
    @minutos INT = 30
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @id_usuario INT,
            @nombre     VARCHAR(100);

    SELECT @id_usuario = id_usuario,
           @nombre     = nombre
    FROM usuarios
    WHERE correo = @correo AND activo = 1;

    IF @id_usuario IS NULL
    BEGIN
        RETURN;
    END

    BEGIN TRY
        BEGIN TRANSACTION;

        -- Invalida los enlaces anteriores sin usar de ese usuario
        UPDATE token_recuperacion
        SET usado = 1
        WHERE id_usuario = @id_usuario AND usado = 0;

        INSERT INTO token_recuperacion (id_usuario, token, fecha_expiracion)
        VALUES (@id_usuario, @token, DATEADD(MINUTE, @minutos, GETDATE()));

        COMMIT TRANSACTION;

        SELECT @nombre AS nombre, @correo AS correo;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO


-- Cambia la contraseña con un token valido y marca el token como usado
CREATE OR ALTER PROCEDURE sp_restablecer_contrasena
    @token         VARCHAR(255),
    @password_hash VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @id_token   INT,
            @id_usuario INT,
            @expira     DATETIME,
            @usado      BIT;

    SELECT @id_token   = id_token,
           @id_usuario = id_usuario,
           @expira     = fecha_expiracion,
           @usado      = usado
    FROM token_recuperacion
    WHERE token = @token;

    IF @id_token IS NULL
    BEGIN
        THROW 50070, N'El enlace de recuperación no es válido.', 1;
    END

    IF @usado = 1
    BEGIN
        THROW 50071, N'El enlace de recuperación ya fue utilizado.', 1;
    END

    IF @expira < GETDATE()
    BEGIN
        THROW 50072, N'El enlace de recuperación venció.', 1;
    END

    BEGIN TRY
        BEGIN TRANSACTION;

        UPDATE usuarios
        SET password_hash = @password_hash
        WHERE id_usuario = @id_usuario;

        UPDATE token_recuperacion
        SET usado = 1
        WHERE id_token = @id_token;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO
