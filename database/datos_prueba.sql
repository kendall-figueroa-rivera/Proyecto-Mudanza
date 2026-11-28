-- Archivo: datos_prueba.sql
-- Modulo: Base de datos
-- Responsable: Daniel Gómez Mora
-- Descripcion: Datos de prueba ficticios para ver la aplicacion llena:
--              usuarios de cada rol, empresas, zonas, rutas, flota,
--              proformas, solicitudes en cada estado y reseñas.
--              Todas las cuentas usan la misma contraseña (ver README).
--
-- ORDEN DE EJECUCION: schema.sql -> procedures.sql -> datos_prueba.sql
-- Se puede ejecutar varias veces: primero borra los datos de las tablas
-- de la aplicacion (NO toca la tabla roles) y luego los vuelve a cargar.


USE plataforma_mudanzas;
GO


-- ---------------------------------------------------------------
-- 0. Limpieza (orden inverso a las llaves foraneas)
-- ---------------------------------------------------------------
DELETE FROM resenas;
DELETE FROM solicitudes;
DELETE FROM proformas;
DELETE FROM tarifas;
DELETE FROM empresa_zona;
DELETE FROM flota;
DELETE FROM empresas;
DELETE FROM zonas_cobertura;
DELETE FROM notificaciones;
DELETE FROM token_recuperacion;
DELETE FROM login_auditoria;
DELETE FROM usuarios;
GO


-- ---------------------------------------------------------------
-- 1. Usuarios (1 administrador, 3 clientes activos, 1 cliente inactivo,
--    7 duenos de empresa). El hash real se pone al final (seccion 10).
-- ---------------------------------------------------------------
DECLARE @rol_admin   INT = (SELECT id_rol FROM roles WHERE nombre = 'administrador');
DECLARE @rol_cliente INT = (SELECT id_rol FROM roles WHERE nombre = 'cliente');
DECLARE @rol_empresa INT = (SELECT id_rol FROM roles WHERE nombre = 'empresa');

SET IDENTITY_INSERT usuarios ON;

INSERT INTO usuarios (id_usuario, id_rol, nombre, correo, password_hash, telefono, activo, fecha_registro) VALUES
(1,  @rol_admin,   'Administrador MudaConecta', 'admin@mudaconecta.com', 'x', '2200-0000', 1, DATEADD(DAY, -90, GETDATE())),
(2,  @rol_cliente, 'Laura Jiménez Mora',        'cliente01@rutas.com',   'x', '8800-0001', 1, DATEADD(DAY, -60, GETDATE())),
(3,  @rol_cliente, 'Carlos Rojas Vargas',       'cliente02@rutas.com',   'x', '8800-0002', 1, DATEADD(DAY, -55, GETDATE())),
(4,  @rol_cliente, 'María Fernanda Solís',      'cliente03@rutas.com',   'x', '8800-0003', 1, DATEADD(DAY, -50, GETDATE())),
(5,  @rol_cliente, 'Pedro Araya Campos',        'cliente04@rutas.com',   'x', '8800-0004', 0, DATEADD(DAY, -40, GETDATE())),
(6,  @rol_empresa, 'Andrés Chaves Quesada',     'empresa01@rutas.com',   'x', '8900-0001', 1, DATEADD(DAY, -85, GETDATE())),
(7,  @rol_empresa, 'Sofía Brenes Alfaro',       'empresa02@rutas.com',   'x', '8900-0002', 1, DATEADD(DAY, -80, GETDATE())),
(8,  @rol_empresa, 'Marco Villalobos Mena',     'empresa03@rutas.com',   'x', '8900-0003', 1, DATEADD(DAY, -75, GETDATE())),
(9,  @rol_empresa, 'Daniela Cordero Ulate',     'empresa04@rutas.com',   'x', '8900-0004', 1, DATEADD(DAY, -70, GETDATE())),
(10, @rol_empresa, 'Jorge Salas Picado',        'empresa05@rutas.com',   'x', '8900-0005', 1, DATEADD(DAY, -65, GETDATE())),
(11, @rol_empresa, 'Karla Murillo Segura',      'empresa06@rutas.com',   'x', '8900-0006', 1, DATEADD(DAY, -3,  GETDATE())),
(12, @rol_empresa, 'Esteban Porras Leiva',      'empresa07@rutas.com',   'x', '8900-0007', 1, DATEADD(DAY, -45, GETDATE()));

