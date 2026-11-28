-- Archivo: estado_suspendida_empresas.sql
-- Modulo: Base de datos
-- Responsable: Daniel Gómez Mora
-- Descripcion: Migracion SOLO PARA BASES CREADAS ANTES de la version actual.
--              Una base nueva creada con schema.sql ya trae este cambio.
--              Actualiza la restriccion ck_empresas_estado para permitir
--              los estados inactiva y suspendida. Se puede ejecutar mas de una vez.


USE plataforma_mudanzas;
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'ck_empresas_estado')
    ALTER TABLE empresas DROP CONSTRAINT ck_empresas_estado;
GO

ALTER TABLE empresas ADD CONSTRAINT ck_empresas_estado
    CHECK (estado IN ('pendiente', 'activa', 'rechazada', 'inactiva', 'suspendida'));
GO