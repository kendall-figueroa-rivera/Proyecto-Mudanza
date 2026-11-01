-- Archivo: migracion_flota_ruta.sql
-- Modulo: Base de datos - Flota
-- Descripcion: Agrega la columna id_tarifa a flota para indicar la ruta
--              (tarifa origen -> destino) que realiza cada vehiculo.
--              Ejecutar una sola vez en bases ya creadas.

USE plataforma_mudanzas;
GO

IF COL_LENGTH('flota', 'id_tarifa') IS NULL
BEGIN
    ALTER TABLE flota ADD id_tarifa INT NULL;
    ALTER TABLE flota ADD CONSTRAINT fk_flota_tarifas
        FOREIGN KEY (id_tarifa) REFERENCES tarifas(id_tarifa);
END
GO
