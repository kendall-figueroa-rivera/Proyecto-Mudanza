# Estándar de programación del proyecto

Este documento resuelve el requisito del profesor: *"Definición de un estándar de programación (métodos, carpetas y comentarios)"*.

## 1. Carpetas

- Cada carpeta dentro de `app/` corresponde a una capa del patrón **MVC**: `controllers`, `models`, `views`.
- Dentro de `views/`, las subcarpetas corresponden a un **módulo/actor** del sistema (`auth`, `cliente`, `empresa`, `admin`, `publico`), nunca se mezclan vistas de distintos módulos en una misma carpeta.
- Los archivos compartidos entre módulos (header, footer, navs) van en `views/partials/`.
- Los archivos estáticos (CSS, JS, imágenes) viven únicamente en `public/assets/`, nunca sueltos en otras carpetas.

## 2. Nombres de archivos

- **Controladores:** `NombreController.php` en PascalCase (ej. `AuthController.php`).
- **Modelos:** `NombreEntidad.php` en PascalCase, singular (ej. `Usuario.php`, no `Usuarios.php`).
- **Vistas:** minúsculas con guion bajo (ej. `historial_proformas.php`).
- **Procedimientos almacenados:** prefijo `sp_` + acción en minúscula (ej. `sp_crear_solicitud`).

## 3. Métodos (funciones)

- Nombres en `camelCase`, verbo + sustantivo (ej. `validarLogin()`, `obtenerFlotaPorEmpresa()`).
- Un método hace una sola cosa. Si necesita "y" en la descripción (ej. "valida y guarda"), probablemente deben ser dos métodos.
- Todo método que reciba datos de un formulario debe validar antes de tocar la base de datos.

## 4. Comentarios

Cada archivo nuevo debe iniciar con un bloque de encabezado:

```php
/**
 * Archivo: NombreDelArchivo.php
 * Modulo: [nombre del módulo]
 * Responsable: [nombre del integrante]
 * Descripcion: [qué hace este archivo]
 */
```

Cada método debe llevar un comentario de una línea antes de su declaración:

```php
// Valida que el correo no esté registrado previamente
function correoDisponible($correo) { ... }
```

## 5. Conexión a base de datos y CSS/JS

- Todo archivo PHP que necesite base de datos incluye `config/database.php` — nunca se duplican credenciales.
- CSS y JS se enlazan siempre como archivos externos (`<link>`, `<script src="">`), nunca estilos o scripts dentro del HTML, según exige el curso.

## 6. Control de versiones

- Mensajes de commit en español, formato: `[módulo] acción breve` (ej. `[auth] agregar validación de formato de correo`).
- Ninguna rama `feature/...` se fusiona a `develop` sin que otra persona del equipo revise el Pull Request.
