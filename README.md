# IPSymconNotification

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang


## 2. Voraussetzungen

- IP-Symcon ab Version 6.0

## 3. Installation

### a. Laden des Moduls

**Installieren über den Module-Store**

Die Webconsole von IP-Symcon mit `http://\<IP-Symcon IP\>:3777/console/` öffnen.

Anschließend oben rechts auf das Symbol für den Modulstore klicken

Im Suchfeld nun *XXXXX* eingeben, das Modul auswählen und auf Installieren drücken.

**Installieren über die Modules-Instanz**

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen: `https://github.com/demel42/IPSymconNotification.git`
und mit _OK_ bestätigen. Ggfs. auf anderen Branch wechseln (Modul-Eintrag editieren, _Zweig_ auswählen).

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

### b. Einrichtung in IPS

Nun _Instanz hinzufügen_ anwählen und als Hersteller _(sonstiges)_ sowie als Gerät _Symcon Integrity-Check_ auswählen.

### zentrale Funktion

## 5. Konfiguration:

### Variablen

| Eigenschaft                                       | Typ     | Standardwert | Beschreibung |
| :------------------------------------------------ | :------ | :----------- | :----------- |
| Instanz deaktivieren                              | boolean | false        | Instanz temporär deaktivieren |
|                                                   |         |              | |

## 6. Anhang

GUIDs

- Modul: `{1E92B006-FB7D-6020-B296-2F31BC2892C4}`
- Instanzen:
  - NotificationCenter: `{4CF21C1E-B0F8-5535-8B5D-01ADDDB5DFD7}`
  - NotificationRule: `{2335FF1E-9628-E363-AAEC-11DE75788A13}`

## 7. Versions-Historie

- 0.9 @ 11.02.2022 10:04 (test)
  - initial
