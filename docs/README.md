Coloquen aquí:
- El documento IEEE del anteproyecto (PDF o Word)
- Las imágenes del sitemap (Fig. 1)
- El diagrama entidad-relación (Fig. 2)
- El diagrama relacional (Fig. 3)

---------------------------------------------------------------
Para crear el usuario administrador para entrar a la página:

1. Ejecutá en git bash el siguiente comando (recordá tener el proyecto en xampp/htdocs):
      /c/xampp/php/php.exe -r "echo password_hash('Mudanzas.2026!', PASSWORD_DEFAULT), PHP_EOL;"

2. Iniciá una nueva consulta para sql server (Ctrl + N). 

3. Copia, pega en la nueva consulta (conectada a la base de datos del proyecto) y ejecutá el siguiente script:
    USE plataforma_mudanzas;
    GO

    DECLARE @id_rol INT;

    SELECT @id_rol = id_rol
    FROM roles
    WHERE nombre = 'administrador';

    EXEC dbo.sp_registrar_usuario
        @id_rol = @id_rol,
        @nombre = N'Administrador',
        @correo = 'admin@mudaconecta.com',
        @password_hash = 'PEGA_AQUI_EL_HASH_GENERADO', //ACÁ PONE EL TEXTO LARGO QUE GENERA EL PASO 1, DENTRO DE ''
        @telefono = NULL;
    GO

4. Inicia sesión en el proyecto, con las credenciales:
    correo: admin@mudaconecta.com
    contraseña: Mudanzas.2026!
