# realPMS

Ein wunderschönes PMS – jetzt mit ersten Setup-Skripten.

## Installation der Datenbanktabellen

1. Erstelle eine `.env` oder `install.config.php` Datei mit den MySQL-Zugangsdaten **und** einem API-Token:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=realpms
   DB_USERNAME=realpms_user
   DB_PASSWORD=geheimespasswort
    API_TOKEN=mein-ultra-sicheres-token
   ```
   Alternativ kannst du `install.config.php` auf Basis von `install.config.php.example` anpassen.
2. Führe den Installer per CLI aus:
   ```bash
   php install.php
   ```
   oder rufe `install.php` im Browser auf. Die Skriptausgabe bestätigt die Erstellung aller Tabellen.

> **Hinweis:** Stelle sicher, dass der Webserver Schreibrechte auf `backend/storage` besitzt – hier landen u. a. das Rechnungslogo
und temporäre PDF-Dateien.

## Repository-Update über das Backend

Für ein automatisiertes Update des aktuell ausgecheckten Branches steht `backend/update.php` bereit.

1. Setze einen sicheren Token als Environment Variable, z. B. in der VirtualHost-/FPM-Konfiguration:
   ```bash
   export PMS_UPDATE_SECRET="mein-sicherer-token"
   ```
2. Führe das Update per CLI aus:
   ```bash
   php backend/update.php mein-sicherer-token
   ```
   oder rufe per HTTP `https://dein-host/backend/update.php?token=mein-sicherer-token` auf.
3. Das Skript führt `git pull` für den aktuellen Branch aus und gibt die Logausgabe als JSON zurück.
4. Falls Git auf dem Webserver nicht verfügbar ist, lädt das Skript automatisch ein ZIP-Archiv von GitHub:
   - Standardmäßig wird `https://github.com/rinkelzz/realpms` mit dem Branch `main` verwendet.
   - Über Umgebungsvariablen kannst du die Quelle anpassen:
     - `PMS_UPDATE_REPO_SLUG` (z. B. `meinkonto/meinrepo`)
     - `PMS_UPDATE_BRANCH` (z. B. `production`)
     - `PMS_UPDATE_ARCHIVE_URL` (kompletter Archiv-Link, überschreibt die beiden Werte oben).
   - Stelle sicher, dass die PHP-Erweiterung `ZipArchive` aktiviert ist, damit das Archiv entpackt werden kann.

> **Hinweis:** Stelle sicher, dass der Webserver-Benutzer die notwendigen Berechtigungen für das Git-Repository besitzt. Andernfalls schlägt das Update fehl.

## Web-Frontend

Neben der reinen API steht jetzt ein leichtgewichtiger Administrations-Client zur Verfügung:

- `public/index.html` bildet ein Single-Page-Dashboard für Reservierungen, Zimmer, Housekeeping, Fakturierung, Berichte, Nutzer, Firmen und Gäste.
- Hinterlege den API-Token im Kopfbereich der Anwendung – er wird im Browser-Storage gespeichert.
- Starte die Oberfläche lokal zum Beispiel mit
  ```bash
  php -S 0.0.0.0:8080 -t public
  ```
  und rufe anschließend `http://localhost:8080/` im Browser auf. Die API muss parallel (z. B. über Apache/Nginx oder einen zweiten PHP-Built-in-Server) erreichbar sein.
- Im Bereich „Firmen“ legst du Unternehmensprofile an und bearbeitest sie; Gäste können direkt im Gästebereich einer Firma zugeordnet oder dort editiert werden.
- Die Dashboard-Kalenderansicht sortiert Zimmer nach Kategorie, markiert Angereist/Bezahlt/Abgereist farblich und lässt sich per Umschalter zwischen Gast- und Firmenname umstellen; ein Klick auf eine belegte Zelle springt direkt zur entsprechenden Reservierung.
- Über den Button „Farben anpassen“ im Dashboard gelangst du direkt zu den Kalender-Einstellungen und kannst alle Status-Farben dauerhaft speichern oder zurücksetzen.
- Die Reservierungsliste bietet farbige Schnellaktionen für Check-in, Zahlungseingang, Check-out und No-Show – ideal, um Gäste schnell als angereist, abgereist oder No-Show zu markieren.
- Im Reservierungsformular kannst du aktive Artikel/Zusatzleistungen auswählen; Mengen werden automatisch mit der Zimmerkapazität abgeglichen.
- Der Fakturierungsbereich enthält ein Artikel-Panel samt CRUD-Funktionalität, eine Rechnungsmaske mit MwSt.-Berechnung sowie Direktlinks zum PDF-Export.
- Unter „Einstellungen“ lässt sich das Rechnungslogo als PNG/JPEG hochladen; die Vorschau zeigt unmittelbar das spätere Layout auf der Rechnung.

## REST API für den MVP-Funktionsumfang

Nach dem erfolgreichen Datenbank-Setup stellt `backend/api/index.php` eine schlanke REST-API bereit, die sämtliche MVP-Bereiche abdeckt. **Authentifiziere jede Anfrage (außer den Gastportal-Endpoints) mit** `X-API-Key: <API_TOKEN>` oder `?token=`.

### Front-Office & Reservierungen
- `GET /backend/api/reservations?status=confirmed&from=2024-01-01&to=2024-01-31` – Übersicht über Reservierungen inklusive Zimmerzuweisungen.
- `POST /backend/api/reservations` – Legt Gäste (falls nötig), Reservierung, Rate-Plan und Zimmerzuweisung in einem Schritt an. Beispiel-Payload:
  ```json
  {
    "guest": {"first_name": "Max", "last_name": "Mustermann", "email": "max@example.com"},
    "check_in_date": "2024-02-10",
    "check_out_date": "2024-02-14",
    "rooms": [{"room_id": 1, "nightly_rate": 120, "currency": "EUR"}],
    "rate_plan_id": 2,
    "status": "confirmed",
    "total_amount": 480,
    "notes": "Late arrival",
    "articles": [{"article_id": 3, "multiplier": 4}]
  }
  ```
