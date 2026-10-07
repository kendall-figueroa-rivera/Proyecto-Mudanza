# Plataforma de intermediación entre empresas de transporte/mudanzas y usuarios finales

**Curso:** SC-502 Ambiente Web Cliente/Servidor — Universidad Fidélitas
**Grupo:** G2
**Integrantes:**
- Figueroa Rivera Kendall David
- Montero Taylor Roberto Esteban
- Azofeifa Chavarria Eliatt Josue
- Gomez Mora Daniel Josue
- Correa Fallas Santiago

## Stack tecnológico

- **Frontend:** HTML5, CSS3, Bootstrap, JavaScript, jQuery
- **Backend:** PHP (arquitectura MVC)
- **Base de datos:** MySQL (con procedimientos almacenados)

## Estructura del proyecto

```
proyecto-mudanzas/
├── app/
│   ├── controllers/     → Lógica de cada módulo (uno por actor)
│   ├── models/           → Acceso a datos, uno por entidad del ER
│   └── views/             → Vistas HTML/PHP organizadas por módulo
│       ├── auth/          → Login, registro, recuperación de contraseña
│       ├── publico/       → Home, contacto, directorio de empresas
│       ├── cliente/       → Búsqueda, proformas, solicitudes, reseñas
│       ├── empresa/       → Perfil, flota, tarifas, solicitudes recibidas
│       ├── admin/         → Moderación de usuarios/empresas/reseñas, zonas
│       └── partials/      → Header, footer y navs reutilizables
├── public/
│   ├── index.php          → Punto de entrada único de la aplicación
│   └── assets/
│       ├── css/           → Hojas de estilo (enlazadas, nunca inline)
│       ├── js/             → Scripts (enlazados, nunca inline)
│       └── img/
├── config/
│   └── database.php       → Conexión centralizada a MySQL
├── database/
│   ├── schema.sql          → Creación de tablas
│   └── procedures.sql     → Procedimientos almacenados
└── docs/                   → Documento IEEE, diagramas ER y relacional
```

## Flujo de trabajo en Git

- `main` → versión estable, solo recibe merge desde `develop`.
- `develop` → rama de integración del equipo.
- `feature/auth`, `feature/cliente`, `feature/empresa`, `feature/admin`, `feature/bd` → una rama por módulo/persona.

**Regla:** nadie hace commit directo a `main` ni a `develop`. Todo cambio entra por Pull Request desde su rama `feature/...` hacia `develop`.

## Asignación de módulos

| Módulo | Rama | Responsable |
|---|---|---|
| Autenticación | `feature/auth` | |
| Cliente | `feature/cliente` | |
| Empresa | `feature/empresa` | |
| Administrador | `feature/admin` | |
| Base de datos | `feature/bd` | |

Ver `ESTANDAR_PROGRAMACION.md` para las reglas de nombres de archivos, carpetas, métodos y comentarios.
