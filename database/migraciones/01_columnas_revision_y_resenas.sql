-- Archivo: columnas_revision_y_resenas.sql
-- Modulo: Base de datos
-- Responsable: Daniel Gómez Mora
-- Descripcion: Migracion SOLO PARA BASES CREADAS ANTES de la version actual.
--              Una base nueva creada con schema.sql ya trae estos cambios.
--              Agrega las columnas de revision de empresas y el estado de
--              las reseñas. Se puede ejecutar mas de una vez.


USE plataforma_mudanzas;
GO

IF COL_LENGTH('empresas', 'id_admin_revision') IS NULL
    ALTER TABLE empresas ADD id_admin_revision INT NULL;
IF COL_LENGTH('empresas', 'fecha_revision') IS NULL
    ALTER TABLE empresas ADD fecha_revision DATETIME NULL;
IF COL_LENGTH('empresas', 'motivo_rechazo') IS NULL
    ALTER TABLE empresas ADD motivo_rechazo VARCHAR(255) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'fk_empresas_admin')
    ALTER TABLE empresas ADD CONSTRAINT fk_empresas_admin
        FOREIGN KEY (id_admin_revision) REFERENCES usuarios(id_usuario);
GO

IF COL_LENGTH('resenas', 'estado') IS NULL
    ALTER TABLE resenas ADD estado VARCHAR(15) NOT NULL DEFAULT 'visible';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'ck_resenas_estado')
    ALTER TABLE resenas ADD CONSTRAINT ck_resenas_estado
        CHECK (estado IN ('visible', 'oculta'));
GO