SET IDENTITY_INSERT usuarios OFF;
GO


-- ---------------------------------------------------------------
-- 2. Zonas de cobertura
-- ---------------------------------------------------------------
SET IDENTITY_INSERT zonas_cobertura ON;

INSERT INTO zonas_cobertura (id_zona, nombre, provincia, canton) VALUES
(1, 'San José Centro',  'San José',   'San José'),
(2, 'Escazú',           'San José',   'Escazú'),
(3, 'Heredia Centro',   'Heredia',    'Heredia'),
(4, 'Alajuela Centro',  'Alajuela',   'Alajuela'),
(5, 'Cartago Centro',   'Cartago',    'Cartago'),
(6, 'Puntarenas Centro','Puntarenas', 'Puntarenas'),
(7, 'Liberia',          'Guanacaste', 'Liberia'),
(8, 'Limón Centro',     'Limón',      'Limón');

SET IDENTITY_INSERT zonas_cobertura OFF;
GO


-- ---------------------------------------------------------------
-- 3. Empresas: 5 activas, 1 pendiente (para mostrar la aprobacion)
--    y 1 suspendida.
-- ---------------------------------------------------------------
SET IDENTITY_INSERT empresas ON;

INSERT INTO empresas (id_empresa, id_usuario, nombre_comercial, identificacion, telefono, direccion, descripcion,
                      estado, fecha_registro, id_admin_revision, fecha_revision, motivo_rechazo) VALUES
(1, 6,  'Mudanzas Pura Vida',     '3-101-000001', '2222-0001', 'San José, Barrio Escalante',
        'Mudanzas residenciales y de oficina en el Valle Central, con personal capacitado y seguro de carga.',
        'activa', DATEADD(DAY, -85, GETDATE()), 1, DATEADD(DAY, -84, GETDATE()), NULL),
(2, 7,  'Transportes El Sol',     '3-101-000002', '2222-0002', 'Heredia, San Francisco',
        'Traslados nacionales con camiones de distintos tamaños. Servicio a Puntarenas y zonas costeras.',
        'activa', DATEADD(DAY, -80, GETDATE()), 1, DATEADD(DAY, -79, GETDATE()), NULL),
(3, 8,  'Fletes Rápidos CR',      '3-101-000003', '2222-0003', 'Cartago, Centro',
        'Fletes y mudanzas rápidas hacia Cartago y Limón. Disponibilidad de fin de semana.',
        'activa', DATEADD(DAY, -75, GETDATE()), 1, DATEADD(DAY, -74, GETDATE()), NULL),
(4, 9,  'Mudanzas Volcán',        '3-101-000004', '2222-0004', 'Alajuela, Centro',
        'Especialistas en mudanzas de larga distancia hacia Guanacaste y el Pacífico.',
        'activa', DATEADD(DAY, -70, GETDATE()), 1, DATEADD(DAY, -69, GETDATE()), NULL),
(5, 10, 'Carga Segura',           '3-101-000005', '2222-0005', 'Escazú, San Rafael',
        'Mudanzas cuidadosas con embalaje incluido para hogares y pequeños negocios.',
        'activa', DATEADD(DAY, -65, GETDATE()), 1, DATEADD(DAY, -64, GETDATE()), NULL),
