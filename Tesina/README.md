# MaxRep

Plataforma web de gimnasio: rutinas, biblioteca de ejercicios, marcas personales,
calculadora de RM y vínculo alumno–profesor.

**Tecnología:** PHP 8 (sin frameworks) · MySQL / MariaDB · HTML, CSS y JavaScript sin dependencias.
Pensado para XAMPP en Windows, funciona en cualquier Apache o en el servidor embebido de PHP.

## Requisitos

- PHP **8.0 o superior** con `mysqli`, `fileinfo` y `mbstring` (XAMPP 8.x ya los trae).
- MySQL 5.7+ o MariaDB 10.3+.
- Navegador moderno (Chrome, Edge, Firefox, Safari de los últimos 3 años).

## Instalación en XAMPP

1. Copiá la carpeta `Tesina` dentro de `C:\xampp\htdocs\`.
2. Iniciá **Apache** y **MySQL** desde el panel de XAMPP.
3. Base de datos, una de dos:
   - **Instalación nueva:** en phpMyAdmin → *Importar* → `IronCore.sql`. Crea la base `ironcore`
     con todas las tablas, los 125 ejercicios y dos usuarios de demostración.
   - **Ya tenés la base `ironcore` del esquema anterior:** hacé un respaldo (phpMyAdmin → *Exportar*)
     y ejecutá el migrador desde la carpeta del proyecto:
     ```
     C:\xampp\php\php.exe Instalar\migrar.php
     ```
     o abrí `http://localhost/Tesina/Instalar/migrar.php?confirmar=1` desde la misma PC.
     Convierte las contraseñas a hash, pasa las rutinas y marcas al formato nuevo y no borra columnas viejas.
     Se puede ejecutar más de una vez.
4. Entrá a `http://localhost/Tesina/`.

Usuarios de demostración (solo en instalación nueva; **borralos o cambiales la contraseña en producción**):

| Rol | Correo | Contraseña |
|---|---|---|
| Usuario (alumno) | `Ejemplo@gmail.com` | `Demo1234` |
| Profesor (código `PROF2026`) | `profe@maxrep.local` | `Demo1234` |

## Configuración

Los valores por defecto de `config.php` sirven para XAMPP. En otros entornos usá variables de entorno
(no hace falta editar código):

| Variable | Por defecto | Uso |
|---|---|---|
| `MAXREP_DB_HOST` / `MAXREP_DB_PORT` | `localhost` / `3306` | Servidor MySQL |
| `MAXREP_DB_USER` / `MAXREP_DB_PASS` | `root` / *(vacía)* | Credenciales MySQL |
| `MAXREP_DB_NAME` | `ironcore` | Nombre de la base |
| `MAXREP_TZ` | `America/Argentina/Cordoba` | Zona horaria (define la "rutina de hoy") |
| `MAXREP_SESSION_IDLE` | `1800` | Segundos de inactividad hasta que expira la sesión |
| `MAXREP_PAYMENT_MODE` | `test` | Pasarela de pagos. Solo existe `test` (no cobra) |
| `MAXREP_PLAN_PRECIO` / `MAXREP_PLAN_MONEDA` | `4999` / `ARS` | Precio del plan Premium |
| `MAXREP_BASE_URL` | *(autodetectada)* | Ruta web del proyecto, p. ej. `/Tesina` |
| `MAXREP_DEBUG` | `0` | `1` muestra errores detallados |

El `.htaccess` sube el límite de subida a 6 MB (la foto de perfil admite 5 MB; XAMPP trae 2 MB).
Si tu Apache no usa `mod_php`, configurá `upload_max_filesize=6M` y `post_max_size=8M` en `php.ini`.

Para que MySQL borre solo los códigos de rutina vencidos, activá el programador de eventos
(`SET GLOBAL event_scheduler = ON;`). Si no, la app igual ignora los códigos vencidos.

## Estructura

```
index.php               Inicio (dashboard)                         CU-03
Cuenta/                 registro.php (CU-01), login.php, logout.php, Perfil.php (CU-02)
Rutinas/                ListaRutinas.php, Rutinas.php (lienzo)     CU-05
Ejercicios/             Ejercicios.php (biblioteca)                CU-06
Ajustes/                Ajustes.php                                CU-07
1Rm.html                Calculadora RM, presente en todas las páginas (CU-04)
Api/                    Endpoints JSON: auth, perfil, dashboard, rutinas, ejercicios, ajustes
Conection.php           Conexión, sesión, CSRF, helpers (consultas preparadas)
layout.php, Bl.php      Plantilla y barra lateral
Css/                    Standard.css (sistema visual MaxRep), Rm.css
Js/                     app.js (utilidades) + un script por página
Instalar/               migrar.php, esquema original de referencia
uploads/perfiles/       Fotos de perfil (Apache no ejecuta código aquí)
tests/                  api_test.py, ui_test.js
```

## Seguridad

- Contraseñas con `password_hash` (bcrypt); las viejas en texto plano se convierten al migrar o al iniciar sesión.
- Todas las consultas son preparadas; toda salida HTML se escapa; el JS construye el DOM sin `innerHTML` con datos.
- Token CSRF en cada escritura; acciones de escritura solo por POST; cookies `HttpOnly` y `SameSite=Lax`.
- Permisos verificados en el servidor: dueño, profesor vinculado o rutina compartida (solo lectura).
- Fotos validadas por contenido real (no por extensión), con nombre aleatorio.
- Freno de login tras 5 intentos fallidos.
- Nunca se guarda el número completo de tarjeta ni el código de seguridad.

## Pruebas

Con el sitio corriendo y una base de prueba (las pruebas crean usuarios nuevos, no borran nada):

```bash
# API (Python 3 + requests)
pip install requests
MAXREP_URL=http://localhost/Tesina python tests/api_test.py

# Interfaz (Node 18+ y jsdom)
npm i jsdom@24
MAXREP_URL=http://localhost/Tesina node tests/ui_test.js
```

Para probar también la expiración de sesión, levantá un segundo servidor con sesión de 2 segundos
sobre la misma base y pasá su URL en `MAXREP_URL_SHORT`:

```bash
MAXREP_DB_NAME=maxrep_test MAXREP_SESSION_IDLE=2 php -S 127.0.0.1:8081 -t <carpeta que contiene Tesina>
```

## Modo de prueba de pagos

Con `MAXREP_PAYMENT_MODE=test` la pasarela integrada **no cobra**:
`4242 4242 4242 4242` se aprueba y `4000 0000 0000 0002` se rechaza (cualquier vencimiento futuro y CVC).
Para cobrar de verdad hay que integrar un procesador (Mercado Pago, Stripe, etc.) en
`Api/ajustes.php` → acción `subscribe`, idealmente con su formulario alojado para no manejar datos de tarjeta.
