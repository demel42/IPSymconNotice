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

Hierzu gibt es drei Module:

### Benachrichtigungs-Basis (_NotificationBase_)
Hier werden grundsätzlich Einstellungen gemacht, unter anderem die _Benutzer_ mit deren Kommunikationswegen angelegt.
Zu jedem Benutzer wird eine Variable angelegt, die den Anwesenheitsstatus repräsentiert. Da es ja ganz unterschiedliche Wege gibt, wie Anwesenheiten ermittelt werden,
wird das von dem Modul nicht selbst ermittelt sondern die Ermittlung (z.B. mittels _Geofency_) muss über ein Ereignis in die entsprechenden Variablen der Benachrichtigungs-Basis übertragen werden;
hierfür gibt es eine passende _RequestAction_.

Es gibt die Präsezstatus:
- zu Hause<br>
Wert 1
- unterwegs<br>
Wert 2
- im Urlaub<br>
Wert 3

Zu den personenbezogenen Präsenz-Status-Variablen (Variable *PresenceState_\<Benutzerkürzel\>*) gibt es noch drei Variablen
- _alle abwesend_ (Variable _AllAbsent_)<br>
keine der Personen ist mehr zu Hause, ann als Trigger benutz werden um z.B. zu überprüfen, ob alle Fenster zu sind etc
- _zuletzt gegangen_ (Variable _LastGone_)<br>
Person, die als letzt das Haus verlassen hat, danach ist das Haus "leer"
- _zuerst gekommen_ (Variable _FirstCome_)<br>
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

Eine Benachrichtigungsregel ist immer mit einer Benachrichtigungs-Basis verknüpft; gibt es nur eine Benachrichtigungs-Basis kann die Angabe entfallen, da die Regel sich dann die
erste Instanz sucht.

### Benachrichtigungs-Ereignis (_NotificationEvent_)
Hiermit können Benachrichtigungs-Regeln verzögert bzw. wiederholt aufgerufen werden
Neben der Timerfunktion können auch Bedingungen angegeben werden, die gültig sein müssen, damit das Ereignis anläuft bzw. weiterläuft (die Bedingungen werden bei jeder
Wiederholung neu geprüft).

Gemäß den angegebenen Einstellungen wird eine entsprechende Benachrichtigungs-Regel aufgerufen, dabei können durch vielfältige EInstellungen Naxchrichtentext, Betreff und Schwergrad angepasst werden.

Ein laufendes Ereignis wird standardmässig durch erneuten Aufruf nicht wieder ausgelöst (siehe _TriggerEvent_).


## 2. Voraussetzungen

- IP-Symcon ab Version 6.0

## 3. Installation

### a. Installation des Moduls

Im ![Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff *Symcon Notification* zu finden.
Alternativ kann das Modul im ![Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/IPSymconNotification.git` installiert werden.

### b. Einrichtung in IPS

Nun _Instanz hinzufügen_ anwählen und als Hersteller _(sonstiges)_ sowie als Gerät _Notification Base_ auswählen.

Für jede Regel muss eine Instanz vom Typ _Notification Rule_ angelegt werden, bei Bedarf _Notification Event_

## 4. Funktionsreferenz

### _NotificationBase_
`boolean Notification_Log(integer $InstanzID, string $Text, mixed $Severity, array $Params)`<br>
Erzeugt einen Eintrag in dem Protokoll vom _NotificationBase_.
_Severity_ kann als numerischer Wert oder als Abkürzung übergeben werden (siehe oben)
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

### _NotificationRule_
`boolean Notification_TriggerRule(integer $InstanzID, string $Message, string $Subject, mixed $Severity, array $Params)`<br>
Löst die Benachrichtigungsregel aus und gemäß der Definition die Benachrichtigungen.
_Severity_ kann als numerischer Wert oder als Abkürzung übergeben werden (siehe oben)
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

`boolean Notification_Log(integer $InstanzID, string $Text, mixed $Severity, array $Params)`<br>
Ruft die FUnktion der _NotificationBase_, dient zur Vereinfachung

### _NotificationEvent_
`int Notification_TriggerEvent(integer $InstanzID, boolean $Force)`
Es wird das Benachrichtigungs-Ereignis ausgelöst (sofern die Bedingungen stimmen). Wenn _Force_ auf _true_ steht, wird ein ggfs. laufender Ereignis neu gestartet.
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

`int Notification_StopEvent(integer $InstanzID)`
Es wird ein ggfs. laufendes Benachrichtigungs-Ereignis  ausgelöst (sofern die Bedingungen stimmen). Wenn _Force_ auf _true_ steht, wird ein ggfs. laufendes Ereignis neu gestartet.
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

## 5. Konfiguration

### Variablen

| Eigenschaft                                       | Typ     | Standardwert | Beschreibung |
| :------------------------------------------------ | :------ | :----------- | :----------- |
| Instanz deaktivieren                              | boolean | false        | Instanz temporär deaktivieren |
|                                                   |         |              | |

## 6. Anhang

GUIDs

- Modul: `{1E92B006-FB7D-6020-B296-2F31BC2892C4}`
- Instanzen:
  - NotificationBase: `{4CF21C1E-B0F8-5535-8B5D-01ADDDB5DFD7}`
  - NotificationRule: `{2335FF1E-9628-E363-AAEC-11DE75788A13}`
  - NotificationEvent: `{BF681BDA-E2C7-3175-6671-6D6E570BCDAA}`

## 7. Versions-Historie

- 1.0 @ 25.02.2022 10:45 (beta)
  - initiale Version
