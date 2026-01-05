# Frontend Modularisierung

Diese Dokumentation beschreibt die modulare Struktur des Frontend-Codes.

## Überblick

Das Frontend wurde in kleinere, wartbare Module aufgeteilt, um die Codequalität zu verbessern und die Wartung zu erleichtern. Die ursprüngliche `app.js` (>3900 Zeilen) wurde in logische Module zerlegt.

## Module

### `js/utils.js` - Utility-Funktionen

**Zweck**: Allgemeine Hilfsfunktionen für Datums-, Format- und Validierungsoperationen.

**Hauptfunktionen**:
- `toLocalISODate(date)` - Konvertiert Date-Objekt zu ISO-String (YYYY-MM-DD)
- `parseISODate(value)` - Parst ISO-Datumsstring zu Date-Objekt
- `addDays(date, amount)` - Fügt Tage zu einem Datum hinzu
- `formatDate(value)` - Formatiert Datum in deutschem Format
- `formatDateTime(value)` - Formatiert DateTime in deutschem Format
- `formatCurrency(amount, currency)` - Formatiert Währungsbeträge
- `escapeHtml(value)` - Schützt vor XSS-Angriffen
- `calculateNightsBetween(checkIn, checkOut)` - Berechnet Anzahl der Nächte
- `normalizeHexColorInput(value)` - Normalisiert Hex-Farbwerte
- `hexToRgb(color)` - Konvertiert Hex zu RGB
- `rgbaFromHex(color, alpha)` - Konvertiert Hex zu RGBA
- `getReadableTextColor(color)` - Bestimmt Textfarbe für Hintergrundfarbe

**Tests**: 51 Unit-Tests in `__tests__/utils.test.js`

### `js/state.js` - Zustandsverwaltung

**Zweck**: Zentrale Verwaltung des Anwendungsstatus und Konstanten.

**Hauptexporte**:
- `state` - Globales Zustandsobjekt mit Token, Entitäten und UI-Status
- `API_BASE` - Basis-URL für API-Anfragen
- `CALENDAR_STATUS_ORDER` - Reihenfolge der Kalenderstatus
- `CALENDAR_COLOR_DEFAULTS` - Standard-Kalenderfarben
- `ARTICLE_SCHEME_LABELS` - Labels für Abrechnungsschemas
- `RESERVATION_STATUS_LABELS` - Labels für Reservierungsstatus
- `INVOICE_STATUS_LABELS` - Labels für Rechnungsstatus
- `getRoomTypeById(id)` - Hilfsfunktion zum Abrufen von Zimmertypen
- `getRatePlanById(id)` - Hilfsfunktion zum Abrufen von Tarifen

### `js/api.js` - API-Kommunikation

**Zweck**: Zentrale Schnittstelle für alle Backend-API-Anfragen.

**Hauptfunktionen**:
- `apiFetch(path, options)` - Basis-Funktion für API-Anfragen mit Authentifizierung
- `requireToken()` - Prüft ob API-Token gesetzt ist
- `loadDashboard(force)` - Lädt Dashboard-Daten
- `loadReservations(force)` - Lädt Reservierungen
- `loadCalendarColors(force)` - Lädt Kalenderfarben
- `loadRooms(force)` - Lädt Zimmer
- `loadArticles(force)` - Lädt Artikel
- `loadGuests(force)` - Lädt Gäste
- `loadCompanies(force)` - Lädt Firmen
- `updateReservationStatus(id, status)` - Aktualisiert Reservierungsstatus
- `searchGuests(term, limit)` - Sucht Gäste

## Nutzung

### ES6-Module laden

Die Module werden als ES6-Module geladen. In `index.html`:

```html
<script type="module" src="js/app.js"></script>
```

### Module importieren

In JavaScript-Dateien:

```javascript
// Utils importieren
import { formatDate, formatCurrency, escapeHtml } from './utils.js';

// State importieren
import { state, CALENDAR_STATUS_ORDER } from './state.js';

// API importieren
import { apiFetch, loadReservations } from './api.js';

// Beispiel: Datum formatieren
const formattedDate = formatDate('2024-01-15');

// Beispiel: API-Anfrage
const reservations = await loadReservations();
```

## Tests ausführen

```bash
# Alle Tests ausführen
npm test

# Tests im Watch-Modus
npm run test:watch
```

## Best Practices

1. **Reine Funktionen**: Utility-Funktionen sollten keine Seiteneffekte haben
2. **JSDoc-Kommentare**: Alle öffentlichen Funktionen dokumentieren
3. **Unit-Tests**: Neue Funktionen mit Tests absichern
4. **Modulare Struktur**: Zusammengehörige Funktionen in einem Modul
5. **Export/Import**: Nur benötigte Funktionen exportieren

## Zukünftige Erweiterungen

- [ ] `ui.js` - UI-Rendering-Funktionen
- [ ] `reservations.js` - Reservierungsspezifische Logik
- [ ] `calendar.js` - Kalender-/Belegungsanzeige
- [ ] `guests.js` - Gästeverwaltung
- [ ] `billing.js` - Artikel und Rechnungen

## Migration von app.js

Die Hauptanwendungsdatei `app.js` wird schrittweise refaktoriert:
1. Utility-Funktionen nach `utils.js` verschoben
2. Konstanten und State nach `state.js` verschoben
3. API-Funktionen nach `api.js` verschoben
4. Weitere Module folgen

Die ursprüngliche `app.js` bleibt vorerst bestehen und wird später durch eine kleinere Datei ersetzt, die hauptsächlich Event-Handler und Initialisierung enthält.
