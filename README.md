# IPSymconNotice

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

In dem Modul geht es darum, Mitteilungen von Benutzern in Abhängigkeit von den Anwesenheit der Benutzer zu steuern.

Es werden 4 Wege der Mitteilung unterstützt
- Push-Nachricht via WebFront<br>
setzt ein oder mehrere Webfront-Instanzen voraus, die mit Endgeräten gekoppelt sind
- E-Mail<br>
setzt ein eingerichtetes Symcon-Modul **SMTP** voraus
- SMS<br>
setzt entweder das Symcon-Modul **SMS REST** (Clickatell) voraus oder das Modul **Sipgate Basic**
- Skript<br>
ein beliebiges Script um die Anbindung sonstiger Mitteilungswege / Dienste zu ermögliche. (_Pushover_, _Pushsafer_ etc).


Es werden 5 Schweregrade unterstützt:

| Bezeichnung | Ident  | Wert |
| :---------- | :----- | :--- |
| Information | info   | 1 |
| Hinweis     | notice | 2 |
| Warnung     | warn   | 3 |
| Alarm       | alert  | 4 |
| Fehlersuche | debug  | 5 |

Die Idee ist dabei, das Mitteilungen in Gruppen zusammengefasst werden, die den gleichen Empfängerkreis haben.

Hierzu gibt es drei Module:

### Mitteilungs-Basis (_NoticeBase_)
Hier werden grundsätzlich Einstellungen gemacht, unter anderem die _Benutzer_ mit deren Kommunikationswegen angelegt.
Zu jedem Benutzer wird eine Variable angelegt, die den Anwesenheitsstatus repräsentiert. Da es ja ganz unterschiedliche Wege gibt, wie Anwesenheiten ermittelt werden,
wird das von dem Modul nicht selbst ermittelt sondern die Ermittlung (z.B. mittels _Geofency_) muss über ein Ereignis in die entsprechenden Variablen der Mitteilungs-Basis
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

Neben der Mitteilung wird auch eine "normale" Protokollierung unterstützt.

### Mitteilungs-Regeln (_NoticeRule_)
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

Eine Mitteilungsregel ist immer mit einer Mitteilungs-Basis verknüpft; gibt es nur eine Mitteilungs-Basis kann die Angabe entfallen, da die Regel sich dann die
erste Instanz sucht.

### Mitteilungs-Ereignis (_NoticeEvent_)
Hiermit können Mitteilungs-Regeln verzögert bzw. wiederholt aufgerufen werden.<br>
Neben der Timerfunktion können auch Bedingungen angegeben werden, die gültig sein müssen, damit das Ereignis anläuft bzw. weiterläuft (die Bedingungen werden 
bei jeder Meldung, egal ob nach Start-Verzögerung oder Wiederholung neu geprüft).

Aufgerufen wird das Mitteilungs-Ereignis i.d.R. durch IPS-Ereignisse, i.d.R. vermutlich _Ausgelöst_ oder _Zyklisch_, aber auch natürlich durch allen anderen Möglichkeiten (Ablaufplan, Skript).<br>
Wichtig ist dabei zu beachten, das die optionalen _Bedingungen_ innerhalb des Mitteilungs-Ereignis dazu dienen, zu entscheiden, ob es sich um einen zu meldenden Vorfall handelt oder ggfs. um eine Wiederherstellung (sofern man auch diese Meldung Wert legt).<br>
<br>
Bespiel: Überwachung der Stromversorgung eines wichtigen Geräts.
- die Mitteilungs-Ereignis prüft in den Bedingungen, ob die Variable (der Spannungsversorgung) auf "AUS" steht - d.h. "AUS" ist der Vorfall, "EIN" ist die Wiederherstellung.
- das "ausgelöste Ereignis" prüft auf Änderung der Variable, d..h das Mitteilungs-Ereignis wird bei dem Wechsel auf "AUS" und auf "EIN" ausgelöst und kann somit eine Wiederherstellung erkennen und melden.
<br>

Gemäß den angegebenen Einstellungen wird eine entsprechende Mitteilungs-Regel aufgerufen, dabei können durch vielfältige Einstellungen Nachrichtentext, Betreff und Schwergrad angepasst werden.

Ein laufendes Ereignis wird standardmässig durch erneuten Aufruf nicht wieder ausgelöst (siehe _TriggerEvent_).


