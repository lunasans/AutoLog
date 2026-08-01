# AutoLog Pro 🏎️💨

AutoLog Pro ist eine moderne, webbasierte Anwendung zur Verwaltung deiner Fahrzeugflotte. Behalte den Überblick über Kraftstoffverbrauch, Wartungskosten und wichtige Termine wie die Hauptuntersuchung (HU) – alles in einem eleganten, responsiven Design.

## ✨ Features

- **Fleet Intel Dashboard**: Eine visuelle Übersicht über alle deine Fahrzeuge, investierte Kosten und Durchschnittsverbrauch.
- **Vereinfachtes Kilometer-Logging**: Setze einen Start-Kilometerstand und logge nur noch deine gefahrenen "Trip-KM". Den Rest erledigt das System.
- **Wartung & Service**: Tracke Reparaturen und Service-Aufenthalte. Kilometerstände sind hier optional, damit du sofort loslegen kannst.
- **Visuelle Trends**: Analysiere deinen Verbrauch mit modernen, geschmeidigen Verlaufsdiagrammen.
- **Branding**: Automatische Integration von Hersteller-Logos für einen premium Look.
- **Termin-Erinnerung**: Automatische Warnung, wenn die nächste HU fällig wird.
- **Volle Kontrolle**: Bearbeite Fahrzeugprofile nachträglich oder lösche einzelne Einträge aus der Historie.

## 🚀 Installation & Deployment

Das Projekt basiert auf dem **Laravel-Framework**.

### Lokal einrichten
1. Repo klonen: `git clone https://github.com/lunasans/AutoLog.git`
2. Abhängigkeiten installieren: `composer install`
3. Umgebung konfigurieren: `cp .env.example .env` (Datenbankdaten eintragen)
4. Key & DB: `php artisan key:generate && php artisan migrate && php artisan db:seed`
5. Starten: `php artisan serve`

Node.js wird nicht benötigt — das Stylesheet liegt fertig unter `public/css/app.css`.

### Deployment (Produktion)
Die vollständige Server-Installation — MySQL, Konfiguration über Umgebungsvariablen statt `.env`, nginx bzw. CloudPanel, Deploy-Skript, Backups und Fehlersuche — steht in **[INSTALL.md](INSTALL.md)**.

## 🛠️ Tech Stack

- **Backend**: Laravel 11.x (PHP 8.2+)
- **Frontend**: Blade-Templates, Vanilla CSS (Glassmorphism Design)
- **Charts**: Chart.js
- **Icons**: Lucide Icons

## 📄 Lizenz

Dieses Projekt ist unter der MIT-Lizenz lizenziert.
