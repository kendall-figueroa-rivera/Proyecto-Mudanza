-- Archivo: schema.sql
-- Modulo: Base de datos
-- Responsable: Daniel Gómez Mora
-- Descripcion: Estructura de tablas segun el diagrama ER
--              del anteproyecto (12 entidades + Empresa_Zona).


IF DB_ID('plataforma_mudanzas') IS NULL
    CREATE DATABASE plataforma_mudanzas;
GO

USE plataforma_mudanzas;
GO


CREATE TABLE roles (
    id_rol      INT IDENTITY(1,1) PRIMARY KEY,
    nombre      VARCHAR(50) NOT NULL UNIQUE
);
GO


CREATE TABLE usuarios (
    id_usuario      INT IDENTITY(1,1) PRIMARY KEY,
    id_rol          INT NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    correo          VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    telefono        VARCHAR(20) NULL,
    activo          BIT NOT NULL DEFAULT 1,
    fecha_registro  DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_usuarios_roles
        FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
);
GO


CREATE TABLE login_auditoria (
    id_login         INT IDENTITY(1,1) PRIMARY KEY,
    id_usuario       INT NULL,                       -- NULL si el correo no existe
    correo_intentado VARCHAR(150) NOT NULL,
    exitoso          BIT NOT NULL,
    ip_origen        VARCHAR(45) NULL,
    fecha_hora       DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_login_usuarios
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);
GO


CREATE TABLE token_recuperacion (
    id_token          INT IDENTITY(1,1) PRIMARY KEY,
    id_usuario        INT NOT NULL,
    token             VARCHAR(255) NOT NULL UNIQUE,
    fecha_creacion    DATETIME NOT NULL DEFAULT GETDATE(),
    fecha_expiracion  DATETIME NOT NULL,
    usado             BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_token_usuarios
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);
GO


CREATE TABLE empresas (
    id_empresa       INT IDENTITY(1,1) PRIMARY KEY,
    id_usuario       INT NOT NULL UNIQUE,            -- usuario dueño de la empresa
    nombre_comercial VARCHAR(150) NOT NULL,
    identificacion   VARCHAR(30) NOT NULL UNIQUE,    -- cedula juridica 
    telefono         VARCHAR(20) NULL,
    direccion        VARCHAR(255) NULL,
    descripcion      VARCHAR(500) NULL,
    estado           VARCHAR(15) NOT NULL DEFAULT 'activa',
    fecha_registro   DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_empresas_usuarios
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    CONSTRAINT ck_empresas_estado
        CHECK (estado IN ('activa', 'inactiva', 'suspendida'))
);
GO


CREATE TABLE flota (
    id_vehiculo    INT IDENTITY(1,1) PRIMARY KEY,
    id_empresa     INT NOT NULL,
    placa          VARCHAR(15) NOT NULL UNIQUE,
    tipo_vehiculo  VARCHAR(50) NOT NULL,
    capacidad_kg   DECIMAL(10,2) NOT NULL,
    estado         VARCHAR(15) NOT NULL DEFAULT 'disponible',
    CONSTRAINT fk_flota_empresas
        FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa),
    CONSTRAINT ck_flota_capacidad CHECK (capacidad_kg > 0),
    CONSTRAINT ck_flota_estado
        CHECK (estado IN ('disponible', 'en_servicio', 'mantenimiento'))
);
GO


CREATE TABLE zonas_cobertura (
    id_zona    INT IDENTITY(1,1) PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    provincia  VARCHAR(100) NOT NULL,
    canton     VARCHAR(100) NULL,
    CONSTRAINT uq_zona UNIQUE (nombre, provincia)
);
GO


CREATE TABLE empresa_zona (
    id_empresa  INT NOT NULL,
    id_zona     INT NOT NULL,
    CONSTRAINT pk_empresa_zona PRIMARY KEY (id_empresa, id_zona),
    CONSTRAINT fk_ez_empresas
        FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa),
    CONSTRAINT fk_ez_zonas
        FOREIGN KEY (id_zona) REFERENCES zonas_cobertura(id_zona)
);
GO


