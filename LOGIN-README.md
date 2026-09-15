# Sistema de login — cómo probarlo y cómo subirlo a Hostinger

## Qué se armó

- **Admin**: crea usuarios (clientes/arquitectos) y obras, asigna quién es
  el cliente y el arquitecto de cada obra, y puede gestionar todo.
- **Arquitecto**: ve solo las obras que el admin le asignó. En cada una
  puede cargar **etapas** (línea de tiempo: nombre, fecha, descripción) y
  subir **fotos** de avance.
- **Cliente**: ve su obra en modo solo lectura — línea de tiempo de
  etapas y galería de fotos.

Nadie se autoregistra: las cuentas de cliente y arquitecto las creás vos
desde el panel admin.

## Probarlo en tu PC (ya está listo)

1. Instalé PHP 8.2 en tu máquina (con SQLite y MySQL habilitados) para
   que puedas probar todo sin instalar un servidor de base de datos.
2. Andá a `http://localhost:8000/setup.php` — la primera vez te deja
   crear tu usuario admin (nombre, email, contraseña). Después de eso,
   ese formulario se desactiva solo (por seguridad).
3. Iniciá sesión en `http://localhost:8000/login.html` con ese usuario.
4. Desde **Usuarios** creá un cliente y un arquitecto de prueba.
5. Desde **Obras** creá una obra y asignale ese cliente y arquitecto.
6. Cerrá sesión y entrá como el arquitecto: entrá a la obra, cargá una
   etapa y subí una foto.
7. Cerrá sesión y entrá como el cliente: deberías ver la obra, la etapa
   y la foto, sin poder editar nada.

Si el servidor local no está corriendo, para levantarlo de nuevo:

```bash
"C:\Users\Max\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -S localhost:8000
```

(ejecutalo parado adentro de la carpeta `arhaus/`, y dejalo abierto en
una terminal mientras probás en el navegador).

## Subirlo a Hostinger (cuando estés list@)

1. En **hPanel > Bases de datos > Bases de datos MySQL**, creá una base
   nueva. Anotá: nombre de la base, usuario y contraseña.
2. Entrá a **phpMyAdmin** desde hPanel, elegí esa base, pestaña **Importar**,
   y subí el archivo `database/schema.mysql.sql` de este proyecto. Eso
   crea las tablas.
3. Abrí `config.php` y cambiá:
   ```php
   'driver' => 'mysql',
   'host' => 'localhost',
   'database' => 'el_nombre_que_te_dio_hostinger',
   'user' => 'el_usuario_que_te_dio_hostinger',
   'password' => 'la_contraseña_que_te_dio_hostinger',
   ```
4. Subí **todo** el contenido de la carpeta `arhaus/` (tal cual está) a
   `public_html` por FTP o el Administrador de archivos de hPanel.
5. Entrá a `https://tudominio.com/setup.php` una sola vez para crear tu
   usuario admin real en el servidor.
6. **Importante**: una vez que ya creaste el admin, borrá `setup.php`
   del servidor (no hace falta dejarlo online).

## Notas de seguridad ya incluidas

- Las contraseñas se guardan hasheadas (`password_hash`), nunca en texto plano.
- Los formularios que cambian datos están protegidos con token CSRF.
- Las fotos se validan (tipo, tamaño, que sea una imagen real) antes de
  guardarse, y la carpeta `uploads/` tiene un `.htaccess` que bloquea
  ejecutar PHP ahí adentro.
- `config.php`, la carpeta `lib/` y `database/` están bloqueadas por
  `.htaccess` para que nadie pueda pedirlas directo desde el navegador.

## Subida de fotos: límite del servidor

El panel deja elegir **muchas fotos de una vez** y las manda de a una, así que
lo único que importa es cuánto pesa **cada foto**, no el total. La app rechaza
arriba de 8 MB por foto.

Para que eso funcione, en el servidor `upload_max_filesize` tiene que ser de
8 MB o más. En Hostinger se cambia desde hPanel → PHP Configuration. Si queda
más bajo, las fotos de celular (3–5 MB) pueden fallar de a una con el mensaje
"La foto es demasiado pesada".

En local, el servidor de pruebas ya arranca con el límite subido (está en la
configuración `arhaus` de `.claude/launch.json`).
