# AutoLog Pro — Installationsanleitung

Produktionsinstallation auf einem Linux-Server mit **nginx**, **PHP-FPM**, **MySQL/MariaDB**
und Konfiguration über **echte Umgebungsvariablen** (ohne `.env`-Datei im Projekt).

---

## 1. Voraussetzungen

| Komponente | Version | Prüfen mit |
|---|---|---|
| PHP | ≥ 8.2 | `php -v` |
| PHP-Extensions | `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `ctype`, `xml`, `curl` | `php -m` |
| Composer | 2.x | `composer -V` |
| MySQL / MariaDB | ≥ 8.0 / ≥ 10.6 | `mysql --version` |

```bash
sudo apt install php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
                 nginx mariadb-server composer
```

> **Node.js wird nicht benötigt.** Das Stylesheet liegt fertig unter `public/css/app.css`
> im Repository, Chart.js und Lucide kommen per CDN. Die Vite-Konfiguration und
> `resources/css` bzw. `resources/js` sind ungenutztes Laravel-Grundgerüst — keine View
> bindet `@vite` ein. Es gibt daher keinen Build-Schritt.

---

## 2. Datenbank anlegen

```sql
CREATE DATABASE autolog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'autolog'@'localhost' IDENTIFIED BY 'HIER_GEHEIMES_PASSWORT';
GRANT ALL PRIVILEGES ON autolog.* TO 'autolog'@'localhost';
FLUSH PRIVILEGES;
```

Die Anwendung legt neben den eigenen Tabellen auch `sessions`, `cache` und `jobs` an —
Sessions, Cache und Queue laufen über die Datenbank, ein Redis wird nicht benötigt.

---

## 3. Code ausrollen

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/lunasans/AutoLog.git autolog
cd autolog

composer install --no-dev --optimize-autoloader
```

Kein Asset-Build nötig — siehe Hinweis in Abschnitt 1.

---

## 4. Konfiguration über Umgebungsvariablen

Es wird **keine `.env` im Projekt** angelegt. Fehlt sie, überspringt Laravel sie und liest
alle Werte aus der Prozessumgebung. Als Quelle dient eine Datei außerhalb des Web-Roots:

```bash
sudo install -m 640 -o root -g www-data /dev/null /etc/autolog.env
sudo nano /etc/autolog.env
```

```ini
APP_NAME=AutoLog
APP_ENV=production
APP_KEY=base64:HIER_EINSETZEN
APP_DEBUG=false
APP_URL=https://deine-domain.tld

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=autolog
DB_USERNAME=autolog
DB_PASSWORD=HIER_GEHEIMES_PASSWORT

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=deine-domain.tld

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
LOG_CHANNEL=stack
LOG_LEVEL=error

# Optional: Beleg-Erkennung. Ohne Key bleibt der Upload unverändert nutzbar,
# nur das automatische Ausfüllen entfällt.
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-opus-5
ANTHROPIC_EFFORT=low
```

> **Zu `ANTHROPIC_EFFORT`:** Nicht jedes Modell kennt diesen Parameter. Haiku 4.5
> lehnt damit **jeden** Request mit `400 - This model does not support the effort
> parameter` ab, statt ihn zu ignorieren. Wer auf `ANTHROPIC_MODEL=claude-haiku-4-5`
> wechselt, muss den Wert deshalb leer lassen (`ANTHROPIC_EFFORT=`).

Den Application Key einmalig erzeugen und oben eintragen (ohne `.env` schreibt
`key:generate` nichts in eine Datei, deshalb `--show`):

```bash
cd /var/www/autolog && php artisan key:generate --show
```

> **Wichtig:** Ohne gültigen `APP_KEY` lassen sich Sessions und Cookies nicht
> entschlüsseln — jeder Login schlägt fehl. Wird der Key später getauscht, sind alle
> bestehenden Sessions ungültig.

### PHP-FPM-Pool

`/etc/php/8.3/fpm/pool.d/autolog.conf` (oder den bestehenden Pool ergänzen):

```ini
[autolog]
user = www-data
group = www-data
listen = /run/php/php8.3-fpm-autolog.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

; Rechnungs-Uploads bis 8 MB
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 12M

env[APP_ENV] = production
env[APP_DEBUG] = false
env[APP_KEY] = base64:HIER_EINSETZEN
env[APP_URL] = https://deine-domain.tld
env[DB_CONNECTION] = mysql
env[DB_HOST] = 127.0.0.1
env[DB_PORT] = 3306
env[DB_DATABASE] = autolog
env[DB_USERNAME] = autolog
env[DB_PASSWORD] = HIER_GEHEIMES_PASSWORT
env[SESSION_DRIVER] = database
env[SESSION_SECURE_COOKIE] = true
env[SESSION_DOMAIN] = deine-domain.tld
env[CACHE_STORE] = database
env[QUEUE_CONNECTION] = database
env[FILESYSTEM_DISK] = local
; Optional, siehe oben
env[ANTHROPIC_API_KEY] =
env[ANTHROPIC_MODEL] = claude-opus-5
env[ANTHROPIC_EFFORT] = low
```

