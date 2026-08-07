# Guía — Exponer Mailpit externamente (como la app)

**Objetivo:** poder abrir la interfaz de Mailpit desde tu navegador en `https://consultor.une.edu.py/mailpit/`, sin depender del túnel SSH (`ssh -L 8025:127.0.0.1:8025 consultor@10.10.10.226`).

**Contexto:** Mailpit hoy corre "suelto" en el servidor, solo en `127.0.0.1:8025` (sin unidad systemd, no sobrevive reboots). La app sí es accesible externamente porque nginx hace proxy desde `consultor.une.edu.py:80/443` hacia Octane. Vamos a agregar un segundo `location` en ese mismo server block, apuntando a Mailpit, protegido con auth básica (va a mostrar correos reales de alumnos, incluidos los PIN de verificación — no lo dejes sin contraseña).

De paso, esto resuelve también que Mailpit no sobreviva a un reboot.

Todo esto se corre en el servidor (`ssh consultor@10.10.10.226`), con `sudo` donde se indica.

---

## 1. Verificar el estado actual

```fish
ss -tln | grep 1025   # SMTP de Mailpit, debería estar escuchando
ss -tln | grep 8025   # UI web de Mailpit
ps aux | grep mailpit # cómo está arrancado hoy (para saber el binario/flags)
```

Anotá la ruta del binario que aparezca en `ps aux` (normalmente `/usr/local/bin/mailpit` o similar) y con qué flags/env corre, para no perder configuración al migrarlo a systemd.

---

## 2. Crear la unidad systemd de Mailpit (con webroot)

Para que la UI funcione bien detrás de un subpath (`/mailpit/`) hay que decirle a Mailpit su webroot, si no los assets (CSS/JS) van a romperse.

```fish
echo "[Unit]
Description=Mailpit
After=network.target

[Service]
Type=simple
User=consultor
Environment=MP_WEBROOT=mailpit
ExecStart=/usr/local/bin/mailpit --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025
Restart=on-failure

[Install]
WantedBy=multi-user.target" | sudo tee /etc/systemd/system/mailpit.service > /dev/null
```

(fish no soporta heredocs `<<EOF` ni herestrings `<<<` — ninguno de los dos existe en su sintaxis. La forma que sí funciona es un string multilínea entre comillas como argumento de `echo`, pipeado a `tee`).

> **Ojo con el nombre de la variable:** la de Mailpit es `MP_WEBROOT` (no `MAILPIT_WEBROOT`), y el valor va **sin barras** (`mailpit`, no `/mailpit/`) — Mailpit las agrega solo. Con el nombre equivocado la variable simplemente se ignora, Mailpit sirve todo en `/` como si no tuviera webroot, y cualquier request a `/mailpit/...` le da 404 (el típico `404 page not found` de Go).

Ajustá `ExecStart` a la ruta real del binario y a los flags que viste en el paso 1 (puerto SMTP, etc.) si difieren.

```fish
sudo systemctl daemon-reload
sudo systemctl enable --now mailpit
systemctl status mailpit   # confirmar "active (running)"
```

**Si ya habías creado el servicio con `MAILPIT_WEBROOT` (el nombre incorrecto)** y te está dando 404, corregilo así:

```fish
sudo sed -i 's/MAILPIT_WEBROOT=\/mailpit\//MP_WEBROOT=mailpit/' /etc/systemd/system/mailpit.service
sudo systemctl daemon-reload
sudo systemctl restart mailpit
```

Y confirmá que Mailpit levantó con el webroot bien puesto, probando directo contra el puerto (sin pasar por nginx todavía):

```fish
curl -i http://127.0.0.1:8025/mailpit/
```

Si eso también da 404, el problema está en Mailpit/systemd, no en nginx — revisá `journalctl -u mailpit -n 50` para ver con qué flags/env arrancó realmente. Si ese `curl` responde 200 (o pide auth), el problema pasa a estar del lado de nginx (location mal puesto o no recargado).

