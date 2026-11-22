# MudaConecta

Plataforma de intermediación entre empresas de transporte/mudanzas y usuarios finales.

**Curso:** SC-502 Ambiente Web Cliente/Servidor — Universidad Fidélitas
**Grupo:** G2

## Integrantes y módulos

| Módulo | Responsable | Rama |
|---|---|---|
| Autenticación, cuenta de usuario e integración | Kendall David Figueroa Rivera | `feature/auth` |
| Cliente y sitio público | Roberto Esteban Montero Taylor | `feature/cliente` |
| Empresa | Santiago Correa Fallas | `feature/empresa` |
| Administrador | Eliatt Josue Azofeifa Chavarria | `feature/admin` |
| Base de datos y correo | Daniel Josue Gomez Mora | `feature/bd` |

## Qué hace

Conecta a **clientes** que necesitan una mudanza o un flete con **empresas** de transporte.
Cada empresa publica su flota y sus tarifas por ruta (zona de origen y destino); el cliente busca por
ruta, genera una proforma, la convierte en solicitud y, al terminar el servicio, califica a la empresa.
Un **administrador** aprueba o suspende empresas y mantiene el catálogo de zonas.

Roles y página a la que lleva el inicio de sesión:

| Rol | Entra a |
|---|---|
| Cliente | `app/views/cliente/dashboard.php` |
| Empresa | `app/views/empresa/dashboard.php` |
| Administrador | `app/views/admin/dashboard.php` |

## Stack tecnológico

- **Frontend:** HTML5, CSS3, Bootstrap 5, JavaScript y jQuery
- **Backend:** PHP con arquitectura MVC
- **Base de datos:** Microsoft SQL Server (Express), con procedimientos almacenados, usando PDO_SQLSRV

## Estructura del proyecto

```
Proyecto-Mudanza/
├── index.php              → abre el home al entrar a la carpeta del proyecto
├── app/
│   ├── controllers/       → lógica de cada módulo (un controlador por actor)
│   ├── models/            → acceso a datos, un modelo por entidad
│   └── views/
│       ├── auth/          → login, registro, mi cuenta, recuperar contraseña
│       ├── publico/       → home, directorio, perfil de empresa, contacto
│       ├── cliente/       → buscar por ruta, proformas, solicitudes, reseñas
│       ├── empresa/       → perfil, flota, rutas y tarifas, solicitudes
│       ├── admin/         → empresas, zonas, usuarios, reseñas
│       └── partials/      → menús reutilizables de cada panel
├── public/assets/
│   ├── css/               → hojas de estilo (siempre enlazadas, nunca dentro de la página)
│   └── js/                → scripts (siempre enlazados, nunca dentro de la página)
├── config/database.php    → conexión a SQL Server (PDO)
├── database/
│   ├── schema.sql         → creación de las tablas
│   ├── procedures.sql     → procedimientos almacenados
│   └── migracion_*.sql    → solo para bases creadas antes de ciertos cambios (ver más abajo)
└── docs/                  → documento IEEE, diagramas y guía del usuario administrador
```

Las reglas de nombres, comentarios y estilo están en [`ESTANDAR_PROGRAMACION.md`](ESTANDAR_PROGRAMACION.md).

## Requisitos

| Componente | Versión |
|---|---|
| XAMPP (Apache y PHP) | PHP 8.0 o superior (el grupo usa 8.0.30; también probado con 8.4) |
| SQL Server | 2025 Express |
| SSMS | 22 |
| ODBC Driver for SQL Server | 18 (x64) |
| Drivers de PHP para SQL Server | `sqlsrv` y `pdo_sqlsrv`, versión que corresponda a tu PHP |
| Git y VS Code | Cualquier versión reciente |

> Cada integrante tiene **su propia base de datos** en su computadora: lo que instala una persona no lo ven las demás.

## Instalación paso a paso

La guía con capturas de pantalla (`Guia_instalacion_SQLServer_PHP.pdf`) tiene el detalle de la instalación de
SQL Server, SSMS y los drivers. Este es el resumen en orden:

### 1. Clonar el proyecto

Dentro de `C:\xampp\htdocs`:

```
git clone https://github.com/kendall-figueroa-rivera/Proyecto-Mudanza.git
cd Proyecto-Mudanza
git checkout develop
```

### 2. Instalar SQL Server, SSMS y el ODBC Driver 18