FPM startet mit `clear_env = yes`, deshalb müssen die Werte hier explizit als `env[…]`
stehen — ein `export` in der Shell erreicht den Webprozess nicht.

```bash
sudo systemctl restart php8.3-fpm
```

---

## 5. Datenbank migrieren

`php artisan` läuft als CLI und sieht die FPM-Werte **nicht**. Die Env-Datei deshalb vorher
in die Shell laden:

```bash
cd /var/www/autolog
set -a; . /etc/autolog.env; set +a

php artisan migrate --force
php artisan db:seed --force      # legt admin@autolog.pro / password an
php artisan storage:link
```

---

## 6. Dateirechte

```bash
sudo chown -R www-data:www-data /var/www/autolog
sudo find /var/www/autolog -type d -exec chmod 755 {} \;
sudo find /var/www/autolog -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/autolog/storage /var/www/autolog/bootstrap/cache
sudo chmod 640 /etc/autolog.env
```

Hochgeladene Rechnungen landen in `storage/app/private/receipts` — außerhalb von `public/`
und damit nicht direkt über den Webserver abrufbar. Sie werden ausschließlich über die
Routen `/fuelings/{id}/receipt` und `/repairs/{id}/receipt` ausgeliefert, die vorher die
Besitzrechte prüfen. Das Verzeichnis entsteht beim ersten Upload automatisch.

---

## 7. Caches bauen

```bash
set -a; . /etc/autolog.env; set +a
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Stolperstein:** `config:cache` schreibt die *aktuell geladenen* Env-Werte fest in
> `bootstrap/cache/config.php`. Läuft der Befehl ohne geladene Variablen, landen `null`-Werte
> im Cache und die App bricht mit „Database connection [mysql] not configured" ab — obwohl
> FPM die Werte korrekt gesetzt hat. Immer erst die Env-Datei laden.

Nach jeder Änderung an `/etc/autolog.env`: `php artisan optimize:clear`, FPM neu laden,
dann erneut cachen.

---

## 8. nginx

Document Root ist **`public/`** — niemals das Projektverzeichnis, sonst wären
Konfiguration, Quellcode und hochgeladene Rechnungen über das Web erreichbar.

```nginx
server {
    listen 80;
    server_name deine-domain.tld;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name deine-domain.tld;

    root /var/www/autolog/public;
    index index.php;
    charset utf-8;

    ssl_certificate     /etc/letsencrypt/live/deine-domain.tld/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/deine-domain.tld/privkey.pem;

    client_max_body_size 12M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm-autolog.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_hide_header X-Powered-By;
        include fastcgi_params;
    }

    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }

    access_log /var/log/nginx/autolog-access.log;
    error_log  /var/log/nginx/autolog-error.log;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/autolog /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

TLS-Zertifikat, falls noch keines vorhanden:
```bash
sudo certbot --nginx -d deine-domain.tld
```

---

## 8b. Variante: CloudPanel

Läuft der Server unter CloudPanel, übernimmt das Panel nginx, den FPM-Pool und den
MySQL-Benutzer. Die Schritte 2, 4 (FPM-Teil), 6 und 8 entfallen dann — stattdessen:

1. **Site anlegen:** *Sites → Add Site → Create a PHP Site*, Application Preset
   **Laravel**, PHP-Version 8.2+. CloudPanel setzt den Document Root damit
   automatisch auf `htdocs/<domain>/public` und legt die Rewrite-Regeln an.
2. **Datenbank:** *Databases → Add Database*. Name, Benutzer und Passwort merken —
   sie kommen in `/etc/autolog.env`. `DB_HOST` bleibt `127.0.0.1`.
3. **Code:** per SSH als **Site-User** (nicht als root!) einspielen:
   ```bash
   ssh site-user@server
   cd /home/<site-user>/htdocs/<domain>
   rm -rf * .[!.]*                 # von CloudPanel angelegte Platzhalter entfernen
   git clone https://github.com/lunasans/AutoLog.git .
   composer install --no-dev --optimize-autoloader
   ```
4. **Umgebungsvariablen:** Die Env-Datei gehört dem Site-User, nicht `www-data`:
   ```bash
   sudo install -m 640 -o root -g <site-user> /dev/null /etc/autolog.env
   ```
   Eintragen wie in Schritt 4. Die `env[…]`-Zeilen gehören in den von CloudPanel
   verwalteten Pool: *Site → Settings → PHP-FPM Settings* (bzw. direkt
   `/etc/php/8.x/fpm/pool.d/<site-user>.conf`). Danach im Panel *PHP-FPM neu starten*.