CREATE TABLE tarifas (
    id_tarifa        INT IDENTITY(1,1) PRIMARY KEY,
    id_empresa       INT NOT NULL,
    id_zona_origen   INT NOT NULL,
    id_zona_destino  INT NOT NULL,
    distancia_km     DECIMAL(8,2) NOT NULL,
    precio_base      DECIMAL(10,2) NOT NULL,
    precio_por_km    DECIMAL(10,2) NOT NULL,
    activo           BIT NOT NULL DEFAULT 1,

    CONSTRAINT fk_tarifas_zona_origen
        FOREIGN KEY (id_empresa, id_zona_origen)
        REFERENCES empresa_zona(id_empresa, id_zona),
    CONSTRAINT fk_tarifas_zona_destino
        FOREIGN KEY (id_empresa, id_zona_destino)
        REFERENCES empresa_zona(id_empresa, id_zona),
    CONSTRAINT uq_tarifa_ruta UNIQUE (id_empresa, id_zona_origen, id_zona_destino),
    CONSTRAINT ck_tarifas_precios
        CHECK (precio_base >= 0 AND precio_por_km >= 0),
    CONSTRAINT ck_tarifas_distancia CHECK (distancia_km > 0)
);
GO


CREATE TABLE proformas (
    id_proforma       INT IDENTITY(1,1) PRIMARY KEY,
    id_cliente        INT NOT NULL,
    id_empresa        INT NOT NULL,
    id_tarifa         INT NOT NULL,
    id_zona_origen    INT NOT NULL,
    id_zona_destino   INT NOT NULL,
    distancia_km      DECIMAL(8,2) NOT NULL,   -- copiada de la tarifa
    monto_total       DECIMAL(12,2) NOT NULL,  -- calculado por sp_generar_proforma
    estado            VARCHAR(15) NOT NULL DEFAULT 'vigente',
    fecha_generacion  DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_proformas_cliente
        FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario),
    CONSTRAINT fk_proformas_empresa
        FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa),
    CONSTRAINT fk_proformas_tarifa
        FOREIGN KEY (id_tarifa) REFERENCES tarifas(id_tarifa),
    CONSTRAINT fk_proformas_zona_origen
        FOREIGN KEY (id_zona_origen) REFERENCES zonas_cobertura(id_zona),
    CONSTRAINT fk_proformas_zona_destino
        FOREIGN KEY (id_zona_destino) REFERENCES zonas_cobertura(id_zona),
    CONSTRAINT ck_proformas_distancia CHECK (distancia_km > 0),
    CONSTRAINT ck_proformas_estado
        CHECK (estado IN ('vigente', 'aceptada', 'vencida'))
);
GO


CREATE TABLE solicitudes (
    id_solicitud       INT IDENTITY(1,1) PRIMARY KEY,
    id_proforma        INT NOT NULL UNIQUE,
    id_cliente         INT NOT NULL,
    direccion_origen   VARCHAR(255) NOT NULL,
    direccion_destino  VARCHAR(255) NOT NULL,
    fecha_mudanza      DATE NOT NULL,
    descripcion        VARCHAR(500) NULL,
    estado             VARCHAR(15) NOT NULL DEFAULT 'pendiente',
    motivo_respuesta   VARCHAR(255) NULL,
    fecha_creacion     DATETIME NOT NULL DEFAULT GETDATE(),
    fecha_respuesta    DATETIME NULL,
    CONSTRAINT fk_solicitudes_proforma
        FOREIGN KEY (id_proforma) REFERENCES proformas(id_proforma),
    CONSTRAINT fk_solicitudes_cliente
        FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario),
    CONSTRAINT ck_solicitudes_estado
        CHECK (estado IN ('pendiente', 'aceptada', 'rechazada', 'completada', 'cancelada'))
);
GO


CREATE TABLE resenas (
    id_resena     INT IDENTITY(1,1) PRIMARY KEY,
    id_solicitud  INT NOT NULL UNIQUE,               -- una reseña por solicitud
    id_cliente    INT NOT NULL,
    id_empresa    INT NOT NULL,
    calificacion  TINYINT NOT NULL,
    comentario    VARCHAR(500) NULL,
    fecha         DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_resenas_solicitud
        FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id_solicitud),
    CONSTRAINT fk_resenas_cliente
        FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario),
    CONSTRAINT fk_resenas_empresa
        FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa),
    CONSTRAINT ck_resenas_calificacion CHECK (calificacion BETWEEN 1 AND 5)
);
GO


CREATE TABLE notificaciones (
    id_notificacion  INT IDENTITY(1,1) PRIMARY KEY,
    id_usuario       INT NOT NULL,
    mensaje          VARCHAR(500) NOT NULL,
    leida            BIT NOT NULL DEFAULT 0,
    fecha            DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_notificaciones_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);
GO


INSERT INTO roles (nombre) VALUES ('administrador'), ('cliente'), ('empresa');
GO