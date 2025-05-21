# SomfyRTS

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-7.0-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.0.20250518-orange.svg?style=flat-square)](https://github.com/Wilkware/Rfx2COM)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/Rfx2COM/style.yml?branch=main&label=CheckStyle&style=flat-square)](https://github.com/Wilkware/Rfx2COM/actions)

Mit diesem Modul können Somfy RTS- sowie ASA-Motoren über einen RFXtrx433E, RFXtrx433XL, RFX433XL, RFX-433 oder RFX-433EMC angesteuert werden.

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#user-content-4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#user-content-5-statusvariablen-und-profile)
6. [Visualisierung](#user-content-6-visualisierung)
7. [PHP-Befehlsreferenz](#user-content-7-php-befehlsreferenz)
8. [Versionshistorie](#user-content-8-versionshistorie)

### 1. Funktionsumfang

Das Modul sendet entsprechende Befehlssequenzen zur Steuerung von mit Somfy-Antrieben ausgestatteten Markisen und Volants.


Was macht bzw. was kann das Modul?

- Unterstützung von RTS und ASA Motoren
- Ein- und ausfahren (Rin/Raus bzw. Hoch/Runter), Stoppen der Fahrt (Stop), sowie das Anfahren einer vorprogrammierten Position (My)
- Verschiedenste Konfigurationsmöglichkeiten zur visuellen Darstellung
- Unterstützung der TileVisu via HTML-SDK

### 2. Voraussetzungen

* IP-Symcon ab Version 7.0

### 3. Installation

* Über den Modul Store die Bibliothek _Rfx2COM_ installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/Rfx2COM` oder `git://github.com/Wilkware/Rfx2COM.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter "Instanz hinzufügen" ist das _'SomfyRTS'_-Modul unter dem Hersteller _'RFXCOM'_ aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> Empfangseinheit …

Name                        | Beschreibung
--------------------------- | ----------------------------------
Typ                         | Somfy (Sub)typ [Somfy RTS(RFY) oder ASA]
Geräte-ID                   | Unit ID (000001 bis FFFFFF)
Gerätecode                  | Unit Code (00 bis FF)

> Visualisierung …

Name                                   | Beschreibung
-------------------------------------- | ----------------------------------
Darstellungsart                        | 'Markise' oder 'Volant'
Position der Fernbedienungstasten      | Position der Tasten auf der Kachel-Visu ('Open' , 'Links', 'unten' oder 'Rechts')
Statusvariable (Ein-/Ausgefahren)      | Variable (bool) für Rückmeldung des Status ob ein- bzw. ausgefahren ist (true=EIN, false=AUS)
Kachelhintergrundfarbe (Eingefahren)   | Farbauswahl für den Zustand 'Geschlossen/Eingefahren'
Kachelhintergrundfarbe (Ausgefahren)   | Farbauswahl für den Zustand 'Geöffnet/Ausgefahren'

Aktionsbereich:

Aktion         | Beschreibung
-------------- | ------------------------------------------------------------
MY/STOP        | Startet das Ausfahren auf vorprogrammierte Position bzw. stoppt die Fahrt an aktueller Position
HOCH/REIN      | Startet das Einfahren der Markise bzw. Volants
RUNTER/RAUS    | Startet das Ausfahren der Markise bzw. Volants

### 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Name                        | Typ       | Beschreibung
--------------------------- | --------- | ----------------
Fernbedienung               | Integer   | Kommando das (zuletzt) gesendet wurde bzw. gesendet werden soll.

Folgendes Profil wird angelegt:

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------
R2C.Awning           | Integer   | My/Stop, Rein oder Raus (0, 1, 3)
R2C.Valance          | Integer   | My/Stop, Hoch oder Runter (0, 1, 3)

### 6. Visualisierung

Man kann direkt das Modul in die Tile Visu verlinken. Die Darstellung der dadurch entstehenden Kachel kann über die  
Konfiguration der Visualisierung gesteuert werden.  
Man könnte aber auch nur die Statusvariablen in die Visualisierung verlinken.

### 7. PHP-Befehlsreferenz

Das Modul stellt keine direkten Funktionsaufrufe zur Verfügung.

### 8. Versionshistorie

v1.0.20250518

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der IP-Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