## 2. Voraussetzungen

- IP-Symcon ab Version 6.0

## 3. Installation

### a. Installation des Moduls

Im [Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff
*Mitteilungen* ( *Notices* ) zu finden.<br>
Alternativ kann das Modul über [Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/IPSymconNotice` installiert werden.

### b. Einrichtung in IPS

Nun _Instanz hinzufügen_ anwählen und als Hersteller _(sonstiges)_ sowie als Gerät _Notice Base_ auswählen.
Für jede Regel muss eine Instanz vom Typ _Notice Rule_ angelegt werden und bei Bedarf _Notice Event_-Instanzen.

## 4. Funktionsreferenz

### Mitteilungs-Basis (_NoticeBase_)
`boolean Notice_Log(integer $InstanzID, string $Message, mixed $Severity, array $Params)`<br>
Erzeugt einen Eintrag in dem Protokoll vom _NoticeBase_.<br>
_Severity_ kann als numerischer Wert oder als Abkürzung übergeben werden (siehe oben).<br>
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

### Mitteilungs-Regeln (_NoticeRule_)
`boolean Notice_TriggerRule(integer $InstanzID, string $Message, string $Subject, mixed $Severity, array $Params)`<br>
Löst die Mitteilungsregel aus und gemäß der Definition die Mitteilungen.<br>
_Severity_ kann als numerischer Wert oder als Abkürzung übergeben werden (siehe oben).<br>
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gibt es eine entsprechende _Aktion_.

`boolean Notice_Log(integer $InstanzID, string $Message, mixed $Severity, array $Params)`<br>
Ruft die korrespondierende Funktion der _NoticeBase_, dient nur zur Vereinfachung

### Mitteilungs-Ereignis (_NoticeEvent_)
`int Notice_TriggerEvent(integer $InstanzID, boolean $Force)`
Es wird das Mitteilungs-Ereignis ausgelöst (sofern die Bedingungen stimmen).
Wenn _Force_ auf _true_ steht, wird ein ggfs. laufender Ereignis neu gestartet.<br>
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

`int Notice_StopEvent(integer $InstanzID)`
Es wird ein ggfs. laufendes Mitteilungs-Ereignis  gestoppt.<br>
Der Aufruf kann in einem Script erfolgen, für Ablaufpläne etc gib es eine entsprechende _Aktion_.

`boolean Notice_Log(integer $InstanzID, string $Message, mixed $Severity, array $Params)`<br>
Ruft die korrespondierende Funktion der _NoticeBase_, dient nur zur Vereinfachung.


Die den Funktionen übergebenen Parameter (_Message_, _Subject_, _Severity_, _Params_) werden mit den entsprechenden
Voreinstellung in den Instanzen ergänzt entsprechend der Hierarchie.
Diese Funktionen sind auch als Aktionen verfügbar.

Zusätzlich gibt es einige Standard-Funktionen, die um die Möglichkeit des Logging erweitert wurden.<br>

- _Schalte auf Wert mit Protokoll_<br>
Ein Duplikat der Standard-Aktionen _Schalte auf Wert_ für alle Datentypen von Variablen mit und ohne Assoziationen

- _Schalte auf Wert einer anderen Variablen mit Protokoll_<br>
Ein Duplikat der Standard-Aktion _Schalte auf Wert einer anderen Variablen_

Alle diese Aktionen haben folgende Zusatzfunktion
- man kann die _Mitteilungs-Basis_ angeben, zu der das Log geschickt werden soll; ist dしese nicht angegeben, wird ein _IPS_LogMessage()_ aufgerufen
- man kann den Text für eine gelungenen bzw fehlgeschlagenen Aktion definieren, hierbei stehen Variablen zur Verfügung
  - _NAME_
    Name der Ziel-Variablen
  - _LOCATION_
    Location der Ziel-Variablen
  - _LOCATION0_, _LOCATION1_, _LOCATION2_ ...
    die in Einzelteile zerlegte Location der Ziel-Variablen, dabei ist _LOCATION0_ die Variable, _LOCATION1_ der Parent usw.
  - _VALUE_
    der zu setzenden Wert, nach Möglichkeit formatiert gemäß der Vorgabe der Ziel-Variablen

## 5. Konfiguration

### Eigenschaften

#### Mitteilungs-Basis (_NoticeBase_)

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
z.B. für die Mitteilung an einen Admin-Mail-Account

* Basiskonfiguration / Webfront<br>
Es gibt folgende spezielle Logik:
- ein in dem Argument _Params_ der Funktion _Notice_TriggerRule_ übergebener bzw. aus den Standardeinstellungen gewonnener Eintrag _TargetID_ wird *WFC_PushNotification* übergeben.
- ist _Message_ leer und _Subject_ angegeben, wird _Subject_ in _Message_ übertragen und _Subject_ wird geleert - Grund: *WFC_PushNotification* erwartet _Message_, _Subject_ ist optional
- _Subject_ wird auf 32 Zeichen gekürzt (Vorgabe von *WFC_PushNotification*)

* Basiskonfiguration / SMS<br>
Es gibt folgende spezielle Logik:
- ist _Message_ nicht angegeben, wird _Subject_ verwendet (es gibt bei SMS kein getrennten Betreff)

* Basiskonfiguration / Skript<br>
alle Argumente werden über *_IPS* weitergegeben

  * die in dem Argument _Params_ den Funktionen übergebenen bzw. aus den Standardeinstellungen gewonnener Einträge

  * zusätzlich bei dem Aufruf aus _NoticeRule_

    | Ident              | Typ     | Bedeutung |
    | :----------------- | :------ | :-------- |
    | ruleID             | integer | ID der aufgerufenen _NoticeRule_ |
    | message            | string  | Nachrichten-Text |
    | subject            | string  | Betreff |
    | severity           | integer | Schweregrad |
    | signal             | string  | Signalisierung (Skript) |
    | sound              | string  | Signaltöne (Webfront) |

  * zusätzlich bei dem Aufruf aus _NoticeEvent_

    | Ident              | Typ     | Bedeutung |
    | :----------------- | :------ | :-------- |
    | eventID            | integer | ID des auslösenden _NoticeEvent_ |
    | repetition         | integer | Wiederholung |
    | recovery           | boolean | handelt sich um eine Wiederherstellungs-Mitteilung |
    | started            | integer | Auslöse-Zeitpunkt |

* Protokollierung / Skript<br>
alle Argumente werden über *_IPS* weitergegeben

  * die in dem Argument _Params_ der Funktion _Notice_Log_ übergebenen bzw. aus den Standardeinstellungen gewonnener Einträge

  * zusätzlich

    | Ident              | Typ     | Bedeutung |
    | :----------------- | :------ | :-------- |
    | message            | string  | Nachrichten-Text |
    | severity           | integer | Schweregrad |

#### Mitteilungs-Regeln (_NoticeRule_)

| Eigenschaft                           | Typ     | Standardwert | Beschreibung |
| :------------------------------------ | :------ | :----------- | :----------- |
| Instanz deaktivieren                  | boolean | false        | Instanz temporär deaktivieren |
|                                       |         |              | |
| Mitteilungs-Basis                     | integer | 0            | Instanz der zugehörigen Basis, nur erforderlich, wenn man mehr als eine Basis nutzen möchte  |
|                                       |         |              | |
| Standardwerte                         |         |              | |
|   Schweregrad                         | integer |              | Stardard-Schweregrad |
|   Betreff                             | string  |              | Standard-Betreff eine Mitteilung (nur _E-Mail_, _Script_) |
|   Nachricht                           | string  |              | Standard-Nachrichten-Text |
|                                       |         |              | |
|   Webfront                            |         |              | |
|     Standardwert für Signaltöne       | string  |              | Töne des Webfront |
|                                       |         |              | |
|   Skript                              |         |              | |
|     Standardwert für Signalisierungen | string  |              | Kodierung von Signalisierungen |
|                                       |         |              | |
| Mitteilungen protokollieren           | boolean | false        | ermöglicht, das Mitteilungen aus mit im enthaltenen automatisch auch mit dem entsprechendem Schweregrad protokolliert werden.<br>|
|                                       |         |              | |
| Empfänger                             |         |              | |
|                                       |         |              | |
| Meldungen zu Instanz-Aktivitäten      | integer |              | IPS-Meldungen zu Aktivitäten der Instanz |

#### Mitteilungs-Ereignis (_NoticeEvent_)

| Eigenschaft                           | Typ     | Standardwert | Beschreibung |
| :------------------------------------ | :------ | :----------- | :----------- |
| Instanz deaktivieren                  | boolean | false        | Instanz temporär deaktivieren |
|                                       |         |              | |
| Bedingungen                           | string  |              | Bedingung, wann das Ereignis gültig ist |
|                                       |         |              | |
| Mitteilungs-Details                   |         |              | |
|   Mitteilungs-Regel                   | integer |              | zu verwendende Mitteilungs-Regel |
|                                       |         |              | |
|   Betreff                             | string  |              | vorgegebener Betreff eine Mitteilung (nur _E-Mail_, _Script_) |
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
|   maximale Wiederholungen             | integer | -1           | Anzahl Meldungs-Wiederholung (-1=ohne Begrenzung, 0=einmalige Meldung) |
|                                       |         |              | |
| Wiederherstellung                     |         |              | |
|   Mitteilung ...                      | boolean | false        | nach minimal einer erfolgten Meldung kann die Wiederherstellung kommuniziert werden |
|   Betreff                             | string  |              | alternativer Betreff einer Mitteilung (nur _E-Mail_, _Script_) |
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
  | ruleID             | integer | ID der Mitteilungs-Regel |
  | severity           | integer | Schweregrad |
  | started            | integer | Auslöse-Zeitpunkt |
  | instanceID         | integer | ID des Mitteilungs-Ereignisses |
  |                    |         | falls in den Systemvariablen gesetzt ... |
  | VARIABLE           | integer | siehe [Symcon-Dokumentation](https://www.symcon.de/service/dokumentation/konzepte/automationen/php-skripte/systemvariablen/#Variable) |
  | VALUE              | string  | siehe [Symcon-Dokumentation](https://www.symcon.de/service/dokumentation/konzepte/automationen/php-skripte/systemvariablen/#Variable) |
  | OLDVALUE           | string  | siehe [Symcon-Dokumentation](https://www.symcon.de/service/dokumentation/konzepte/automationen/php-skripte/systemvariablen/#Variable) |

  Zurückgegeben wird entweder ein json-kodiertes Array mit den optionalen Argumenten

  | Ident              | Typ     | Bedeutung |
  | :----------------- | :------ | :-------- |
  | message            | string  | Nachrichten-Text |
  | ruleID             | integer | ID der Mitteilungs-Regel |
  | severity           | integer | Schweregrad |
  | summary            | string  | Betreff |
  | log_additionally   | boolean | Mitteilung zusätzlich protokollieren |

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

#### Mitteilungs-Basis (_NoticeBase_)

| Ident                       | Typ          | Bezeichnung |
| :-------------------------- | :------      | :---------- |
| AllAbsent                   | boolean      | alle abwesend |
| LastGone                    | string       | zuletzt gegangen |
| FirstCome                   | string       | zuerst gekommen |
|                             |              | |
| PresenceState_\<*Kürzel*\>  | integer      | Präsenz-Status von ... |
|                             |              | |
| Notices                     | HTML-Box     | Mitteilungen |
| Data                        | Medienobjekt | Daten |

#### Mitteilungs-Regeln (_NoticeRule_)

#### Mitteilungs-Ereignis (_NoticeEvent_)

| Ident                       | Typ          | Bezeichnung |
| :-------------------------- | :------      | :---------- |
| TimerStarted                | integer      | Auslöse-Zeitpunkt |

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Boolean<br>
Notice.YesNo

* Integer<br>
Notice.Presence

## 6. Anhang

GUIDs

- Modul: `{1E92B006-FB7D-6020-B296-2F31BC2892C4}`
- Instanzen:
  - NoticeBase: `{4CF21C1E-B0F8-5535-8B5D-01ADDDB5DFD7}`
  - NoticeRule: `{2335FF1E-9628-E363-AAEC-11DE75788A13}`
  - NoticeEvent: `{BF681BDA-E2C7-3175-6671-6D6E570BCDAA}`

## 7. Versions-Historie

- 1.9 @ 20.09.2023 17:37
  - Neu: Ermittlung von Speicherbedarf und Laufzeit (aktuell und für 31 Tage) und Anzeige im Panel "Information"
  - update submodule CommonStubs

- 1.8 @ 04.07.2023 14:44
  - Fix: Anpassung der Spaltenbreite der Tabellen
  - Fix: Schreibfehler korrigiert
  - Vorbereitung auf IPS 7 / PHP 8.2
  - update submodule CommonStubs
    - Absicherung bei Zugriff auf Objekte und Inhalte

- 1.7.1 @ 11.03.2023 14:47
  - Fix: Initialisierungsfehler in der Instanz-Konfiguration, wenn es nicht genau eine NoticeBase gibt
  - Fix: Typo im README korrigiert

- 1.7 @ 19.10.2022 09:48
  - Neu: einige Standard-Aktionen zum Setzen von Variablen dupliziert und mit Logging versehen

- 1.6.2 @ 11.10.2022 08:28
  - update submodule CommonStubs

- 1.6.1 @ 07.10.2022 13:59
  - update submodule CommonStubs
    Fix: Update-Prüfung wieder funktionsfähig

- 1.6 @ 31.08.2022 09:13
  - Verbesserung: Referenzen setzen für den Inhalt des Scriptes in "NoticeEvent"
  - update submodule CommonStubs

- 1.5.2 @ 27.08.2022 16:50
  - Fix: bessere Prüfung auf vorhandenen Event-Bedingung

- 1.5.1 @ 19.08.2022 10:57
  - Ausgabe der Benutzer war z.T. in Großschreibung
  - update submodule CommonStubs

- 1.5 @ 05.07.2022 10:00
  - Verbesserung: IPS-Status wird nur noch gesetzt, wenn er sich ändert
  - Übersetzung ergänzt

- 1.4.2 @ 22.06.2022 10:30
  - Fix: Angabe der Kompatibilität auf 6.2 korrigiert

- 1.4.1 @ 28.05.2022 11:37
  - update submodule CommonStubs
    Fix: Ausgabe des nächsten Timer-Zeitpunkts

- 1.4 @ 25.05.2022 16:55
  - die Modul-Aktivität steht zur vereinfachten Prüfung in einem zusätzlichen Panel zur Verfügung
  - interne Funktionen sind nun entweder private oder nur noch via IPS_RequestAction() erreichbar
  - einige Funktionen (GetFormElements, GetFormActions) waren fehlerhafterweise "protected" und nicht "private"

- 1.3.6 @ 18.05.2022 10:23
  - in Meldungsliste auch mehrzeilige Einträge darstellen

- 1.3.5 @ 17.05.2022 15:38
  - update submodule CommonStubs
    Fix: Absicherung gegen fehlende Objekte

- 1.3.4 @ 10.05.2022 15:06
  - update submodule CommonStubs
  - SetLocation() -> GetConfiguratorLocation()
  - weitere Absicherung ungültiger ID's

- 1.3.3 @ 29.04.2022 15:24
  - Überlagerung von Translate und Aufteilung von locale.json in 3 translation.json (Modul, libs und CommonStubs)

- 1.3.2 @ 26.04.2022 12:22
  - Korrektur: self::$IS_DEACTIVATED wieder IS_INACTIVE

- 1.3.1 @ 24.04.2022 10:32
  - Übersetzung vervollständigt

- 1.3 @ 21.04.2022 09:27
  - Implememtierung einer Update-Logik
  - diverse interne Änderungen

- 1.2.3 @ 16.04.2022 10:25
  - Übergabe zusätzlicher Variablen an das Ereignis-Script
  - Instanz-Debug etwas ergänzt

- 1.2.2 @ 13.04.2022 15:13
  - potentieller Namenskonflikt behoben (trait CommonStubs)

- 1.2.1 @ 12.04.2022 18:05
  - Ausgabe der Instanz-Timer unter "Referenzen"
  - Steuerung der zusätzlichen Protokollierung aus dem Event-Script (siehe 'log_additionally')

- 1.2 @ 04.04.2022 11:14
  - Script des Mitteilungs-Ereignisses: Möglichkeit, die Mitteilung zusätzlich zu protokollieren (übersteuert die Einstellung in der Regel)

- 1.1.1 @ 29.03.2022 13:47
  - Korrektur zu 1.1 (WFC_PushNotification war fehlerhafterweise zu WFC_PushNotice geworden)
  - Korrektur im README.md

- 1.1 @ 28.03.2022 10:31
  - Aufgrund von Namenskonflikten: Notification -> Notice

- 1.0.8 @ 17.03.2022 17:53
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
