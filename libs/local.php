<?php

declare(strict_types=1);

trait NotificationLocalLib
{
    public static $IS_INVALIDCONFIG = IS_EBASE + 1;

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    public static $STATE_UNKNOWN = 0;
    public static $STATE_AT_HOME = 1;
    public static $STATE_BE_AWAY = 2;
    public static $STATE_ON_VACATION = 3;

    public static $MODE_WFC = 0;
    public static $MODE_MAIL = 1;
    public static $MODE_SMS = 2;
    public static $MODE_SCRIPT = 3;

    public static $USAGE_UNKNOWN = 0;
    public static $USAGE_EVERYONE = 1;
    public static $USAGE_ALL_PRESENT = 2;
    public static $USAGE_ALL_ABSENT = 3;
    public static $USAGE_FIRST_OF_PERSENT = 4;
    public static $USAGE_LAST_GONE = 5;
    public static $USAGE_FIRST_COME = 6;
    public static $USAGE_IF_NO_ONE = 7;

    public static $SEVERITY_INFO = 0;
    public static $SEVERITY_NOTICE = 1;
    public static $SEVERITY_WARN = 2;
    public static $SEVERITY_ALERT = 3;

    private function GetFormStatus()
    {
        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => self::$IS_INVALIDCONFIG, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid configuration)'];

        return $formStatus;
    }

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    public function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $associations = [];
        $associations[] = ['Wert' => self::$STATE_AT_HOME, 'Name' => $this->Translate('at home'), 'Farbe' => 0x64C466];
        $associations[] = ['Wert' => self::$STATE_BE_AWAY, 'Name' => $this->Translate('be away'), 'Farbe' => 0xEB4D3D];
        $associations[] = ['Wert' => self::$STATE_ON_VACATION, 'Name' => $this->Translate('on vacation'), 'Farbe' => 0x087FC9];
        $this->CreateVarProfile('Notification.Presence', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [];
        $associations[] = ['Wert' => false, 'Name' => $this->Translate('No'), 'Farbe' => -1];
        $associations[] = ['Wert' => true, 'Name' => $this->Translate('Yes'), 'Farbe' => 0xEE0000];
        $this->CreateVarProfile('Notification.YesNo', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 0, '', $associations, $reInstall);
    }

    private function ModeMapping()
    {
        return [
            self::$MODE_WFC    => [
                'tag'     => 'wfc',
                'caption' => 'Webfront',
            ],
            self::$MODE_MAIL   => [
                'tag'     => 'mail',
                'caption' => 'Mail',
            ],
            self::$MODE_SMS    => [
                'tag'     => 'sms',
                'caption' => 'SMS',
            ],
            self::$MODE_SCRIPT => [
                'tag'     => 'caption',
                'caption' => 'Script',
            ],
        ];
    }

    private function TargetEncode(string $abbreviation, string $mode)
    {
        return $abbreviation . '/' . $mode;
    }

    private function TargetDecode(string $target)
    {
        $r = explode('/', $target);
        return [
            'abbreviation' => $r[0],
            'mode'         => isset($r[1]) ? $r[1] : '',
        ];
    }

    private function SeverityMapping()
    {
        return [
            self::$SEVERITY_INFO    => [
                'tag'     => 'info',
                'caption' => 'Information',
            ],
            self::$SEVERITY_NOTICE   => [
                'tag'     => 'notice',
                'caption' => 'Notice',
            ],
            self::$SEVERITY_WARN    => [
                'tag'     => 'warn',
                'caption' => 'Warning',
            ],
            self::$SEVERITY_ALERT => [
                'tag'     => 'alert',
                'caption' => 'Alert',
            ],
        ];
    }

    private function SeverityDecode($indent)
    {
        $severity = self::$SEVERITY_INFO;
        $severityMap = $this->SeverityMapping();
        foreach ($severityMap as $index => $map) {
            if ($map['tag'] == strtolower($indent)) {
                $severity = $index;
                break;
            }
        }
        return $severity;
    }

    private function UsageMapping()
    {
        return [
            self::$USAGE_EVERYONE => [
                'caption' => 'everyone of the list',
            ],
            self::$USAGE_ALL_PRESENT => [
                'caption' => 'everyone present of the list',
            ],
            self::$USAGE_ALL_ABSENT => [
                'caption' => 'everyone absent of the list',
            ],
            self::$USAGE_FIRST_OF_PERSENT => [
                'caption' => 'first present of the list',
            ],
            self::$USAGE_LAST_GONE => [
                'caption' => 'last gone',
            ],
            self::$USAGE_FIRST_COME => [
                'caption' => 'first come',
            ],
            self::$USAGE_IF_NO_ONE => [
                'caption' => 'if no one else',
            ],
        ];
    }

    private function WfcSounds()
    {
        return [
            ['value' => '', 'caption' => $this->Translate('')],
            ['value' => 'alarm', 'caption' => $this->Translate('alarm')],
            ['value' => 'bell', 'caption' => $this->Translate('bell')],
            ['value' => 'boom', 'caption' => $this->Translate('boom')],
            ['value' => 'buzzer', 'caption' => $this->Translate('buzzer')],
            ['value' => 'connected', 'caption' => $this->Translate('connected')],
            ['value' => 'dark', 'caption' => $this->Translate('dark')],
            ['value' => 'digital', 'caption' => $this->Translate('digital')],
            ['value' => 'drums', 'caption' => $this->Translate('drums')],
            ['value' => 'duck', 'caption' => $this->Translate('duck')],
            ['value' => 'full', 'caption' => $this->Translate('full')],
            ['value' => 'happy', 'caption' => $this->Translate('happy')],
            ['value' => 'horn', 'caption' => $this->Translate('horn')],
            ['value' => 'inception', 'caption' => $this->Translate('inception')],
            ['value' => 'kazoo', 'caption' => $this->Translate('kazoo')],
            ['value' => 'roll', 'caption' => $this->Translate('roll')],
            ['value' => 'siren', 'caption' => $this->Translate('siren')],
            ['value' => 'space', 'caption' => $this->Translate('space')],
            ['value' => 'trickling', 'caption' => $this->Translate('trickling')],
            ['value' => 'turn', 'caption' => $this->Translate('turn')],
        ];
    }
}