5. **Upload-Limits:** *Site → Settings → PHP Settings* → `upload_max_filesize` auf `10M`,
   `post_max_size` auf `12M`. Das nginx-`client_max_body_size` setzt CloudPanel im
   Vhost-Editor (*Site → Vhost*).
6. **Rechte:** statt `www-data` gehört alles dem Site-User:
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```
   Eigentümer sind bereits korrekt, wenn du als Site-User geklont hast.
7. **SSL:** *Site → SSL/TLS → Let's Encrypt*.

Migration, Seed, `storage:link` und die Caches (Schritte 5 und 7) laufen unverändert —
immer als Site-User und immer mit vorher geladener Env-Datei.

---

## 9. Erster Login

`https://deine-domain.tld/login`

* Benutzer: `admin@autolog.pro`
* Passwort: `password`

**Sofort ändern** unter *Profil → Passwort ändern*. Eine Selbstregistrierung gibt es
bewusst nicht; weitere Benutzer legst du auf dem Server an:

```bash
set -a; . /etc/autolog.env; set +a
php artisan tinker --execute="App\Models\User::create(['name'=>'Vorname Nachname','email'=>'mail@domain.tld','password'=>bcrypt('startpasswort')]);"
```

Jeder Benutzer sieht ausschließlich seine eigenen Fahrzeuge, Tankvorgänge, Reparaturen
und Rechnungen.

---

## 10. Deploys

`deploy.sh` liegt im Repository — einmal ausführbar machen, danach genügt ein Aufruf:

```bash
chmod +x deploy.sh
./deploy.sh
```

Es zieht den Stand, installiert Abhängigkeiten, migriert und **erneuert die Caches**.
Der letzte Punkt ist keine Kosmetik: Wird `php artisan optimize:clear` ausgelassen,
liefert der kompilierte View weiter die alte Seite. Der neue Code ist dann zwar
ausgerollt, im Browser passiert aber nichts — ein Fehlerbild, das nach einem Bug
aussieht und keiner ist.

Wer von Hand ausrollt, braucht denselben Nachlauf:

```bash
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Läuft PHP-FPM mit OPcache, wird der Code erst nach dessen Reload sichtbar:
`sudo systemctl reload php8.3-fpm` — auf Hostings ohne `sudo` über die
Panel-Funktion „PHP neu starten".

---

## 11. Backups

Zwei Dinge müssen zusammen gesichert werden — nur die Datenbank reicht nicht, sonst
verweisen die Einträge auf fehlende Belege:

```bash
# Datenbank
mysqldump -u autolog -p autolog | gzip > autolog-$(date +%F).sql.gz

# Hochgeladene Rechnungen und Avatare
tar czf autolog-files-$(date +%F).tar.gz \
    -C /var/www/autolog storage/app/private storage/app/public
```

---

## 12. Fehlersuche

| Symptom | Ursache / Lösung |
|---|---|
| `Database connection [mysql] not configured` | `config:cache` ohne geladene Env-Datei ausgeführt → `php artisan optimize:clear`, Env laden, erneut cachen |
| `No application encryption key has been specified` | `APP_KEY` fehlt im FPM-Pool bzw. in `/etc/autolog.env` |
| Weiße Seite, keine Fehlermeldung | `storage/logs/laravel.log` prüfen; meist Rechte auf `storage/` oder `bootstrap/cache/` |
| `The stream or file … could not be opened` | `sudo chmod -R 775 storage bootstrap/cache` und Eigentümer `www-data` |
| Login schlägt trotz korrektem Passwort fehl | Nach 5 Fehlversuchen pro Minute greift die Rate-Begrenzung — kurz warten |
| Upload bricht bei großen Rechnungen ab | `upload_max_filesize` / `post_max_size` in FPM und `client_max_body_size` in nginx erhöhen |
| Seite ohne Styling | `public/css/app.css` fehlt oder ist nicht lesbar; Chart.js/Lucide werden per CDN geladen — ohne Internetzugang des Browsers fehlen Diagramm und Icons |
| Avatare werden nicht angezeigt | `php artisan storage:link` fehlt |

Logs:
```bash
tail -f /var/www/autolog/storage/logs/laravel.log
tail -f /var/log/nginx/autolog-error.log
```

---

## Sicherheits-Checkliste vor dem Livegang

- [ ] `APP_DEBUG=false` und `APP_ENV=production`
- [ ] Seed-Passwort des Admin-Kontos geändert
- [ ] HTTPS aktiv, `SESSION_SECURE_COOKIE=true`
- [ ] nginx Document Root zeigt auf `public/`
- [ ] `/etc/autolog.env` mit `chmod 640`, Eigentümer `root:www-data`
- [ ] Keine `.env` im Projektverzeichnis
- [ ] MySQL-Benutzer hat nur Rechte auf die Datenbank `autolog`
- [ ] Backup von Datenbank **und** `storage/app/private` eingerichtet