(6, 11, 'Mudanzas Nuevo Rumbo',   '3-101-000006', '2222-0006', 'San José, Pavas',
        'Empresa nueva que ofrece mudanzas dentro del Gran Área Metropolitana.',
        'pendiente', DATEADD(DAY, -3, GETDATE()), NULL, NULL, NULL),
(7, 12, 'Fletes del Pacífico',    '3-101-000007', '2222-0007', 'Alajuela, Grecia',
        'Fletes hacia el Valle Central. Cuenta suspendida temporalmente por revisión.',
        'suspendida', DATEADD(DAY, -45, GETDATE()), 1, DATEADD(DAY, -44, GETDATE()), NULL);

SET IDENTITY_INSERT empresas OFF;
GO


-- ---------------------------------------------------------------
-- 4. Zonas que cubre cada empresa
-- ---------------------------------------------------------------
INSERT INTO empresa_zona (id_empresa, id_zona) VALUES
(1, 1), (1, 2), (1, 3), (1, 4),
(2, 1), (2, 3), (2, 4), (2, 6),
(3, 1), (3, 5), (3, 8),
(4, 4), (4, 6), (4, 7),
(5, 1), (5, 2), (5, 5),
(6, 1), (6, 3),
(7, 1), (7, 4);
GO


-- ---------------------------------------------------------------
-- 5. Tarifas (rutas). Una queda inactiva (id 6) para mostrar el filtro.
-- ---------------------------------------------------------------
SET IDENTITY_INSERT tarifas ON;

INSERT INTO tarifas (id_tarifa, id_empresa, id_zona_origen, id_zona_destino, distancia_km, precio_base, precio_por_km, activo) VALUES
(1,  1, 1, 3,  12.00,  45000.00, 1200.00, 1),
(2,  1, 1, 4,  20.00,  50000.00, 1300.00, 1),
(3,  1, 2, 1,   8.00,  40000.00, 1100.00, 1),
(4,  2, 1, 6, 110.00,  90000.00, 1000.00, 1),
(5,  2, 3, 4,  15.00,  42000.00, 1000.00, 1),
(6,  2, 1, 3,  12.00,  43000.00, 1150.00, 0),
(7,  3, 1, 5,  24.00,  48000.00, 1250.00, 1),
(8,  3, 1, 8, 160.00, 120000.00,  950.00, 1),
(9,  4, 4, 6,  95.00,  85000.00, 1050.00, 1),
(10, 4, 4, 7, 190.00, 130000.00,  900.00, 1),
(11, 5, 1, 5,  24.00,  46000.00, 1200.00, 1),
(12, 5, 2, 5,  30.00,  50000.00, 1200.00, 1),
(13, 7, 1, 4,  20.00,  48000.00, 1100.00, 1);

SET IDENTITY_INSERT tarifas OFF;
GO


-- ---------------------------------------------------------------
-- 6. Flota
-- ---------------------------------------------------------------
INSERT INTO flota (id_empresa, placa, tipo_vehiculo, capacidad_kg, estado) VALUES
(1, 'MDA-101', 'Camión pequeño', 1500.00, 'disponible'),
(1, 'MDA-102', 'Camión mediano', 4000.00, 'disponible'),
(1, 'MDA-103', 'Camión grande',  8000.00, 'en_servicio'),
(2, 'MDB-201', 'Pickup',          800.00, 'disponible'),
(2, 'MDB-202', 'Camión mediano', 4500.00, 'disponible'),
(2, 'MDB-203', 'Camión grande',  9000.00, 'mantenimiento'),
(3, 'MDC-301', 'Camión pequeño', 2000.00, 'disponible'),
(3, 'MDC-302', 'Camión mediano', 5000.00, 'en_servicio'),
(4, 'MDD-401', 'Camión mediano', 4000.00, 'disponible'),
(4, 'MDD-402', 'Camión grande', 10000.00, 'disponible'),
(5, 'MDE-501', 'Pickup',          900.00, 'disponible'),
(5, 'MDE-502', 'Camión pequeño', 1800.00, 'disponible'),
(5, 'MDE-503', 'Camión mediano', 4200.00, 'mantenimiento'),
(6, 'MDF-601', 'Camión pequeño', 1500.00, 'disponible'),
(7, 'MDG-701', 'Camión mediano', 4000.00, 'disponible');
GO


