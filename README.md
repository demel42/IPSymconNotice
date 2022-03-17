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
- Skript<br>
ein beliebiges Script um die Anbindung sonstiger Benachrichtigungswege / Dienste zu ermögliche. (_Pushover_, _Pushsafer_ etc).


Es werden 5 Schweregrade unterstützt:

| Bezeichnung | Ident  | Wert |
| :---------- | :----- | :--- |
| Information | info   | 1 |
| Hinweis     | notice | 2 |
| Warnung     | warn   | 3 |
| Alarm       | alert  | 4 |
| Fehlersuche | debug  | 5 |

Die Idee ist dabei, das Benachrichtigungen in Gruppen zusammengefasst werden, die den gleichen Empfängerkreis haben.

Hierzu gibt es drei Module:

### Benachrichtigungs-Basis (_NotificationBase_)
Hier werden grundsätzlich Einstellungen gemacht, unter anderem die _Benutzer_ mit deren Kommunikationswegen angelegt.
Zu jedem Benutzer wird eine Variable angelegt, die den Anwesenheitsstatus repräsentiert. Da es ja ganz unterschiedliche Wege gibt, wie Anwesenheiten ermittelt werden,
wird das von dem Modul nicht selbst ermittelt sondern die Ermittlung (z.B. mittels _Geofency_) muss über ein Ereignis in die entsprechenden Variablen der Benachrichtigungs-Basis
übertragen werden; hierfür gibt es eine passende _RequestAction_.

Es gibt die Präsezstatus:

| Status      | Wert |
| :---------- | :--- |
| zu Hause    | 1 |
| unterwegs   | 2 |
| im Urlaub   | 3 |

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

| Bezeichnung                 | Bedeutung |
| :-------------------------- | :-------- |
| immer                       | der eingetragenen Empfänger wird bedingungslos angesprochen |
| wenn zu Haus                | der Empfänger wird genutzt, wenn er im Status "zu Hause" ist |
| wenn unterwegs              | der Empfänger wird genutzt, wenn er im Status "unterwegs" ist.<br>Hinweis: hier zählt _im Urlaub_ nicht mit |
| erster Anwesender der Liste | erste Person auf der Liste, die zu Hause ist |
| letzter gegangen            | wenn der definierte Benutzer die Person ist, die zuletzt das Haus verlassen hat |
| erster gekommen             | wenn der definierte Benutzer die Person ist, die zuerst in das leere Haus gekommen ist |
| wenn sonst keiner           | Ersatzwert, wenn keine der sonstigen Bedingungen zutrifft |

Die Liste der Bedingungen wird der Reihefolge nach abgearbeitet, was insbesondere für _erster Anwesender der Liste_ und _wenn sonst keiner_ relevant ist.

Eine Benachrichtigungsregel ist immer mit einer Benachrichtigungs-Basis verknüpft; gibt es nur eine Benachrichtigungs-Basis kann die Angabe entfallen, da die Regel sich dann die
erste Instanz sucht.

### Benachrichtigungs-Ereignis (_NotificationEvent_)
Hiermit können Benachrichtigungs-Regeln verzögert bzw. wiederholt aufgerufen werden.<br>
Neben der Timerfunktion können auch Bedingungen angegeben werden, die gültig sein müssen, damit das Ereignis anläuft bzw. weiterläuft (die Bedingungen werden 
bei jeder Meldung, egal ob nach Start-Verzögerung oder Wiederholung neu geprüft).

Aufgerufen wird das Benachrichtigungs-Ereignis i.d.R. durch IPS-Ereignisse, i.d.R. vermutlich _Ausgelöst_ oder _Zyklisch_, aber auch natürlich durch allen anderen Möglichkeiten (Ablaufplan, Skript).<br>
Wichtig ist dabei zu beachten, das die optionalen _Bedingungen_ innerhalb des Benachrichtigungs-Ereignis dazu dienen, zu entscheiden, ob es sich um einen zu meldenden Vorfall handelt oder ggfs. um eine Wiederherstellung (sofern man auch diese Meldung Wert legt).<br>
<br>
Bespiel: Überwachung der Stromversorgung eines wichtigen Geräts.
- die Benachrichtigungs-Ereignis prüft in den Bedingungen, ob die Variable (der Spannungsversorgung) auf "AUS" steht - d.h. "AUS" ist der Vorfall, "EIN" ist die Wiederherstellung.
- das "ausgelöste Ereignis" prüft auf Änderung der Variable, d..h das Benachrichtigungs-Ereignis wird bei dem Wechsel auf "AUS" und auf "EIN" ausgelöst und kann somit eine Wiederherstellung erkennen und melden.
<br>

