-- Archivo: migracion_flota_activo.sql
-- Modulo: Base de datos - Flota
-- Descripcion: Agrega la columna activo a flota para la eliminacion logica
--              de vehiculos. Ejecutar una sola vez en bases ya creadas.

USE plataforma_mudanzas;
GO

IF COL_LENGTH('flota', 'activo') IS NULL
    ALTER TABLE flota ADD activo BIT NOT NULL CONSTRAINT df_flota_activo DEFAULT 1;
GO
