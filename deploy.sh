#!/usr/bin/env bash
#
# Rollt den aktuellen Stand aus. Aus dem Projektverzeichnis heraus aufrufen:
#
#   ./deploy.sh
#
# Ohne sudo lauffähig - alles, was hier passiert, gehört dem Anwendungsbenutzer.
# Läuft PHP-FPM mit OPcache, wird der Code erst nach dessen Reload sichtbar;
# siehe Hinweis am Ende.
set -euo pipefail

cd "$(dirname "$0")"

# Konfiguration kommt aus der Prozessumgebung, nicht aus einer .env im Projekt.
# Fehlt die Datei, läuft der Rest trotzdem - dann sind die Werte schon gesetzt.
if [ -f /etc/autolog.env ]; then
    set -a; . /etc/autolog.env; set +a
fi

branch="$(git rev-parse --abbrev-ref HEAD)"
if [ "$branch" != "main" ]; then
    echo "Abbruch: HEAD steht auf '$branch', ausgerollt wird nur main." >&2
    exit 1
fi

git pull --ff-only
composer install --no-dev --optimize-autoloader

php artisan migrate --force

# Der Pflichtteil: ohne optimize:clear liefert der kompilierte View weiter die
# alte Seite - der Code ist dann zwar da, aber im Browser passiert nichts.
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo
echo "Fertig. Läuft PHP-FPM mit OPcache, jetzt noch neu laden lassen:"
echo "  sudo systemctl reload php8.3-fpm"
echo "Auf Hostings ohne sudo erledigt das die Panel-Funktion 'PHP neu starten'."