-- ---------------------------------------------------------------
-- 7. Proformas: 'aceptada' = reservada por una solicitud,
--    'vigente' = disponible para solicitar, 'vencida' = ya no sirve.
--    monto_total = precio_base + precio_por_km * distancia_km
-- ---------------------------------------------------------------
SET IDENTITY_INSERT proformas ON;

INSERT INTO proformas (id_proforma, id_cliente, id_empresa, id_tarifa, id_zona_origen, id_zona_destino,
                       distancia_km, monto_total, estado, fecha_generacion) VALUES
(1,  2, 1, 1,  1, 3,  12.00,  59400.00, 'aceptada', DATEADD(DAY, -20, GETDATE())),
(2,  3, 1, 2,  1, 4,  20.00,  76000.00, 'aceptada', DATEADD(DAY, -15, GETDATE())),
(3,  2, 2, 4,  1, 6, 110.00, 200000.00, 'aceptada', DATEADD(DAY, -12, GETDATE())),
(4,  4, 3, 7,  1, 5,  24.00,  78000.00, 'aceptada', DATEADD(DAY, -10, GETDATE())),
(5,  3, 4, 9,  4, 6,  95.00, 184750.00, 'aceptada', DATEADD(DAY, -5,  GETDATE())),
(6,  2, 5, 11, 1, 5,  24.00,  74800.00, 'aceptada', DATEADD(DAY, -8,  GETDATE())),
(7,  4, 1, 3,  2, 1,   8.00,  48800.00, 'aceptada', DATEADD(DAY, -9,  GETDATE())),
(8,  3, 2, 5,  3, 4,  15.00,  57000.00, 'vigente',  DATEADD(DAY, -1,  GETDATE())),
(9,  4, 3, 8,  1, 8, 160.00, 272000.00, 'vigente',  DATEADD(DAY, -1,  GETDATE())),
(10, 2, 4, 10, 4, 7, 190.00, 301000.00, 'vencida',  DATEADD(DAY, -60, GETDATE())),
(11, 3, 5, 12, 2, 5,  30.00,  86000.00, 'vencida',  DATEADD(DAY, -45, GETDATE())),
(12, 4, 5, 11, 1, 5,  24.00,  74800.00, 'aceptada', DATEADD(DAY, -30, GETDATE()));

SET IDENTITY_INSERT proformas OFF;
GO


-- ---------------------------------------------------------------
-- 8. Solicitudes: completada (1, 2, 3, 8), aceptada (4), pendiente (5),
--    rechazada (6) y cancelada (7).
-- ---------------------------------------------------------------
SET IDENTITY_INSERT solicitudes ON;

INSERT INTO solicitudes (id_solicitud, id_proforma, id_cliente, direccion_origen, direccion_destino, fecha_mudanza,
                         descripcion, estado, motivo_respuesta, fecha_creacion, fecha_respuesta) VALUES
(1, 1,  2, 'Barrio Escalante, 200 m norte de la iglesia', 'Heredia Centro, frente al parque',
        CAST(DATEADD(DAY, -15, GETDATE()) AS DATE), 'Apartamento de 2 habitaciones, sala, cocina y 15 cajas.',
        'completada', NULL, DATEADD(DAY, -19, GETDATE()), DATEADD(DAY, -18, GETDATE())),
(2, 2,  3, 'San Pedro, Los Yoses', 'Alajuela Centro, cerca de la catedral',
        CAST(DATEADD(DAY, -10, GETDATE()) AS DATE), 'Casa pequeña, 1 refrigerador y 1 lavadora.',
        'completada', NULL, DATEADD(DAY, -14, GETDATE()), DATEADD(DAY, -13, GETDATE())),
