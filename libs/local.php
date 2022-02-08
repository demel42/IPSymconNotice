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

    public static $SERVERITY_INFO = 0;
    public static $SERVERITY_NOTICE = 1;
    public static $SERVERITY_WARN = 2;
    public static $SERVERITY_ALERT = 3;

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

    private function ServerityMapping()
    {
        return [
            self::$SERVERITY_INFO    => [
                'tag'     => 'info',
                'caption' => 'Information',
            ],
            self::$SERVERITY_NOTICE   => [
                'tag'     => 'notice',
                'caption' => 'Notice',
            ],
            self::$SERVERITY_WARN    => [
                'tag'     => 'warn',
                'caption' => 'Warning',
            ],
            self::$SERVERITY_ALERT => [
                'tag'     => 'alert',
                'caption' => 'Alert',
            ],
        ];
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
}
