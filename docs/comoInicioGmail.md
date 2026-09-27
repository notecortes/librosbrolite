¡Buenas noticias! En **LibrosBro el código ya está 100% implementado** y preparado en PHP puro (usando cURL nativo, sin librerías externas).

Cuenta con el flujo OAuth 2.0 completo:
- La ruta de inicio [`/auth/google`](file:///Users/paspas/Library/CloudStorage/OneDrive-Conselleriad'Educació/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/router.php#L271).
- El retorno y validación segura con token CSRF/state en [`/auth/google/callback`](file:///Users/paspas/Library/CloudStorage/OneDrive-Conselleriad'Educació/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/router.php#L290).
- La gestión en [`app/helpers/auth_google.php`](file:///Users/paspas/Library/CloudStorage/OneDrive-Conselleriad'Educació/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/helpers/auth_google.php) (vinculación automática si el email ya existía o creación de nueva cuenta).
- El botón **«Continuar con Google»** en [`app/views/auth/login.php`](file:///Users/paspas/Library/CloudStorage/OneDrive-Conselleriad'Educació/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/views/auth/login.php#L63-L74), que **se activa automáticamente** en cuanto configuras las credenciales.

Para ponerlo en marcha en tu hosting o en local, solo necesitas obtener las credenciales de Google y pegarlas en el panel. Aquí tienes el paso a paso:

---

### Paso 1: Crear las credenciales en Google Cloud Console

1. Entra en [Google Cloud Console](https://console.cloud.google.com/) con tu cuenta de Google.
2. Crea un proyecto nuevo (por ejemplo, `LibrosBro`).
3. En el menú lateral, ve a **APIs y servicios** > **Pantalla de consentimiento de OAuth**:
   - **Tipo de usuario:** Selecciona **Externo** y pulsa *Crear*.
   - **Nombre de la aplicación:** `LibrosBro`.
   - **Correo de asistencia del usuario:** tu email (ej. `notecortesprofe@gmail.com`).
   - **Logotipo de la aplicación:** (opcional).
   - **Dominio de la aplicación:**
     - Enlace a la página principal: `https://tudominio.com`
     - Enlace a la política de privacidad: `https://tudominio.com/privacidad` *(LibrosBro ya la tiene creada)*.
     - Enlace a las condiciones de servicio: `https://tudominio.com/terminos`.
   - **Dominios autorizados:** Pon tu dominio (ej. `tudominio.com`). Si estás en local no es necesario.
   - **Datos de contacto del desarrollador:** tu email.
   - **Permisos (Scopes):** Pulsa *Añadir o quitar permisos* y marca `.../auth/userinfo.email` y `.../auth/userinfo.profile`. Guarda y continúa.
   - **Usuarios de prueba:** Mientras la app esté en modo prueba, puedes añadir los correos que vayan a probarla. *(Cuando quieras que cualquiera pueda entrar, pulsa en «Publicar aplicación»)*.
4. En el menú lateral, ve a **Credenciales**:
   - Pulsa **Crear credenciales** > **ID de cliente de OAuth**.
   - **Tipo de aplicación:** **Aplicación web**.
   - **Nombre:** `LibrosBro Web`.
   - **Orígenes autorizados de JavaScript:**
     - `https://tudominio.com`
     - *(Si pruebas en local, puedes añadir `http://localhost:8088`)*.
   - **URIs de redireccionamiento autorizados** *(¡muy importante!):*
     - `https://tudominio.com/auth/google/callback`
     - *(Si pruebas en local: `http://localhost:8088/auth/google/callback`)*.
   - Pulsa **Crear**.
5. Google te mostrará en pantalla:
   - **ID de cliente** (termina en `.apps.googleusercontent.com`).
   - **Secreto de cliente**.

---

### Paso 2: Guardar las credenciales en LibrosBro

Tienes dos maneras muy sencillas:

#### Opción A — Desde el propio panel web de LibrosBro (Recomendada):
1. Inicia sesión en LibrosBro con una cuenta de Administrador.
2. Ve a **Panel de Control** > **Configuración** (ruta `/admin/configuracion`).
3. Baja hasta la sección **«4. Integración con Google Sign-In»**:
   - **Google Client ID:** Pega el ID de cliente de Google.
   - **Google Client Secret:** Pega el Secreto de cliente.
   - **Redirect URI de retorno:** Pega la URL de callback (ej. `https://tudominio.com/auth/google/callback`).
4. Haz clic en **Guardar Cambios**.

#### Opción B — Directamente en la base de datos (por ejemplo, desde phpMyAdmin en cPanel):
Si prefieres actualizarlo directo en MySQL:
```sql
UPDATE configuracion SET valor = 'TU_CLIENT_ID.apps.googleusercontent.com' WHERE clave = 'google_client_id';
UPDATE configuracion SET valor = 'TU_CLIENT_SECRET' WHERE clave = 'google_client_secret';
UPDATE configuracion SET valor = 'https://tudominio.com/auth/google/callback' WHERE clave = 'google_redirect_uri';
```

---

### Paso 3: Comprobación

1. Sal de tu sesión y ve a la pantalla de login (`/login`).
2. Comprobarás que automáticamente aparece el separador *"o también"* y el botón **«Continuar con Google»**.
3. Al hacer clic:
   - Te llevará a la pantalla de selección de cuenta de Google.
   - Tras conceder permiso, Google devolverá al usuario a `/auth/google/callback`.
   - Si el email ya existía como usuario local, se vincula y se inicia sesión.
   - Si no existía, se crea una cuenta nueva `google-only` y entra directamente a su panel.

> [!NOTE]
> En entornos de producción (hosting real), Google exige que la URL de retorno use protocolo seguro **`https://`**. En local (`localhost`) permite `http://`.