Sigue la guía. Al conectarte desde SSMS usa el servidor `.\SQLEXPRESS` con autenticación de Windows y marca
**Certificado de servidor de confianza**.

### 3. Agregar las extensiones de PHP

1. Descarga los drivers de [github.com/microsoft/msphpsql/releases](https://github.com/microsoft/msphpsql/releases)
   y copia a `C:\xampp\php\ext` los **dos archivos `_ts`** (Thread Safe, x64) que correspondan a tu versión de PHP.
2. En el Panel de XAMPP: Apache → *Config* → *PHP (php.ini)*. Debajo de `;extension=shmop` agrega las dos
   líneas, **sin punto y coma** y con el mismo nombre que los archivos copiados. Por ejemplo, para PHP 8.0:
   ```
   extension=php_sqlsrv_80_ts.dll
   extension=php_pdo_sqlsrv_80_ts.dll
   ```
3. Reinicia Apache y comprueba con `php -m` que aparezcan `sqlsrv` y `pdo_sqlsrv`.

### 4. Crear la base de datos

En SSMS (*Archivo → Abrir → Archivo*), ejecuta **en este orden y una sola vez cada uno**:

1. `database/schema.sql`
2. `database/procedures.sql`

Debe quedar la base `plataforma_mudanzas` con 13 tablas y sus procedimientos almacenados. Para empezar de
cero: clic derecho sobre la base → *Eliminar* (marca *Cerrar conexiones existentes*) y repite los dos scripts.

> **Si creaste tu base antes de que existieran las columnas `flota.activo` y `flota.id_tarifa`**, ejecuta además,
> una sola vez, `database/migracion_flota_activo.sql` y `database/migracion_flota_ruta.sql`.
> Una base nueva creada con el `schema.sql` actual no los necesita.

### 5. Crear el usuario de SQL Server que usa la aplicación

PHP se conecta con un usuario de SQL Server, el mismo para todo el grupo. En una consulta nueva de SSMS:

```sql
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = 'administrador')
    CREATE LOGIN administrador WITH PASSWORD = 'Mudanzas.2026!';
GO
USE plataforma_mudanzas;
GO
IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'administrador')
    CREATE USER administrador FOR LOGIN administrador;
GO
ALTER ROLE db_owner ADD MEMBER administrador;
GO
```

Luego activa la **autenticación mixta**: clic derecho sobre el servidor → *Propiedades* → *Seguridad* →
*Modo de autenticación de SQL Server y Windows*, y reinicia el servicio **SQL Server (SQLEXPRESS)** desde
`services.msc`.

### 6. Revisar la conexión

`config/database.php` ya apunta a `.\SQLEXPRESS` y a la base `plataforma_mudanzas`. Si el nombre de tu instancia
es otro, cámbialo solo en tu copia y **no subas ese cambio** al repositorio.

### 7. Crear el usuario administrador de la aplicación

El administrador no se registra desde la página. Los pasos están en [`docs/README.md`](docs/README.md): se
genera el hash de la contraseña con PHP y se crea la cuenta con `sp_registrar_usuario`.
La cuenta resultante es `admin@mudaconecta.com`.

### 8. Abrir la aplicación

Con Apache iniciado: <http://localhost/Proyecto-Mudanza/>

## Flujo de trabajo en Git

- `main`: versión estable para las entregas. Solo recibe cambios desde `develop`.
- `develop`: rama de integración del equipo.
- `feature/…`: una rama por tarea o módulo, creada desde `develop`.

**Reglas**

- Nadie hace commit directo a `main` ni a `develop`: todo entra por **Pull Request hacia `develop`**.
- **Otra persona revisa y mezcla el Pull Request**; nadie mezcla el suyo.
- Mensajes de commit con el formato `[modulo] qué se hizo`, por ejemplo `[auth] agregar pagina Mi cuenta`.
- Antes de crear una rama nueva: `git checkout develop` y `git pull origin develop`.
- No se suben contraseñas ni credenciales.

## Convenciones del código

- El CSS y el JavaScript van en archivos externos dentro de `public/assets/`; nada de `<style>` ni `<script>`
  escritos dentro de las páginas.
- Todo archivo lleva el encabezado de comentario del estándar y cada método un comentario breve.
- Cada formulario valida en el navegador (jQuery) **y** en el servidor (PHP). Los formularios de los paneles
  llevan token CSRF.
- Las consultas a la base de datos son siempre parametrizadas.