(3, 3,  2, 'Pavas, Rohrmoser', 'Puntarenas Centro, barrio El Cocal',
        CAST(DATEADD(DAY, -6, GETDATE()) AS DATE), 'Mudanza completa de una casa de 3 habitaciones.',
        'completada', NULL, DATEADD(DAY, -11, GETDATE()), DATEADD(DAY, -10, GETDATE())),
(4, 4,  4, 'San José, Barrio Amón', 'Cartago Centro, 100 m sur de las ruinas',
        CAST(DATEADD(DAY, 3, GETDATE()) AS DATE), 'Oficina pequeña con 6 escritorios y archivadores.',
        'aceptada', NULL, DATEADD(DAY, -9, GETDATE()), DATEADD(DAY, -8, GETDATE())),
(5, 5,  3, 'Alajuela, La Ceiba', 'Puntarenas Centro, Paseo de los Turistas',
        CAST(DATEADD(DAY, 10, GETDATE()) AS DATE), 'Casa de 2 pisos con muebles grandes y un piano vertical.',
        'pendiente', NULL, DATEADD(DAY, -4, GETDATE()), NULL),
(6, 6,  2, 'San José, Barrio México', 'Cartago, Barrio Los Ángeles',
        CAST(DATEADD(DAY, 5, GETDATE()) AS DATE), 'Apartamento amueblado de 1 habitación.',
        'rechazada', 'No hay disponibilidad de camión para esa fecha.', DATEADD(DAY, -7, GETDATE()), DATEADD(DAY, -6, GETDATE())),
(7, 7,  4, 'Escazú, Guachipelín', 'San José, Barrio Escalante',
        CAST(DATEADD(DAY, 7, GETDATE()) AS DATE), 'Traslado de muebles de sala y comedor.',
        'cancelada', 'El cliente canceló la mudanza.', DATEADD(DAY, -8, GETDATE()), NULL),
(8, 12, 4, 'San José, Hatillo', 'Cartago, Occidental',
        CAST(DATEADD(DAY, -25, GETDATE()) AS DATE), 'Mudanza de un apartamento con 20 cajas.',
        'completada', NULL, DATEADD(DAY, -29, GETDATE()), DATEADD(DAY, -28, GETDATE()));

SET IDENTITY_INSERT solicitudes OFF;
GO


-- ---------------------------------------------------------------
-- 9. Reseñas (solo de solicitudes completadas). La 4 esta oculta
--    para mostrar la moderacion del administrador.
-- ---------------------------------------------------------------
SET IDENTITY_INSERT resenas ON;

INSERT INTO resenas (id_resena, id_solicitud, id_cliente, id_empresa, calificacion, comentario, fecha, estado) VALUES
(1, 1, 2, 1, 5, 'Excelente servicio, llegaron puntuales y cuidaron todos los muebles.',        DATEADD(DAY, -14, GETDATE()), 'visible'),
(2, 2, 3, 1, 4, 'Buen trabajo en general, solo hubo un pequeño retraso en la llegada.',         DATEADD(DAY, -9,  GETDATE()), 'visible'),
(3, 3, 2, 2, 5, 'Muy profesionales, el traslado a Puntarenas salió perfecto.',                  DATEADD(DAY, -5,  GETDATE()), 'visible'),
(4, 8, 4, 5, 2, 'El servicio llegó tarde y faltó cuidado con una mesa.',                        DATEADD(DAY, -24, GETDATE()), 'oculta');

SET IDENTITY_INSERT resenas OFF;
GO


