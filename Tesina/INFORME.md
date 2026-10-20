# Informe de implementación — MaxRep

Fuente de requerimientos: `Casos_de_Uso_Detallados_-_MaxRep.txt` (CU-01 a CU-07) e
`instrucciones_de_logo.txt` (identidad visual). Fecha: 20/10/2026.

## Matriz de trazabilidad

Estados: **IV** = implementado y verificado con prueba automatizada · **IP** = implementado, pendiente de verificación visual en navegador · **B** = bloqueado.

### CU-01 Registrarse — Visitante

| Paso / excepción | Implementación | Prueba | Estado |
|---|---|---|---|
| 1. Dos tarjetas USUARIO / PROFESOR | `Cuenta/registro.php` (`role="radiogroup"`) | UI | IV |
| 2. Hover: eleva 2 px, borde rojo, sombra | `.role-card:hover` en `Standard.css` | — | IP |
| 3. Clic: tarjeta activa, formulario con *fade-in slide-down* | `Js/registro.js` + `.reveal` | UI | IV |
| 4. Hover del botón: opacidad 90 %, *pointer*, resplandor | `.btn-register:hover` | — | IP |
| 5. Clic: botón deshabilitado con spinner, valida y redirige a Inicio con sesión | `Api/auth.php?action=register`, `login_user()` | API + UI | IV |
| 1.1 Correo existente → borde rojo + tooltip "Este correo ya está en uso" | 409 `email_taken` (sin distinguir mayúsculas) | API + UI | IV |
| 1.2 Contraseña débil → input rojo + lista animada de requisitos | `password_issues()` + `#pwRules` | API + UI | IV |
| 1.3 / 1.4 Campos vacíos → scroll suave, *shake*, asterisco rojo | `flagMissing()`; servidor devuelve `fields` | API + UI | IV |
| 1.5 Sin red → spinner se detiene, botón gris, toast "Error de red" | `MR.api` lanza `network`; `.is-failed` | UI | IV |

### CU-02 Perfil — Usuario/Profesor

| Paso / excepción | Implementación | Prueba | Estado |
|---|---|---|---|
| 1. Modo lectura con campos deshabilitados y "EDITAR PERFIL" | `Cuenta/Perfil.php` | UI | IV |
| 2. Pasa a inputs con borde resaltado y "Guardar Cambios", sin recargar | `Js/perfil.js` `setEditing()` | UI | IV |
| 3. Valida, guarda, toast verde "Perfil actualizado", vuelve a lectura | `Api/perfil.php?action=update` | API + UI | IV |
| 2.1 Dato inválido (letras en peso) → *shake* + borde rojo | Validación cliente y servidor | API + UI | IV |
| 2.2 Foto > 5 MB o formato no admitido → previsualización oscurecida + globo | `finfo` + `getimagesize`; `.is-rejected` | API + UI | IV |
| 2.3 Sesión expira en edición → fondo oscurecido y modal de contraseña | Expiración por inactividad + `MR.reauth()` + reintento | API + UI | IV |

### CU-03 Inicio — Usuario/Profesor

| Paso / excepción | Implementación | Prueba | Estado |
|---|---|---|---|
| 1. *Skeleton loader* y luego widgets | `index.php`, `Js/inicio.js`, `Api/dashboard.php` | UI | IV |
| 2. Hover de tarjetas: `scale(1.02)` y sombra más profunda | `.card--hover` | — | IP |
| 3.1 Problema de conexión → ícono gris y "Reintentar conexión" | `MR.errorState()` | UI | IV |
| 3.2 Usuario nuevo → estados vacíos con "Crear tu primera rutina" | `isNew` | API + UI | IV |

### CU-04 Calculadora RM — en cualquier pestaña

| Paso / excepción | Implementación | Prueba | Estado |
|---|---|---|---|
| 1. "ELEGIR MÉTODO DE CÁLCULO" despliega menú blanco con sombra y hover gris | `1Rm.html`, `Js/rm.js` (listbox con teclado) | UI | IV |
| 2. Elegir cierra el menú; foco ilumina el borde | `choose()`, `:focus` | UI | IV |
| 3. "Calcular" → resultado grande en panel *slide-down* + tabla de % | `compute()` (Brzycki, Epley, Lander, Lombardi) | UI | IV |
| 4.1 Más de 40 reps → alerta amarilla "Las fórmulas son menos precisas…" | `validate()` | UI | IV |
| 4.2 Peso ≤ 0 → se limpia al perder foco y "Calcular" se desactiva | `blur` + `disabled` | UI | IV |
| 4.3 Sin método → "ELEGIR MÉTODO" parpadea | `MR.blink()` | UI | IV |

### CU-05 Rutina — Alumno, Profesor

