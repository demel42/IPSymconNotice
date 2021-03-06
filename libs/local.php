<?php

declare(strict_types=1);

trait NoticeLocalLib
{
    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

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

    public static $STATE_UNKNOWN = 0;
    public static $STATE_AT_HOME = 1;
    public static $STATE_BE_AWAY = 2;
    public static $STATE_ON_VACATION = 3;

    private function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $associations = [
            ['Wert' => self::$STATE_AT_HOME, 'Name' => $this->Translate('at home'), 'Farbe' => 0x64C466],
            ['Wert' => self::$STATE_BE_AWAY, 'Name' => $this->Translate('be away'), 'Farbe' => 0xEB4D3D],
            ['Wert' => self::$STATE_ON_VACATION, 'Name' => $this->Translate('on vacation'), 'Farbe' => 0x087FC9],
        ];
        $this->CreateVarProfile('Notice.Presence', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => false, 'Name' => $this->Translate('No'), 'Farbe' => -1],
            ['Wert' => true, 'Name' => $this->Translate('Yes'), 'Farbe' => 0xEE0000],
        ];
        $this->CreateVarProfile('Notice.YesNo', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 0, '', $associations, $reInstall);
    }

    public static $MODE_WEBFRONT = 0;
    public static $MODE_MAIL = 1;
    public static $MODE_SMS = 2;
    public static $MODE_SCRIPT = 3;

    private function ModeMapping()
    {
        return [
            self::$MODE_WEBFRONT    => [
                'tag'     => 'wf',
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
                'tag'     => 'script',
                'caption' => 'Script',
            ],
        ];
    }

    private function ModeDecode($ident)
    {
        $mode = self::$MODE_WEBFRONT;
        $modeMap = $this->ModeMapping();
        foreach ($modeMap as $index => $map) {
            if ($map['tag'] == strtolower($ident)) {
                $mode = $index;
                break;
            }
        }
        return $mode;
    }

    private function TargetEncode(string $user_id, string $mode)
    {
        return $user_id . '/' . $mode;
    }

    private function TargetDecode(string $target)
    {
        $r = explode('/', $target);
        return [
            'user_id'      => $r[0],
            'mode'         => isset($r[1]) ? $r[1] : '',
        ];
    }

    public static $SEVERITY_UNKNOWN = 0;
    public static $SEVERITY_INFO = 1;
    public static $SEVERITY_NOTICE = 2;
    public static $SEVERITY_WARN = 3;
    public static $SEVERITY_ALERT = 4;
    public static $SEVERITY_DEBUG = 9;

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
            self::$SEVERITY_DEBUG => [
                'tag'     => 'debug',
                'caption' => 'Debug',
            ],
        ];
    }

    private function SeverityEncode($val, $useCaption)
    {
        $maps = $this->SeverityMapping();
        if (isset($maps[$val])) {
            $map = $maps[$val];
        } else {
            $map = $maps[self::$SEVERITY_INFO];
        }
        return $useCaption ? $this->Translate($map['caption']) : $map['tag'];
    }

    private function SeverityDecode($ident)
    {
        $severity = self::$SEVERITY_INFO;
        $maps = $this->SeverityMapping();
        foreach ($maps as $index => $map) {
            if ($map['tag'] == strtolower($ident)) {
                $severity = $index;
                break;
            }
        }
        return $severity;
    }

    private function SeverityAsOptions(bool $withUndef)
    {
        $maps = $this->SeverityMapping();
        $opts = [];
        if ($withUndef) {
            $opts[] = [
                'caption' => 'undef',
                'value'   => self::$SEVERITY_UNKNOWN,
            ];
        }
        foreach ($maps as $i => $e) {
            $opts[] = [
                'caption' => $e['caption'],
                'value'   => $i,
            ];
        }
        return $opts;
    }

    public static $USAGE_UNKNOWN = 0;
    public static $USAGE_ALWAYS = 1;
    public static $USAGE_IF_PRESENT = 2;
    public static $USAGE_IF_AWAY = 3;
    public static $USAGE_FIRST_OF_PERSENT = 4;
    public static $USAGE_LAST_GONE = 5;
    public static $USAGE_FIRST_COME = 6;
    public static $USAGE_IF_NO_ONE = 7;

    private function UsageMapping()
    {
        return [
            self::$USAGE_ALWAYS => [
                'caption' => 'always',
            ],
            self::$USAGE_IF_PRESENT => [
                'caption' => 'if at home',
            ],
            self::$USAGE_IF_AWAY => [
                'caption' => 'if away',
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

    private function UsageAsOptions()
    {
        $maps = $this->UsageMapping();
        $opts = [];
        foreach ($maps as $u => $e) {
            $opts[] = [
                'caption' => $e['caption'],
                'value'   => $u,
            ];
        }
        return $opts;
    }

    public static $LOGLEVEL_NONE = 0;
    public static $LOGLEVEL_NOTIFY = 1;
    public static $LOGLEVEL_MESSAGE = 2;

    private function LoglevelMapping()
    {
        return [
            self::$LOGLEVEL_NONE    => [
                'caption' => 'None',
            ],
            self::$LOGLEVEL_NOTIFY   => [
                'caption' => 'Notify',
            ],
            self::$LOGLEVEL_MESSAGE    => [
                'caption' => 'Message',
            ],
        ];
    }

    private function LoglevelAsOptions()
    {
        $maps = $this->LoglevelMapping();
        $opts = [];
        foreach ($maps as $u => $e) {
            $opts[] = [
                'caption' => $e['caption'],
                'value'   => $u,
            ];
        }
        return $opts;
    }

    private function WebfrontSounds()
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
