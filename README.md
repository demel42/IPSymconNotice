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

In dem Modul geht es darum, Benachrichtigungen von Benutzern in Abhängigkeit von den Anwesenheit der Benutzer zu steuern.

Es werden 4 Wege der Benachrichtigung unterstützt
- Push-Nachricht via WebFront<br>
setzt ein oder mehrere Webfront-Instanzen voraus, die mit Endgeräten gekoppelt sind
- E-Mail<br>
setzt ein eingerichtetes Symcon-Modul **SMTP** voraus
- SMS<br>
setzt entweder das Symcon-Modul **SMS REST** (Clickatell) voraus oder das Modul **Sipgate Basic**
- ein beliebiges Script um die Anbindung sonstiger Benachrichtigungswege / Dienste zu ermögliche. (_Pushover_, _Pushsafer_ etc).

Es werden 5 Schweregrade unterstützt
- Information<br>
Kürzel _info_, Wert 1
- Hinweis<br>
Kürzel _notice_, Wert 2
- Warnung<br>
Kürzel _warn_, Wert 3
- Alarm<br>
Kürzel _alert_, Wert 4
- Fehlersuche<br>
Kürzel _Debug_, Wert 9

Die Idee ist dabei, das Benachrichtigungen in Gruppen zusammengefasst werden, die den gleichen Empfängerkreis haben.

Hierzu gibt es zwei Module:

### Benachrichtigungs-Zentrale (_NotificationCenter_)
Hier werden grundsätzlich Einstellungen gemacht, unter anderem die _Benutzer_ mit deren Kommunikationswegen angelegt.
Zu jedem Benutzer wird eine Variable angelegt, die den Anwesenheitsstatus repräsentiert. Da es ja ganz unterschiedliche Wege gibt, wie Anwesenheiten ermittelt werden,
wird das von dem Modul nicht selbst ermittelt sondern die Ermittlung (z.B. mittels _Geofency_) muss über ein Ereignis in die entsprechenden Variablen übertragen werden;
hierfür gibt es eine passende _RequestAction_.

Es gibt die Präsezstatus:
- zu Hause<br>
Wert 1
- unterwegs<br>
Wert 2
- im Urlaub<br>
Wert 3

Zu den Personenbezogenen Präsenz-Status-Variablen (_PresenceState_\<Benutzerkürzel\>_) gibt es noch drei Variablen
- _alle abwesend_ (_AllAbsent_)<br>
keine der Personen ist mehr zu Hause, ann als Trigger benutz werden um z.B. zu überprüfen, ob alle Fenster zu sind etc
- _zuletzt gegangen_ (_LastGone_)<br>
Person, die als letzt das Haus verlassen hat, danach ist das Haus "leer"
- _zuerst gekommen_ (_FirstCome_)<br>
die Person, die als erste das leere Haus betreten hat

Neben der Benachrichtigung wird auch eine "normale" Protokollierung unterstützt.

### Benachrichtigungs-Regeln (_NotifcationRule_)
Hier werden die enwünschen Empfänger (Kombination von Benutzer und Kommunikationsweg) sowie die dazugehörige Bedingung definiert.

Bedingungen gibt es folgende

- immer<br>
der eingetragenen Empfänger wird bedingungslos angesprochen

- wenn zu Haus<br>
der Empfänger wird genutzt, wenn er im Status "zu Hause" ist

- wenn unterwegs<br>
der Empfänger wird genutzt, wenn er im Status "unterwegs" ist

- erster Anwesender der Liste<br>
erste Person auf der Liste, die zu Hause ist

- letzter gegangen<br>
wenn der definierte Benutzer die Person ist, die zuletzt das Haus verlassen hat

- erster gekommen<br>
wenn der definierte Benutzer die Person ist, die zuerst in das leere Haus gekommen ist

- wenn sonst keiner<br>
Ersatzwert, wenn keine der sonstigen Bedingungen zutrifft

Die Liste der Bedingungen wird der Reihefolge nach abgearbeitet, was insbesondere für _erster Anwesender der Liste_ und _wenn sonst keiner_ relevant ist.

Eine Benachrichtigungsregel ist immer mit einer Benachrichtigungezentrale verknüpft; gibt es nur eine Zentrale kann die Angabe entfallen, da er sich die ersten Zentrale sucht.


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

Nun _Instanz hinzufügen_ anwählen und als Hersteller _(sonstiges)_ sowie als Gerät _Notification Center_ auswählen.

Für jede Regeln eine Instanz vom Typ _Notification Rule_ anlegen

### zentrale Funktion

`boolean Notification_TriggerRule(integer $InstanzID, string $Message, string $Subject, mixed $Severity, array $Params)`<br>
Löst die Benachrichtigungsregel aus und gemäß der Definition die Benachrichtigungen.
Der Aufruf erfolgt in dem entsprechenden Script, für Ablaufpläne etc gib es eine entsprechende _Aktion_.
_InstanzID_ muss vom Typ _NotificationRule_ sein.

`boolean Notification_Log(integer $InstanzID, string $Text, mixed $Severity, array $Params)`<br>
Der Aufruf erfolgt in dem entsprechenden Script, für Ablaufpläne etc gib es eine entsprechende _Aktion_.
_InstanzID_ muss vom Typ _NotificationCenter_ sein.

`int Notification_SeverityDecode(integer $InstanzID, string $ident)`
wandelt die o.g. Kennungen des _Schweregrades_ in den numerischen Wert um.

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

- 0.9 @ 11.02.2022 15:15 (test)
  - initial