Gemäß den angegebenen Einstellungen wird eine entsprechende Benachrichtigungs-Regel aufgerufen, dabei können durch vielfältige Einstellungen Nachrichtentext, Betreff und Schwergrad angepasst werden.

Ein laufendes Ereignis wird standardmässig durch erneuten Aufruf nicht wieder ausgelöst (siehe _TriggerEvent_).


## 2. Voraussetzungen

- IP-Symcon ab Version 6.0

## 3. Installation

### a. Installation des Moduls

Im [Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff *Symcon Notification* zu finden.<br>
Alternativ kann das Modul über [Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/IPSymconNotification.git` installiert werden.

### b. Einrichtung in IPS

Nun _Instanz hinzufügen_ anwählen und als Hersteller _(sonstiges)_ sowie als Gerät _Notification Base_ auswählen.
Für jede Regel muss eine Instanz vom Typ _Notification Rule_ angelegt werden und bei Bedarf _Notification Event_-Instanzen.

## 4. Funktionsreferenz

### Benachrichtigungs-Basis (_NotificationBase_)
`boolean Notification_Log(integer $InstanzID, string $Message, mixed $Severity, array $Params)`<br>
Erzeugt einen Eintrag in dem Protokoll vom _NotificationBase_.<br>
_Severity_ kann als numerischer Wert oder als Abkürzung übergeben werden (siehe oben).<br>
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

### Benachrichtigungs-Regeln (_NotifcationRule_)
`boolean Notification_TriggerRule(integer $InstanzID, string $Message, string $Subject, mixed $Severity, array $Params)`<br>
Löst die Benachrichtigungsregel aus und gemäß der Definition die Benachrichtigungen.<br>
_Severity_ kann als numerischer Wert oder als Abkürzung übergeben werden (siehe oben).<br>
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

`boolean Notification_Log(integer $InstanzID, string $Message, mixed $Severity, array $Params)`<br>
Ruft die korrespondierende Funktion der _NotificationBase_, dient nur zur Vereinfachung

### Benachrichtigungs-Ereignis (_NotificationEvent_)
`int Notification_TriggerEvent(integer $InstanzID, boolean $Force)`
Es wird das Benachrichtigungs-Ereignis ausgelöst (sofern die Bedingungen stimmen).
Wenn _Force_ auf _true_ steht, wird ein ggfs. laufender Ereignis neu gestartet.<br>
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

`int Notification_StopEvent(integer $InstanzID)`
Es wird ein ggfs. laufendes Benachrichtigungs-Ereignis  gestoppt.<br>
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

`boolean Notification_Log(integer $InstanzID, string $Message, mixed $Severity, array $Params)`<br>
Ruft die korrespondierende Funktion der _NotificationBase_, dient nur zur Vereinfachung.


Die den Funktionen übergebenen Parameter (_Message_, _Subject_, _Severity__Params_ werden mit den entspreㄔhenden Voreinstellung in den Instanzen ergänzt entsprechend der Hierarchie.

## 5. Konfiguration

### Eigenschaften 

#### Benachrichtigungs-Basis (_NotificationBase_)

| Eigenschaft                           | Typ     | Standardwert | Beschreibung |
| :------------------------------------ | :------ | :----------- | :----------- |
| Instanz deaktivieren                  | boolean | false        | Instanz temporär deaktivieren |
|                                       |         |              | |
| Basiskonfiguration / Webfront         |         |              | |
|   Standardwert für Signaltöne         | string  |              | Töne des Webfront |
|   sonstige Standardeinstellungen      | string  |              | json-kodierte Attribute (siehe _Params_) |
|                                       |         |              | |
| Basiskonfiguration / E-Mail           |         |              | |
|   SMTP-Instanz                        | integer | 0            | Instanz des Moduls **SMTP** |
|   sonstige Standardeinstellungen      | string  |              | json-kodierte Attribute (siehe _Params_) |
|                                       |         |              | |
| Basiskonfiguration / SMS              |         |              | |
|   SMS-Instanz                         | integer | 0            | Instanz des Moduls **SMS REST** oder **Sipgate Basic** |
|   sonstige Standardeinstellungen      | string  |              | json-kodierte Attribute (siehe _Params_) |
|                                       |         |              | |
| Basiskonfiguration / Skript           |         |              | |
|   Skript                              | integer | 0            | ID des Skriptes |
|   Standardwert für Signalisierungen   | string  |              | Kodierung von Signalisierungen |
|   sonstige Standardeinstellungen      | string  |              | json-kodierte Attribute (siehe _Params_) |
|                                       |         |              | |
| Benutzer                              |         |              | |
|   Kürzel                              | string  |              | interne Bezeichnung **nicht ändern** |
|   Bezeichnung                         | string  |              | |
|   Webfront                            | integer | 0            | ID der Webfront-Instanz |
|   Mail-Adresse                        | string  |              | Mail-Adresse |
|   SMS Telefonnummer                   | string  |              | Telefonnummer für SMS |
|   Skript-Parameter                    | string  |              | json-kodierte Attribute (siehe _Params_) |
|   inaktiv                             | boolean | false        | Eintrag ist inaktiv und wird nicht beachtet |
|   unbeweglich                         | boolean | false        | Eintrag wird bei der Ermittlung der Anwesenheit nicht beachtet |
|                                       |         |              | |
| Protokollierung                       |         |              | |
|   Skript                              | integer | 0            | Skript zu alternativen Protokollierung |
|   Alter                               | integer | 90           | Lösch-Alter von Log-Einträgen |
|   Darstellung ...                     |         |              | Angaben zur Darstellung des Logs in der HTML-Box |
|                                       |         |              | |
| Meldungen zu Instanz-Aktivitäten      | integer |              | IPS-Meldungen zu Aktivitäten der Instanz |

* unbeweglich<br>
z.B. für die Benachrichtigung an einen Admin-Mail-Account

* Basiskonfiguration / Webfront<br>
ein in dem Argument _Params_ der Funktion _Notification_TriggerRule_ übergebener bzw. aus den Standardeinstellungen gewonnener Eintrag _TargetID_ wird *WFC_PushNotification* übergeben.<br>

* Basiskonfiguration / Skript<br>
alle Argumente werden über *_IPS* weitergegeben

  * die in dem Argument _Params_ den Funktionen übergebenen bzw. aus den Standardeinstellungen gewonnener Einträge

  * zusätzlich bei dem Aufruf aus _NotificationRule_

    | Ident              | Typ     | Bedeutung |
    | :----------------- | :------ | :-------- |
    | ruleID             | integer | ID der aufgerufenen _NotificationRule_ |
    | message            | string  | Nachrichten-Text |
    | subject            | string  | Betreff |
    | severity           | integer | Schweregrad |
    | signal             | string  | Signalisierung (Skript) |
    | sound              | string  | Signaltöne (Webfront) |

  * zusätzlich bei dem Aufruf aus _NotificationEvent_

    | Ident              | Typ     | Bedeutung |
    | :----------------- | :------ | :-------- |
    | eventID            | integer | ID des auslösenden _NotificationEvent_ |
    | repetition         | integer | Wiederholung |
    | recovery           | boolean | handelt sich um eine Wiederherstellungs-Mitteilung |
    | started            | integer | Auslöse-Zeitpunkt |

* Protokollierung / Skript<br>
alle Argumente werden über *_IPS* weitergegeben

  * die in dem Argument _Params_ der Funktion _Notification_Log_ übergebenen bzw. aus den Standardeinstellungen gewonnener Einträge

  * zusätzlich
 
    | Ident              | Typ     | Bedeutung |
    | :----------------- | :------ | :-------- |
    | message            | string  | Nachrichten-Text |
    | severity           | integer | Schweregrad |

#### Benachrichtigungs-Regeln (_NotifcationRule_)

| Eigenschaft                           | Typ     | Standardwert | Beschreibung |
| :------------------------------------ | :------ | :----------- | :----------- |
| Instanz deaktivieren                  | boolean | false        | Instanz temporär deaktivieren |
|                                       |         |              | |
| Benachrichtigungs-Basis               | integer | 0            | Instanz der zugehörigen Basis, nur erforderlich, wenn man mehr als eine Basis nutzen möchte  |
|                                       |         |              | |
| Standardwerte                         |         |              | |
|   Schweregrad                         | integer |              | Stardard-Schweregrad |
|   Betreff                             | string  |              | Standard-Betreff eine Benachrichtigung (nur _E-Mail_, _Script_) |
|   Nachricht                           | string  |              | Standard-Nachrichten-Text |
|                                       |         |              | |
|   Webfront                            |         |              | |
|     Standardwert für Signaltöne       | string  |              | Töne des Webfront |
|                                       |         |              | |
|   Skript                              |         |              | |
|     Standardwert für Signalisierungen | string  |              | Kodierung von Signalisierungen |
|                                       |         |              | |
| Benachrichtigungen protokollieren     | boolean | false        | ermöglicht, das Benachrichtigungen aus mit im enthaltenen automatisch auch mit dem entsprechendem Schweregrad protokolliert werden.<br>|
|                                       |         |              | |
| Empfänger                             |         |              | |
|                                       |         |              | |
| Meldungen zu Instanz-Aktivitäten      | integer |              | IPS-Meldungen zu Aktivitäten der Instanz |

#### Benachrichtigungs-Ereignis (_NotificationEvent_)

| Eigenschaft                           | Typ     | Standardwert | Beschreibung |
| :------------------------------------ | :------ | :----------- | :----------- |
| Instanz deaktivieren                  | boolean | false        | Instanz temporär deaktivieren |
|                                       |         |              | |
| Bedingungen                           | string  |              | Bedingung, wann das Ereignis gültig ist |
|                                       |         |              | |
| Benachrichtigungs-Details             |         |              | |
|   Benachrichtigungs-Regel             | integer |              | zu verwendende Benachrichtigungs-Regel |
|                                       |         |              | |
|   Betreff                             | string  |              | vorgegebener Betreff eine Benachrichtigung (nur _E-Mail_, _Script_) |
|   Nachricht                           | string  |              | vorgegebener Nachrichten-Text |
|   Schweregrad                         | integer |              | vorgegebener Schweregrad |
|   ... erhöhen                         | boolean | false        | Schweregrad bei jeder Widerholung erhöhen |
|                                       |         |              | |
|   Skript ...                          | string  |              | optionale Skript zur Erzeugung von individuellen Meldungstexten |
|                                       |         |              | |
| Start-Verzögerung                     |         |              | |
|   Zeiteinheit                         | integer |              | Einheit der angegebenen oder aus der Variable ausgelwsenen Zeitangabe |
|   fester Wert                         | integer |              | |
|   Variable                            | integer |              | |
|                                       |         |              | |
| Wiederholungen                        |         |              | |
|   Zeiteinheit                         | integer |              | Einheit der angegebenen oder aus der Variable ausgelwsenen Zeitangabe |
|   fester Wert                         | integer |              | |
|   Variable                            | integer |              | |
|   maximale WIederholungen             | integer | -1           | Anzahl Meldungs-Wiederholung (-1=ohne Begrenzung, 0=einmalige Meldung) |
|                                       |         |              | |
| Wiederherstellung                     |         |              | |
|   Benachrichtigung ...                | boolean | false        | nach minimal einer erfolgten Meldung kann die Wiederherstellung kommuniziert werden |
|   Betreff                             | string  |              | alternativer Betreff einer Benachrichtigung (nur _E-Mail_, _Script_) |
|   Nachricht                           | string  |              | alternativer Nachrichten-Text |
|   Schweregrad                         | integer |              | alternativer Schweregrad |
|                                       |         |              | |
| Meldungen zu Instanz-Aktivitäten      | integer |              | IPS-Meldungen zu Aktivitäten der Instanz |

* Skript ...<br>
dem Skript werden in dem Array *_IPS* folgende Daten übergeben

  | Ident              | Typ     | Bedeutung |
  | :----------------- | :------ | :-------- |
  | recovery           | boolean | handelt sich um eine Wiederherstellungs-Mitteilung |
  | repetition         | integer | Wiederholung |
  | ruleID             | integer | ID der Benachrichtigungs-Regel |
  | severity           | integer | Schweregrad |
  | started            | integer | Auslöse-Zeitpunkt |

  Zurückgegeben wird entweder json-kodiertes Array mit den optionalen Argumenten

  | Ident              | Typ     | Bedeutung |
  | :----------------- | :------ | :-------- |
  | message            | string  | Nachrichten-Text |
  | ruleID             | integer | ID der Benachrichtigungs-Regel |
  | severity           | integer | Schweregrad |
  | summary            | string  | Betreff |

  oder ein String, der als _message_ verwendet wird. Die werte überschreiben dann eventuelle Voreinstellungen.

Beispiele:

  einfach
  ```
  echo 'Status der USV: ' . GetValueFormatted(12345);
  ```
  komplexer
  ```
  $r = [];
  if (GetValueBoolean(88888)) { 
      $r['message'] = 'Wasserstand unter der Heizung erkannt';
  } else if (GetValueBoolean(77777)) { 
      $r['message'] = 'Feuchtigkeit unter der Heizung erkannt';
  } else { 
      if ($_IPS['recovery'])
          $r['message'] = 'Boden unter der Heizung ist wieder trocken';
      else
          $r['message'] = 'Heizung-Wassersensor OK';
      $r['severity'] = 'info';
  } 
  echo json_encode($r);
  ```

### Variablen

#### Benachrichtigungs-Basis (_NotificationBase_)

| Ident                       | Typ          | Bezeichnung |
| :-------------------------- | :------      | :---------- |
| AllAbsent                   | boolean      | alle abwesend |
| LastGone                    | string       | zuletzt gegangen |
| FirstCome                   | string       | zuerst gekommen |
|                             |              | |
| PresenceState_\<*Kürzel*\>  | integer      | Präsenz-Status von ... |
|                             |              | |
| Notifications               | HTML-Box     | Benachrichtigungen |
| Data                        | Medienobjekt | Daten |

#### Benachrichtigungs-Regeln (_NotifcationRule_)

#### Benachrichtigungs-Ereignis (_NotificationEvent_)

| Ident                       | Typ          | Bezeichnung |
| :-------------------------- | :------      | :---------- |
| TimerStarted                | integer      | Auslöse-Zeitpunkt |

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Boolean<br>
Notification.YesNo

* Integer<br>
Notification.Presence

## 6. Anhang

GUIDs

- Modul: `{1E92B006-FB7D-6020-B296-2F31BC2892C4}`
- Instanzen:
  - NotificationBase: `{4CF21C1E-B0F8-5535-8B5D-01ADDDB5DFD7}`
  - NotificationRule: `{2335FF1E-9628-E363-AAEC-11DE75788A13}`
  - NotificationEvent: `{BF681BDA-E2C7-3175-6671-6D6E570BCDAA}`

## 7. Versions-Historie

- 1.0.8 @ 17.03.2022 17:53 (beta)
  - Ausgabe der Aufruf-Historie (CallChain)

- 1.0.7 @ 03.03.2022 14:02
  - Absicherung $action.restrictions.moduleID
  - Anzeige referenzierter Statusvariablen

- 1.0.6 @ 03.03.2022 10:08
  - Fix in CommonStubs

- 1.0.5 @ 01.03.2022 21:53
  - Anzeige der Referenzen der Instanz
  - Default von 'max_repetitions' ist nun 0

- 1.0.4 @ 28.02.2022 16:18
  - Action createLogEntry gibt es in allen drei Modulen

- 1.0.3 @ 28.02.2022 10:02
  - teilweise (Webfront, Log) wird, wenn "message" nicht angegeben ist, stattdessen "subject" verwendet

- 1.0.2 @ 27.02.2022 11:27
  - LogMessages überarbeitet
  - Korrekturen

- 1.0.1 @ 26.02.2022 06:52
  - GetPresence() ist wieder public

- 1.0 @ 25.02.2022 19:32
  - initiale Version