-- ---------------------------------------------------------------
-- 10. Notificaciones y auditoria de accesos (incluye accesos fallidos
--     en las ultimas 24 horas para el panel del administrador)
-- ---------------------------------------------------------------
INSERT INTO notificaciones (id_usuario, mensaje, leida, fecha) VALUES
(6,  'Nueva solicitud de mudanza #7 pendiente de respuesta.', 1, DATEADD(DAY, -8, GETDATE())),
(9,  'Nueva solicitud de mudanza #5 pendiente de respuesta.', 0, DATEADD(DAY, -4, GETDATE())),
(8,  'Tu empresa fue aprobada. Ya puedes recibir solicitudes.', 1, DATEADD(DAY, -74, GETDATE())),
(2,  'Tu solicitud #1 fue completada. Ya puedes calificarla.', 1, DATEADD(DAY, -18, GETDATE())),
(4,  'Tu solicitud #4 fue aceptada.', 0, DATEADD(DAY, -8, GETDATE())),
(2,  'Tu solicitud #6 fue rechazada.', 0, DATEADD(DAY, -6, GETDATE()));

INSERT INTO login_auditoria (id_usuario, correo_intentado, exitoso, ip_origen, fecha_hora) VALUES
(1,    'admin@mudaconecta.com', 1, '127.0.0.1', DATEADD(HOUR, -3,  GETDATE())),
(2,    'cliente01@rutas.com',   1, '127.0.0.1', DATEADD(DAY,  -1,  GETDATE())),
(3,    'cliente02@rutas.com',   1, '127.0.0.1', DATEADD(DAY,  -2,  GETDATE())),
(4,    'cliente03@rutas.com',   1, '127.0.0.1', DATEADD(DAY,  -3,  GETDATE())),
(6,    'empresa01@rutas.com',   1, '127.0.0.1', DATEADD(DAY,  -1,  GETDATE())),
(7,    'empresa02@rutas.com',   1, '127.0.0.1', DATEADD(DAY,  -2,  GETDATE())),
(8,    'empresa03@rutas.com',   1, '127.0.0.1', DATEADD(DAY,  -4,  GETDATE())),
(2,    'cliente01@rutas.com',   0, '127.0.0.1', DATEADD(HOUR, -5,  GETDATE())),
(NULL, 'desconocido@correo.com',0, '127.0.0.1', DATEADD(HOUR, -4,  GETDATE())),
(NULL, 'intruso@correo.com',    0, '127.0.0.1', DATEADD(HOUR, -2,  GETDATE())),
(5,    'cliente04@rutas.com',   0, '127.0.0.1', DATEADD(HOUR, -1,  GETDATE())),
(1,    'admin@mudaconecta.com', 0, '127.0.0.1', DATEADD(DAY,  -6,  GETDATE()));
GO


-- ---------------------------------------------------------------
-- 11. Contraseña unica para todas las cuentas de prueba.
--     Hash generado con password_hash() de PHP (ver README).
-- ---------------------------------------------------------------
UPDATE usuarios
SET password_hash = '$2y$10$a04XawUEuRTrRKXVxXjWleJcSorj3OOQaSZWh9/FHx4x.EUODxjZu';
GO


-- ---------------------------------------------------------------
-- Verificacion rapida (ejecutar aparte, deben dar estos totales)
-- ---------------------------------------------------------------
-- SELECT 'usuarios' AS tabla, COUNT(*) AS total FROM usuarios          -- 12
-- UNION ALL SELECT 'empresas',     COUNT(*) FROM empresas              -- 7
-- UNION ALL SELECT 'zonas',        COUNT(*) FROM zonas_cobertura       -- 8
-- UNION ALL SELECT 'tarifas',      COUNT(*) FROM tarifas               -- 13
-- UNION ALL SELECT 'flota',        COUNT(*) FROM flota                 -- 15
-- UNION ALL SELECT 'proformas',    COUNT(*) FROM proformas             -- 12
-- UNION ALL SELECT 'solicitudes',  COUNT(*) FROM solicitudes           -- 8
-- UNION ALL SELECT 'resenas',      COUNT(*) FROM resenas;              -- 4
GO