Si antes lo tenías corriendo manualmente (proceso suelto), matalo para que no choquen los dos en el mismo puerto:

```fish
pkill -f 'mailpit' # antes de habilitar el servicio, o después si el puerto queda ocupado
```

---

## 3. Generar el usuario/contraseña de auth básica

```fish
sudo apt-get install -y apache2-utils   # si falta htpasswd
sudo htpasswd -c /etc/nginx/.htpasswd-mailpit consultor
```

Te va a pedir una contraseña — usá una nueva, no reutilices la de `consultor` en el sistema.

---

## 4. Ubicar el server block de nginx de `consultor.une.edu.py`

```fish
sudo nginx -T | grep -B2 -A2 "server_name consultor.une.edu.py"
```

Eso te da el archivo (probablemente en `/etc/nginx/sites-available/`). Abrilo con tu editor.

---

## 5. Agregar el location de Mailpit

Dentro del mismo `server { ... }` que ya sirve la app (mismo bloque que tiene `listen 443` / `listen 80` para `consultor.une.edu.py`), agregá:

```nginx
location ^~ /mailpit/ {
    auth_basic "Mailpit";
    auth_basic_user_file /etc/nginx/.htpasswd-mailpit;

    proxy_pass http://127.0.0.1:8025/mailpit/;
    proxy_set_header Host $http_host;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}
```

**El `^~` es obligatorio, no cosmético.** Este server block ya tiene (por el deploy de Octane, ver [[octane-deploy-servidor-remoto]]) un `location` con regex por extensión — algo como `location ~* \.(css|js|svg|png|ico|woff2?)$ { try_files $uri @octane; }` — que fue justamente el fix para que `livewire.min.js` no diera 404. En nginx, **las regex ganan sobre los prefijos normales**, sin importar cuál sea "más específico" en el texto. Sin `^~`, cualquier pedido a `/mailpit/dist/app.css` o `/mailpit/favicon.svg` cae en ese location regex, va para Octane/Laravel en vez de Mailpit, y el navegador recibe HTML donde esperaba CSS/JS (`NS_ERROR_CORRUPTED_CONTENT`, bloqueo por `nosniff`, página en blanco). `^~` le dice a nginx "si este prefijo matchea, no evalúes ninguna regex" — así `/mailpit/` gana siempre, sea cual sea la extensión del archivo.

---

## 6. Validar y recargar

```fish
sudo nginx -t          # debe decir "syntax is ok" / "test is successful"
sudo systemctl reload nginx
```

---

## 7. Probar

Abrí en el navegador: `https://consultor.une.edu.py/mailpit/` (o `http://` si todavía no hay TLS — ver el plan de pruebas de login, que señala que el sitio va sin TLS por ahora). Te va a pedir el usuario/contraseña del paso 3, y después deberías ver la bandeja de Mailpit igual que por el túnel.

---

## 8. (Opcional) Restringir por IP además de la contraseña

Si querés una capa extra, agregá dentro del mismo `location`:

```nginx
allow 10.10.10.0/24;   # o tu rango de confianza
deny all;
```

(esto además del `auth_basic`, no en lugar de).

---

## Notas

- Con esto, `MAIL_MAILER=smtp` sigue apuntando a `127.0.0.1:1025` sin cambios — solo cambia cómo accedés a la UI.
- Si alguna vez migran a SMTP real, este acceso deja de tener sentido (no habrá nada nuevo en Mailpit) — se puede desactivar comentando el `location`.
- Actualizá la memoria/[[login-verificacion-smtp-pendiente]] una vez hecho esto: ya no haría falta el túnel SSH para leer correos de prueba.
- Referencia oficial del webroot/proxy de Mailpit: [HTTP proxy — Mailpit](https://mailpit.axllent.org/docs/configuration/proxy/) y [Runtime options — Mailpit](https://mailpit.axllent.org/docs/configuration/runtime-options/).
