# IP-Symcon Energy & Meter Management

Todo: Variablen IDs über IPS_GetObjectIDByIdent holen

Energie- und Zählerverwaltung für **IP-Symcon** mit automatischer Skalierung, Energiemanagement, Monitoring und Visualisierung.

Dieses Projekt wurde im Rahmen einer **Meisterprüfung (HVG241)** entwickelt und zeigt ein vollständiges Konzept für:

- Energiemanagement
- Lastverteilung
- Zähler-Skalierung
- Monitoring & Fehlerdiagnose
- Sicherheitsüberwachung
- Visualisierung im WebFront
- automatischer Script-Import

---

# Funktionen

## Automatischer Script-Importer

Dieses Projekt enthält ein eigenes **IP-Symcon Modul**, das Skripte und Medien automatisch importiert.

Der Importer ermöglicht die Installation des gesamten Projekts **ohne Modulstore und ohne Internetverbindung**.

Das Modul:

- durchsucht automatisch den Ordner `/scripts`
- importiert alle `.php` Dateien als **IP-Symcon Skripte**
- erstellt fehlende Skripte automatisch
- erkennt vorhandene Skripte und überschreibt sie nicht
- importiert Bilder aus dem `/media` Ordner
- legt diese als **Symcon Medienobjekte** an

Damit kann das gesamte Projekt sehr einfach verteilt werden.

### Vorteile

- Offline Installation möglich
- kein Zugriff auf Modulstore notwendig
- automatische Scriptverwaltung
- einfache Projektverteilung
- keine manuelle Erstellung der Skripte in Symcon

### Funktionsweise

Beim Aktivieren des Moduls:

1. Das Modul durchsucht den Ordner `scripts`
2. Alle PHP-Dateien werden automatisch als Skripte angelegt
3. Der Skriptinhalt wird direkt in Symcon gespeichert
4. Medien aus dem Ordner `media` werden importiert
5. Existierende Objekte werden erkannt und nicht doppelt erstellt

### Ordnerstruktur

```
/scripts
    Energiemanagement.php
    Energiemonitoring.php
    ZaehlerManagement.php
    Sicherheitsstatus.php
    Fehlerverlauf.php

/media
    dashboard.png
    schema.png
```

Das Modul erstellt daraus automatisch:

- Symcon Skripte
- Medienobjekte
- Referenzen im Modul

---

## Energiemanagement

Das System steuert Verbraucher auf Basis der verfügbaren Energie.

Features:

- PV-Überschussladung
- Batterieintegration
- Fair-Share-Lastverteilung
- Hausanschlussbegrenzung
- Zeitfenster-Priorisierung
- automatische Wallbox-Leistungssteuerung
- Wärmepumpenfreigabe

Visualisierung:

- Live-Dashboard
- Leistungsübersicht
- Statusanzeige

---

## Energiemonitoring

Überwachung der Energiebilanz des Systems.

Funktionen:

- Berechnung des Gesamtverbrauchs
- Vergleich mit Netzbezug
- Plausibilitätsprüfungen
- Statusüberwachung
- Verlauf bei Statuswechseln

Erkennt z. B.:

- falsche Leistungswerte
- unplausible Energieflüsse
- Hausanschlussüberlastung
- Wallbox-Fehlzustände

---

## Zähler-Skalierung

Das Projekt enthält ein Messkonzept zur **virtuellen Aufteilung eines Referenzzählers**.

Ein EASTRON-Zähler dient als Referenz und wird prozentual auf mehrere Unterzähler verteilt.

Beispiel:

Referenzzähler (Messwandler)  
↓  
Z4 Allgemeinverbrauch  
Z5 Mieter EG  
Z6 Mieter 1.OG  
Z7 Mieter 1.OG  
Z8 Mieter 2.OG  
Z9 Mieter 2.OG  

Zusätzliche Funktionen:

- Differenzzähler zur Kontrolle
- automatische Archivierung
- automatische Variablenerstellung
- Visualisierung im Dashboard

---

## Zähler-Dashboard

Das Dashboard zeigt alle Zählerwerte übersichtlich an.

Features:

- automatische Erkennung aller `Z*_` Variablen
- numerische Sortierung
- Anzeige mit Einheiten
- Statusprüfung
- Live-Visualisierung

---

# Sicherheitsüberwachung

Das System enthält ein Sicherheitsmodul mit Anzeige für:

- Einbruchmeldeanlage
- Wassermelder
- Brandmeldeanlage

Funktionen:

- zentraler Alarmstatus
- farbliche Anzeige
- Fehlerhistorie
- automatische HTML-Dashboards

---

# Kommunikations-Fallback

Überwachung der Kommunikation zwischen:

- KNX
- MQTT
- Loxone
- IP-Symcon

Das System erkennt:

- Busausfälle
- fehlende Daten
- Kommunikationsprobleme

---

# Architektur

IP-Symcon
│
├── Energiemanagement
│   Steuerung der Energieflüsse und Verbraucher
│   ├── Energiesteuerung
│   ├── Fair-Share Lastverteilung
│   └── Hausanschlussüberwachung
│
├── Energiemonitoring
│   Analyse und Überwachung des Energieverbrauchs
│   ├── Verbrauchsberechnung
│   ├── Plausibilitätsprüfung
│   └── Verlauf
│
├── Zählerverwaltung
│   Verwaltung und Skalierung der Energiezähler
│   ├── Zähler-Skalierung
│   ├── Differenzzähler
│   └── Dashboard
│
├── Sicherheitsüberwachung
│   Überwachung sicherheitsrelevanter Zustände
│   ├── Alarmstatus
│   └── Fehlerhistorie
│
└── Kommunikationsüberwachung
    Überwachung externer Systeme und Busverbindungen
    └── Fallback-Logik

---

# Installation

## Voraussetzungen

- IP-Symcon 7.x oder neuer
- Archivinstanz aktiviert

Benötigte Variablen im System:

PV-Leistung  
Hausverbrauch  
Batterie-SOC  
Wallbox-Leistung  
Netzbezug  

---

## Installation

Repository herunterladen:

```
git clone https://github.com/username/ip-symcon-energy-management
```

Danach:

1. Modulordner in das Symcon Modulverzeichnis kopieren
2. Modul im Modulstore aktualisieren
3. Modulinstanz erstellen
4. Skripte werden automatisch importiert

---

# Dashboard

Das System erzeugt automatisch mehrere HTML-Dashboards:

- Energiemanagement Übersicht
- Zähler Dashboard
- Sicherheitsstatus
- Fehlerverlauf

Diese werden direkt im **IP-Symcon WebFront** angezeigt.

---

# Logging

Automatisch aktiviert über die **IP-Symcon Archivinstanz**.

Gespeichert werden:

- Energieflüsse
- Zählerstände
- Systemstatus
- Fehlerverlauf

---

# Projektstatus

Projekt entwickelt für:

**Meisterprüfung Elektrotechnik (HVG241)**

Status:  
Produktiv getestet

---

# Lizenz

MIT License

---

# Autor

Mike Dorr

Projekt: Meisterprüfung Elektrotechnik  
System: IP-Symcon Energiemanagement