| Paso / excepción | Implementación | Prueba | Estado |
|---|---|---|---|
| 1. "CREAR RUTINA" abre lienzo con "+ Agregar Ejercicio" que rota al hover | `Rutinas/Rutinas.php`, `.add-ex:hover` | UI (lógica) | IV / IP (rotación) |
| 2. "ENTRAR A RUTINA HECHA (EDITAR)" carga la rutina del día; tarjetas arrastrables flotantes | Campo `Dia`, banner en lista, arrastre con *pointer events* + ↑/↓ | API + UI | IV |
| 3. "CONECTARSE CON PROFESOR" → modal con *backdrop blur* "INTRODUCIR CÓDIGO DE PROFESOR" | `Js/rutinas.js` `connect()` | UI | IV |
| 4. "Vincular" → verifica, cierra modal, check verde animado | `?action=link`, `.check-anim` | API + UI | IV |
| 5. Profesor: "RUTINAS ALUMNOS" con filas que cambian de color al hover | `?action=alumnos`, `.table tbody tr:hover` | API + UI | IV |
| 5.1 Código inválido → *shake* y "Código no encontrado" en rojo, sin cerrar | 404 `not_found` | API + UI | IV |
| 5.2 Guardar sin ejercicios → botón "Añade al menos un ejercicio" deshabilitado | Cliente + servidor (422 `empty`) | API + UI | IV |
| 5.3 Edición simultánea → bloquea al segundo y muestra modal "…recarga la página" | Columna `Version` (bloqueo optimista), 409 | API + UI | IV |

### CU-06 Ejercicios — Usuario/Profesor

| Paso / excepción | Implementación | Prueba | Estado |
|---|---|---|---|
| 1. Foco en "BUSCAR EJERCICIOS E INFO": borde más grueso y búsquedas recientes | `.search__input:focus`, `localStorage` | UI | IV |
| 2. Filtrado en tiempo real (debounce) con grilla de tarjetas | `Api/ejercicios.php?action=list` | API + UI | IV |
| 3. Hover: imagen oscurecida y "Ver Info" con transición de opacidad | `.ex-card__overlay` | UI (estructura) | IV / IP (efecto) |
| 4. Clic: modal con músculos implicados y técnica | `openDetail()` | API + UI | IV |
| 6.1 Sin resultados → estado vacío "No encontramos ejercicios con ese nombre" | `MR.empty()` | API + UI | IV |
| 6.2 Falla multimedia → silueta gris "Contenido multimedia no disponible temporalmente" | `mediaUnavailable()` en `error` de img/video | UI | IV |

### CU-07 Ajustes — Usuario/Profesor

| Paso / excepción | Implementación | Prueba | Estado |
|---|---|---|---|
| 1. "Modo Oscuro": toggle se desliza, fondo verde, transición 0.3 s de toda la paleta | `.switch`, `MR.applyTheme()`, `Users.Theme` + `localStorage` | API + UI | IV |
| 2. "Comprar" → formulario "PONER DATOS" con número formateado con espacios | `payForm()` | UI | IV |
| 3. Botón de pago se hunde, barra de progreso, recibo digital | `.btn-press`, `.progress`, `receiptEl()` | API + UI | IV (modo prueba) |
| 4. "Cancelar" → modal rojo "¿Estás seguro?" con confirmar rojo intenso al hover | `confirmCancel()`, `.modal--danger` | UI | IV |
| 5. "Ayuda y soporte" → panel lateral con FAQ y formulario | `MR.modal({drawer:true})`, tabla `SupportMessages` | API + UI | IV |
| 7.1 Tarjeta rechazada → formulario vibra y banner carmesí "Pago rechazado…" | 402 `declined` | API + UI | IV (modo prueba) |
| 7.2 Clave actual errónea → input rojo y botón vuelve a inactivo | 422 `bad_current` | API + UI | IV |
| 7.3 Sin conexión al cancelar → confirmar bloqueado y loader "Esperando reconexión…" | `navigator.onLine` + evento `online` | UI | IV |
| Cobro real con un procesador de pagos | — | — | **B** |

### Requisitos transversales

| Requisito | Implementación | Estado |
|---|---|---|
| Autenticación real (antes `$_SESSION['User'] = 1` fijo) | `Cuenta/login.php`, `require_page_auth()` | IV |
| Autorización en servidor | `can_view` / `can_edit`, rol profesor | IV |
| Inyección SQL / XSS / CSRF | Consultas preparadas, `e()`, token CSRF | IV |
| Identidad visual (Anton + Inter, paleta, logotipo con líneas) | `Standard.css`, `logo()` | IP |
| Responsive | Barra lateral colapsable < 860 px, grillas fluidas | IP |
| Migración del esquema anterior | `Instalar/migrar.php` (probado 2 veces sobre copia) | IV |

## Decisiones tomadas por falta de detalle en el TXT

1. **1.3 y 1.4 son idénticas** en el TXT; se implementaron una vez.
2. **"Rutina del día":** se agregó un campo *Día* opcional a cada rutina; Inicio y Rutinas muestran la del día actual.
3. **Usuarios también crean rutinas** (ya lo permitía el sistema); el profesor además puede crearlas para un alumno vinculado y editar las de sus alumnos.
4. **Compartir por código** (función "Vincular" existente) se conservó: código de 6 caracteres, 30 minutos, un solo uso, acceso de solo lectura.
5. **No se repiten ejercicios** dentro de una rutina (regla del código anterior).
6. **Calculadora:** se pasó de recalcular al tipear a un botón "Calcular" como pide el CU; se agregaron Lander y Lombardi; se conservó la tabla de porcentajes original.
7. **Pagos en modo prueba**, visible en pantalla. **Soporte** guarda los mensajes en la base (no envía correo).
8. **Logotipo:** la etiqueta "LOGO" y la tabla de colores de la sección 5 describen la lámina de referencia, no pantallas de la app; el logo se aplicó en la barra lateral y en login/registro.
9. **Botones rellenos** usan `#C42F2F` (variante del rojo MAX) para que el texto blanco cumpla contraste AA; `#E23B3B` sigue siendo el acento.
10. **Login** acepta correo o nombre de usuario (antes era por nombre).