- `POST /backend/api/reservations/{id}/status` – Aktualisiert den Reservierungsstatus (`checked_in`, `paid`, `checked_out`, `no_show` usw.) inklusive Logbuch, Zimmer- und Housekeeping-Updates. Aus Kompatibilitätsgründen funktionieren weiterhin die Kurzpfade `/check-in`, `/check-out`, `/pay` und `/no-show`.
- `POST /backend/api/reservations/{id}/documents` – Hinterlegt Meldescheine oder andere Dateien (es werden Metadaten gespeichert, die Dateiablage erfolgt extern).
- `GET|POST|PATCH /backend/api/guests` – Verwalten von Gästestammdaten inklusive Firmenzuordnung und optionaler Suchfunktion via `?search=`.
- `GET|POST|PATCH /backend/api/companies` – Firmenstammdaten für Reisebüros, Unternehmen oder Agenturen; Gäste lassen sich diesen Einträgen zuweisen.

### Housekeeping & Maintenance
- `GET /backend/api/rooms?status=in_cleaning` – Filterbare Raumliste inkl. Raumtyp.
- `PATCH /backend/api/rooms/{id}` – Aktualisiert Status (z. B. `available`, `in_cleaning`, `out_of_order`) und protokolliert automatisch einen Housekeeping-Log.
- `GET|POST|PATCH /backend/api/housekeeping/tasks` – Aufgabenlisten für Reinigung & Technik, inkl. optionaler Raum- und Mitarbeiterzuweisung.

### Fakturierung & Zahlungen
- `POST /backend/api/invoices` – Erstellt Rechnungen mit beliebig vielen Positionen; Netto-/Steuer-/Brutto-Summen werden automatisch berechnet.
- `POST /backend/api/payments` – Verbucht Zahlungen (Bar, Karte, externes Gateway) und verknüpft sie mit Rechnungen.
- `GET /backend/api/invoices/{id}/pdf` – Rendert die Rechnung als PDF (inkl. Rechnungslogo, Netto-/MwSt.-Ausweis und Artikellisten) über die mitgelieferte FPDF-Library.
  - **Wichtig:** Lade die Standard-Schriftdateien der FPDF-Library (z. B. `helvetica.php`, `courier.php`) manuell in `backend/lib/font/` hoch; sie sind aus lizenzrechtlichen Gründen nicht im Repository enthalten.
- `GET|POST|PATCH|DELETE /backend/api/articles` – Verwalte abrechenbare Artikel (z. B. Frühstück, Parkplatz). Die Felder `charge_scheme`, `unit_price` und `tax_rate` sorgen dafür, dass Mehrwertsteuer nach deutschem Recht (Standard 19 %, optional z. B. 7 %) korrekt in Rechnungen einfließt.

### Berichte & Analytics
- `GET /backend/api/reports/occupancy?start=2024-02-01&end=2024-02-07` – Tagesbasierte Auslastungsquote.
- `GET /backend/api/reports/revenue?start=2024-02-01&end=2024-02-29` – Umsatzübersicht inkl. Steuern und Zahlungsarten.
- `GET /backend/api/reports/forecast` – Einfache Forecast-Kennzahlen auf Basis kommender Reservierungen.

### Nutzer- & Rollenverwaltung
- `GET|POST /backend/api/users` – Legt Benutzer mit Passwort-Hash an und weist Rollen zu.
- `GET|POST /backend/api/roles`, `POST /backend/api/roles/{id}/permissions` sowie `GET|POST /backend/api/permissions` – Rollen-/Rechteverwaltung mit Audit-Logs über `reservation_status_logs` bzw. `audit_logs`.

### Integrationen (Platzhalter)
- `GET /backend/api/integrations` – Liefert den aktuellen Verbindungsstatus zu Channel-Managern, POS, Türschließsystemen und Buchhaltung.

### Einstellungen
- `GET /backend/api/settings` – Liefert die verfügbaren Einstellungsbereiche.
- `GET|PUT|DELETE /backend/api/settings/calendar-colors` – Liest, speichert oder setzt die Kalenderfarben zurück. `PUT` erwartet ein Objekt nach dem Schema `{ "colors": { "confirmed": "#2563eb", "checked_in": "#16a34a", ... } }`.
- `GET|PUT|DELETE /backend/api/settings/invoice-logo` – Lädt das Rechnungslogo als Base64-Daten, speichert neue Uploads oder entfernt vorhandene Logos.

### Gästeportal / Self-Service
- `GET /backend/api/guest-portal/reservations/{confirmation}` – Gäste sehen Reservierungsdetails, Zimmer und Dokumente.
- `POST /backend/api/guest-portal/reservations/{confirmation}/check-in` – Self-Check-in; aktualisiert Reservierungs- und Zimmerstatus.
- `POST /backend/api/guest-portal/reservations/{confirmation}/documents` – Upload von Meldescheinen (Metadaten) durch den Gast.
- `POST /backend/api/guest-portal/reservations/{confirmation}/upsell` – Gäste buchen Zusatzleistungen, die als `service_orders` im Backoffice landen.

> Tipp: Für lokale Tests bietet sich `php -S 0.0.0.0:8000 -t backend` an. Die API ist dann unter `http://localhost:8000/api/index.php/...` erreichbar.
