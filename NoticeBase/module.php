<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class NoticeBase extends IPSModule
{
    use Notice\StubsCommonLib;
    use NoticeLocalLib;

    private static $semaphoreID = __CLASS__ . 'Data';
    private static $semaphoreTM = 5 * 1000;

    private $ModuleDir;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('users', json_encode([]));

        // MODE_WEBFRONT
        $this->RegisterPropertyString('default_webfront_sound_info', '');
        $this->RegisterPropertyString('default_webfront_sound_notice', '');
        $this->RegisterPropertyString('default_webfront_sound_warn', '');
        $this->RegisterPropertyString('default_webfront_sound_alert', '');
        $this->RegisterPropertyString('webfront_defaults', '');

        // MODE_MAIL
        $this->RegisterPropertyInteger('mail_instID', 0);
        $this->RegisterPropertyString('mail_defaults', '');

        // MODE_SMS
        $this->RegisterPropertyInteger('sms_instID', 0);
        $this->RegisterPropertyString('sms_defaults', '');

        // MODE_SCRIPT
        $this->RegisterPropertyInteger('scriptID', 0);
        $this->RegisterPropertyString('default_script_signal_info', '');
        $this->RegisterPropertyString('default_script_signal_notice', '');
        $this->RegisterPropertyString('default_script_signal_warn', '');
        $this->RegisterPropertyString('default_script_signal_alert', '');
        $this->RegisterPropertyString('script_defaults', '');

        // Logging
        $this->RegisterPropertyInteger('logger_scriptID', 0);
        $this->RegisterPropertyInteger('max_age', 90);

        // Darstellung
        $this->RegisterPropertyBoolean('severity_info_show', true);
        $this->RegisterPropertyInteger('severity_info_expire', 24);
        $this->RegisterPropertyInteger('severity_info_color', 0);

        $this->RegisterPropertyBoolean('severity_notice_show', true);
        $this->RegisterPropertyInteger('severity_notice_expire', 48);
        $this->RegisterPropertyInteger('severity_notice_color', 0x507dca);

        $this->RegisterPropertyBoolean('severity_warn_show', true);
        $this->RegisterPropertyInteger('severity_warn_expire', 7 * 24);
        $this->RegisterPropertyInteger('severity_warn_color', 0xf57d0c);

        $this->RegisterPropertyBoolean('severity_alert_show', true);
        $this->RegisterPropertyInteger('severity_alert_expire', 0);
        $this->RegisterPropertyInteger('severity_alert_color', 0xfc1a29);

        $this->RegisterPropertyBoolean('severity_debug_show', false);
        $this->RegisterPropertyInteger('severity_debug_expire', 0);
        $this->RegisterPropertyInteger('severity_debug_color', 0x696969);

        $this->RegisterPropertyInteger('activity_loglevel', self::$LOGLEVEL_NOTIFY);

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->InstallVarProfiles(false);

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $s = $this->ReadPropertyString('webfront_defaults');
        if ($s != false) {
            @$j = json_decode($s, true);
            if ($j == false) {
                $this->SendDebug(__FUNCTION__, '"webfront_defaults" has no json-coded content "' . $s . '"', 0);
                $field = $this->Translate('Webfront defaults');
                $r[] = $this->TranslateFormat('Field "{$field}" must be json-coded', ['{$field}' => $field]);
            }
        }
        $s = $this->ReadPropertyString('mail_defaults');
        if ($s != false) {
            @$j = json_decode($s, true);
            if ($j == false) {
                $this->SendDebug(__FUNCTION__, '"mail_defaults" has no json-coded content "' . $s . '"', 0);
                $field = $this->Translate('SMTP defaults');
                $r[] = $this->TranslateFormat('Field "{$field}" must be json-coded', ['{$field}' => $field]);
            }
        }
        $s = $this->ReadPropertyString('sms_defaults');
        if ($s != false) {
            @$j = json_decode($s, true);
            if ($j == false) {
                $this->SendDebug(__FUNCTION__, '"sms_defaults" has no json-coded content "' . $s . '"', 0);
                $field = $this->Translate('SMS defaults');
                $r[] = $this->TranslateFormat('Field "{$field}" must be json-coded', ['{$field}' => $field]);
            }
        }
        $s = $this->ReadPropertyString('script_defaults');
        if ($s != false) {
            @$j = json_decode($s, true);
            if ($j == false) {
                $this->SendDebug(__FUNCTION__, '"script_defaults" has no json-coded content "' . $s . '"', 0);
                $field = $this->Translate('Script defaults');
                $r[] = $this->TranslateFormat('Field "{$field}" must be json-coded', ['{$field}' => $field]);
            }
        }

        $users = json_decode($this->ReadPropertyString('users'), true);
        $n_users = 0;
        if ($users != false) {
            foreach ($users as $user) {
                if ($user['inactive'] == true) {
                    continue;
                }
                $n_users++;
                $user_id = $user['id'];
                $s = $this->GetArrayElem($user, 'script_params', '');
                if ($s != '') {
                    @$j = json_decode($s, true);
                    if ($j == false) {
                        $this->SendDebug(__FUNCTION__, '"users.script_params" of user ' . $user_id . ' has no json-coded content "' . $s . '"', 0);
                        $field = $this->Translate('Script params');
                        $r[] = $this->TranslateFormat('User "{$user_id}": field "{$field}" must be json-coded', ['{$user_id}' => $user_id, '{$field}' => $field]);
                    }
                }
            }
        }
        if ($n_users == 0) {
            $this->SendDebug(__FUNCTION__, '"users" is empty', 0);
            $r[] = $this->Translate('at minimum one valid users must be defined');
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = ['mail_instID', 'sms_instID', 'scriptID'];
        $this->MaintainReferences($propertyNames);
        $users = json_decode($this->ReadPropertyString('users'), true);
        if ($users != false) {
            foreach ($users as $user) {
                $oid = $this->GetArrayElem($user, 'webfront_instID', 0);
                if (IPS_InstanceExists($oid)) {
                    $this->RegisterReference($oid);
                }
            }
        }

        if ($this->CheckPrerequisites() != false) {
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->SetStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 0;
        $this->MaintainVariable('AllAbsent', $this->Translate('all absent'), VARIABLETYPE_BOOLEAN, 'Notice.YesNo', $vpos++, true);
        $this->MaintainVariable('LastGone', $this->Translate('last gone'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('FirstCome', $this->Translate('first come'), VARIABLETYPE_STRING, '', $vpos++, true);

        $vpos = 90;
        $logger_scriptID = $this->ReadPropertyInteger('logger_scriptID');
        $this->MaintainVariable('Notices', $this->Translate('Notices'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, IPS_ScriptExists($logger_scriptID) == false);

        $vpos = 100;
        $objList = [];
        $chldIDs = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($chldIDs as $chldID) {
            $obj = IPS_GetObject($chldID);
            switch ($obj['ObjectType']) {
                case OBJECTTYPE_VARIABLE:
                    if (preg_match('#^PresenceState_#', $obj['ObjectIdent'])) {
                        $objList[] = $obj;
                    }
                    break;
                default:
                    break;
            }
        }

        $users = json_decode($this->ReadPropertyString('users'), true);
        if ($users == false) {
            $users = [];
        }

        $identList = [];
        foreach ($users as $user) {
            $user_id = $user['id'];
            if ($user_id == false) {
                continue;
            }
            if ($user['inactive'] == true || $user['immobile']) {
                continue;
            }
            $name = $user['name'];

            $ident = 'PresenceState_' . strtoupper($user_id);
            $desc = $this->Translate('Presence state of') . ' ' . $name;

            $this->MaintainVariable($ident, $desc, VARIABLETYPE_INTEGER, 'Notice.Presence', $vpos++, true);
            $this->MaintainAction($ident, true);
            $identList[] = $ident;
        }

        foreach ($objList as $obj) {
            $ident = $obj['ObjectIdent'];
            if (!in_array($ident, $identList)) {
                $this->SendDebug(__FUNCTION__, 'unregister variable: ident=' . $ident, 0);
                $this->UnregisterVariable($ident);
            }
        }

        $vpos = 100;

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $this->SetStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
        }
    }

    public function MessageSink($tstamp, $senderID, $message, $data)
    {
        parent::MessageSink($tstamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
        }
    }

    protected function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Notice base');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Basic configuration',
            'expanded'  => false,
            'items'     => [
                [
                    'type'      => 'ExpansionPanel',
                    'caption'   => 'Webfront',
                    'expanded'  => false,
                    'items'     => [
                        [
                            'type'    => 'RowLayout',
                            'items'   => [
                                [
                                    'type'    => 'Label',
                                    'caption' => 'Default sounds',
                                ],
                                [
                                    'type'     => 'Select',
                                    'options'  => $this->WebfrontSounds(),
                                    'name'     => 'default_webfront_sound_info',
                                    'caption'  => 'Information',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'Select',
                                    'options'  => $this->WebfrontSounds(),
                                    'name'     => 'default_webfront_sound_notice',
                                    'caption'  => 'Notice',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'Select',
                                    'options'  => $this->WebfrontSounds(),
                                    'name'     => 'default_webfront_sound_warn',
                                    'caption'  => 'Warning',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'Select',
                                    'options'  => $this->WebfrontSounds(),
                                    'name'     => 'default_webfront_sound_alert',
                                    'caption'  => 'Alert',
                                    'width'    => '200px',
                                ],
                            ]
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'webfront_defaults',
                            'caption' => 'Other defaults',
                            'width'   => '800px',
                        ],
                    ],
                ],
                [
                    'type'      => 'ExpansionPanel',
                    'caption'   => 'E-Mail',
                    'expanded'  => false,
                    'items'     => [
                        [
                            'type'         => 'SelectModule',
                            'moduleID'     => '{375EAF21-35EF-4BC4-83B3-C780FD8BD88A}',
                            'name'         => 'mail_instID',
                            'caption'      => 'SMTP instance'
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'mail_defaults',
                            'caption' => 'Other defaults',
                            'width'   => '800px',
                        ],
                    ],
                ],
                [
                    'type'      => 'ExpansionPanel',
                    'caption'   => 'SMS',
                    'expanded'  => false,
                    'items'     => [
                        [
                            'type'         => 'SelectInstance',
                            'validModules' => ['{96102E00-FD8C-4DD3-A3C2-376A44895AC2}', '{D8C71279-8E04-4466-8996-04B6B6CF2B1D}'],
                            'name'         => 'sms_instID',
                            'caption'      => 'SMS instance'
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'sms_defaults',
                            'caption' => 'Other defaults',
                            'width'   => '800px',
                        ],
                    ],
                ],
                [
                    'type'      => 'ExpansionPanel',
                    'caption'   => 'Script',
                    'expanded'  => false,
                    'items'     => [
                        [
                            'type'    => 'SelectScript',
                            'name'    => 'scriptID',
                            'caption' => 'Script'
                        ],
                        [
                            'type'    => 'RowLayout',
                            'items'   => [
                                [
                                    'type'    => 'Label',
                                    'caption' => 'Default signals',
                                ],
                                [
                                    'type'     => 'ValidationTextBox',
                                    'name'     => 'default_script_signal_info',
                                    'caption'  => 'Information',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'ValidationTextBox',
                                    'name'     => 'default_script_signal_notice',
                                    'caption'  => 'Notice',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'ValidationTextBox',
                                    'name'     => 'default_script_signal_warn',
                                    'caption'  => 'Warning',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'ValidationTextBox',
                                    'name'     => 'default_script_signal_alert',
                                    'caption'  => 'Alert',
                                    'width'    => '200px',
                                ],
                            ]
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'script_defaults',
                            'caption' => 'Other defaults',
                            'width'   => '800px',
                        ],
                    ],
                ],
            ],
        ];

        $columns = [];
        $columns[] = [
            'caption' => 'Initial',
            'name'    => 'id',
            'add'     => '',
            'width'   => '150px',
            'edit'    => [
                'type'     => 'ValidationTextBox',
                'validate' => '^[0-9A-Za-z]+$',
            ],
        ];
        $columns[] = [
            'caption' => 'Name',
            'name'    => 'name',
            'add'     => '',
            'width'   => 'auto',
            'edit'    => [
                'type'     => 'ValidationTextBox',
            ],
        ];
        $columns[] = [
            'caption' => 'Webfront',
            'name'    => 'webfront_instID',
            'width'   => '200px',
            'add'     => 0,
            'edit'    => [
                'type'     => 'SelectModule',
                'moduleID' => '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}',
            ],
        ];
        $mail_instID = $this->ReadPropertyInteger('mail_instID');
        if (IPS_InstanceExists($mail_instID)) {
            $columns[] = [
                'caption' => 'Mail address',
                'name'    => 'mail_addr',
                'width'   => '200px',
                'add'     => '',
                'edit'    => [
                    'type'     => 'ValidationTextBox',
                    'validate' => '^$|^[a-zA-z0-9.-]+\@[a-zA-z0-9.-]+.[a-zA-Z]+.$',
                ],
            ];
        }
        $sms_instID = $this->ReadPropertyInteger('sms_instID');
        if (IPS_InstanceExists($sms_instID)) {
            $columns[] = [
                'caption' => 'SMS telno',
                'name'    => 'sms_telno',
                'width'   => '200px',
                'add'     => '',
                'edit'    => [
                    'type'     => 'ValidationTextBox',
                    'validate' => '^$|^((\\+|00)[1-9]\\d{0,3}|0 ?[1-9]|\\(00? ?[1-9][\\d ]*\\))[\\d\\-/ ]*$',
                ],
            ];
        }
        $scriptID = $this->ReadPropertyInteger('scriptID');
        if (IPS_ScriptExists($scriptID)) {
            $columns[] = [
                'caption' => 'Script params',
                'name'    => 'script_params',
                'width'   => '200px',
                'add'     => '',
                'edit'    => [
                    'type'    => 'ValidationTextBox',
                ],
            ];
        }
        $columns[] = [
            'caption' => 'inactive',
            'name'    => 'inactive',
            'add'     => false,
            'width'   => '100px',
            'edit'    => [
                'type' => 'CheckBox',
            ],
        ];
        $columns[] = [
            'caption' => 'immobile',
            'name'    => 'immobile',
            'add'     => false,
            'width'   => '100px',
            'edit'    => [
                'type' => 'CheckBox',
            ],
        ];

        $formElements[] = [
            'type'     => 'List',
            'name'     => 'users',
            'caption'  => 'Users',
            'rowCount' => 5,
            'add'      => true,
            'delete'   => true,
            'columns'  => $columns,
            'sort'     => [
                'column'    => 'name',
                'direction' => 'ascending'
            ],
        ];

        $formElements[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Logging',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'SelectScript',
                    'name'    => 'logger_scriptID',
                    'caption' => 'Script for alternate logging'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'minimum' => 0,
                    'suffix'  => 'days',
                    'name'    => 'max_age',
                    'caption' => 'maximun age of log entries'
                ],
                [
                    'type'    => 'Label',
                    'bold'    => true,
                    'caption' => 'Visualization of "Notices"',
                ],
                [
                    'type'    => 'RowLayout',
                    'items'   => [
                        [
                            'type'    => 'ColumnLayout',
                            'items'   => [
                                [
                                    'type'    => 'Label',
                                    'caption' => 'Information',
                                ],
                                [
                                    'type'     => 'CheckBox',
                                    'name'     => 'severity_info_show',
                                    'caption'  => 'Show',
                                ],
                                [
                                    'type'     => 'NumberSpinner',
                                    'minimum'  => 0,
                                    'suffix'   => 'hours',
                                    'name'     => 'severity_info_expire',
                                    'width'    => '150px',
                                    'caption'  => 'Expiration',
                                ],
                                [
                                    'type'             => 'SelectColor',
                                    'allowTransparent' => false,
                                    'name'             => 'severity_info_color',
                                    'width'            => '200px',
                                    'caption'          => 'Textcolor',
                                ],
                            ],
                        ],
                        [
                            'type'    => 'ColumnLayout',
                            'items'   => [
                                [
                                    'type'    => 'Label',
                                    'caption' => 'Notice',
                                ],
                                [
                                    'type'     => 'CheckBox',
                                    'name'     => 'severity_notice_show',
                                    'caption'  => 'Show',
                                ],
                                [
                                    'type'     => 'NumberSpinner',
                                    'minimum'  => 0,
                                    'suffix'   => 'hours',
                                    'name'     => 'severity_notice_expire',
                                    'width'    => '150px',
                                    'caption'  => 'Expiration',
                                ],
                                [
                                    'type'             => 'SelectColor',
                                    'allowTransparent' => false,
                                    'name'             => 'severity_notice_color',
                                    'width'            => '200px',
                                    'caption'          => 'Textcolor',
                                ],
                            ],
                        ],
                        [
                            'type'    => 'ColumnLayout',
                            'items'   => [
                                [
                                    'type'    => 'Label',
                                    'caption' => 'Warning',
                                ],
                                [
                                    'type'     => 'CheckBox',
                                    'name'     => 'severity_warn_show',
                                    'caption'  => 'Show',
                                ],
                                [
                                    'type'     => 'NumberSpinner',
                                    'minimum'  => 0,
                                    'suffix'   => 'hours',
                                    'name'     => 'severity_warn_expire',
                                    'width'    => '150px',
                                    'caption'  => 'Expiration',
                                ],
                                [
                                    'type'             => 'SelectColor',
                                    'allowTransparent' => false,
                                    'name'             => 'severity_warn_color',
                                    'width'            => '200px',
                                    'caption'          => 'Textcolor',
                                ],
                            ],
                        ],
                        [
                            'type'    => 'ColumnLayout',
                            'items'   => [
                                [
                                    'type'    => 'Label',
                                    'caption' => 'Alert',
                                ],
                                [
                                    'type'     => 'CheckBox',
                                    'name'     => 'severity_alert_show',
                                    'caption'  => 'Show',
                                ],
                                [
                                    'type'     => 'NumberSpinner',
                                    'minimum'  => 0,
                                    'suffix'   => 'hours',
                                    'name'     => 'severity_alert_expire',
                                    'width'    => '150px',
                                    'caption'  => 'Expiration',
                                ],
                                [
                                    'type'             => 'SelectColor',
                                    'allowTransparent' => false,
                                    'name'             => 'severity_alert_color',
                                    'width'            => '200px',
                                    'caption'          => 'Textcolor',
                                ],
                            ],
                        ],
                        [
                            'type'    => 'ColumnLayout',
                            'items'   => [
                                [
                                    'type'    => 'Label',
                                    'caption' => 'Debug',
                                ],
                                [
                                    'type'     => 'CheckBox',
                                    'name'     => 'severity_debug_show',
                                    'caption'  => 'Show',
                                ],
                                [
                                    'type'     => 'NumberSpinner',
                                    'minimum'  => 0,
                                    'suffix'   => 'hours',
                                    'name'     => 'severity_debug_expire',
                                    'width'    => '150px',
                                    'caption'  => 'Expiration',
                                ],
                                [
                                    'type'             => 'SelectColor',
                                    'allowTransparent' => false,
                                    'name'             => 'severity_debug_color',
                                    'width'            => '200px',
                                    'caption'          => 'Textcolor',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $formElements[] = [
            'type'     => 'Select',
            'options'  => $this->LoglevelAsOptions(),
            'name'     => 'activity_loglevel',
            'caption'  => 'Instance activity messages in IP-Symcon'
        ];

        return $formElements;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $logger_scriptID = $this->ReadPropertyInteger('logger_scriptID');
        if (IPS_ScriptExists($logger_scriptID) == false) {
            $formActions[] = [
                'type'    => 'Button',
                'caption' => 'Rebuild "Notices"',
                'onClick' => $this->GetModulePrefix() . '_RebuildHtml($id);'
            ];
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'Button',
                    'caption' => 'Re-install variable-profiles',
                    'onClick' => $this->GetModulePrefix() . '_InstallVarProfiles($id, true);'
                ],
            ]
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ],
                [
                    'type'    => 'Label',
                ],
                [
                    'type'    => 'RowLayout',
                    'items'   => [
                        [
                            'type'     => 'SelectModule',
                            'moduleID' => '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}',
                            'caption'  => 'Webfront',
                            'name'     => 'instID',
                        ],
                        [
                            'type'     => 'Select',
                            'options'  => $this->WebfrontSounds(),
                            'name'     => 'sound',
                            'caption'  => 'Sound',
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Test sound',
                            'onClick' => 'WFC_PushNotification($instID, "' . $this->Translate('Test sound') . '", $sound, $sound, 0);',
                        ],
                    ],
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $r = false;

        if (preg_match('#^PresenceState_#', $ident)) {
            $users = json_decode($this->ReadPropertyString('users'), true);
            if ($users != false) {
                foreach ($users as $user) {
                    if ($ident == 'PresenceState_' . strtoupper($user['id'])) {
                        @$varID = $this->GetIDForIdent($ident);
                        $r = $this->SetPresenceState($ident, (int) $value);
                        return;
                    }
                }
            }
        }

        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }

    private function CheckPresenceState(int $state)
    {
        $r = IPS_GetVariableProfile('Notice.Presence');
        foreach ($r['Associations'] as $a) {
            if ($a['Value'] == (int) $state) {
                return true;
            }
        }
        return false;
    }

    private function SetPresenceState(string $ident, int $state)
    {
        @$varID = $this->GetIDForIdent($ident);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'unknown ident ' . $ident, 0);
            return false;
        }
        if ($this->CheckPresenceState($state) == false) {
            $this->SendDebug(__FUNCTION__, 'unknown ' . $state, 0);
            return false;
        }

        $user_id = false;
        if (preg_match('#^PresenceState_(.*)$#', $ident, $r)) {
            $user_id = $r[1];
        }

        $old_state = $this->GetValue($ident);
        if ($old_state != $state) {
            $this->SetValue($ident, $state);
        }

        $n_user = 0;
        $n_present = 0;
        $n_absent = 0;

        $users = json_decode($this->ReadPropertyString('users'), true);
        if ($users != false) {
            foreach ($users as $user) {
                if ($user['inactive'] || $user['immobile']) {
                    continue;
                }
                $n_user++;
                $ident = 'PresenceState_' . strtoupper($user['id']);
                $st = $this->GetValue($ident);
                if ($st == self::$STATE_AT_HOME) {
                    $n_present++;
                } else {
                    $n_absent++;
                }
            }
        }

        $dir = $state == self::$STATE_AT_HOME ? 'present' : 'absent';
        $last_gone = '';
        $first_come = '';
        if ($n_present == 0 && $old_state == self::$STATE_AT_HOME && $state != self::$STATE_AT_HOME) {
            $last_gone = $user_id;
        }
        if ($n_present == 1 && $old_state != self::$STATE_AT_HOME && $state == self::$STATE_AT_HOME) {
            $first_come = $user_id;
        }
        $this->SendDebug(__FUNCTION__, 'user=' . $user_id . ' (' . $dir . '), #total=' . $n_user . ', #present=' . $n_present . ', #absent=' . $n_absent, 0);

        if ($old_state != $state) {
            $this->SetValue('LastGone', $last_gone);
            $this->SetValue('FirstCome', $first_come);
        }

        $this->SetValue('AllAbsent', $n_present == 0);

        return true;
    }

    private function GetUser(string $user_id)
    {
        $user_id = strtoupper($user_id);
        $users = json_decode($this->ReadPropertyString('users'), true);
        if ($users != false) {
            foreach ($users as $user) {
                if (strtoupper($user['id']) == $user_id) {
                    return $user;
                }
            }
        }
        return false;
    }

    private function DeliverWebfront(string $user_id, string $message, array $params)
    {
        $this->SendDebug(__FUNCTION__, 'user_id=' . $user_id . ', message=' . $message . ', params=' . print_r($params, true), 0);

        $default_webfront_sound_info = $this->ReadPropertyString('default_webfront_sound_info');
        $default_webfront_sound_notice = $this->ReadPropertyString('default_webfront_sound_notice');
        $default_webfront_sound_warn = $this->ReadPropertyString('default_webfront_sound_warn');
        $default_webfront_sound_alert = $this->ReadPropertyString('default_webfront_sound_alert');

        $user = $this->GetUser($user_id);
        if ($user == false) {
            $this->SendDebug(__FUNCTION__, 'unknown user "' . $user_id . '"', 0);
            return false;
        }
        if ($user['inactive']) {
            $this->SendDebug(__FUNCTION__, 'user "' . $user_id . '" is inactive', 0);
            return false;
        }

        $webfront_instID = $user['webfront_instID'];
        if ($webfront_instID == 0) {
            $this->SendDebug(__FUNCTION__, 'no WF-instance given', 0);
            return false;
        }
        @$inst = IPS_GetInstance($webfront_instID);
        if ($inst == false) {
            $this->SendDebug(__FUNCTION__, 'WF-instance ' . $webfront_instID . ' is invalid', 0);
            return false;
        }
        $status = $inst['InstanceStatus'];
        if ($status != IS_ACTIVE) {
            $this->SendDebug(__FUNCTION__, 'WF-instance ' . $webfront_instID . ' is not active (status=' . $status . ')', 0);
            return false;
        }
        $moduleID = $inst['ModuleInfo']['ModuleID'];
        if ($moduleID != '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}') {
            $this->SendDebug(__FUNCTION__, 'WF-instance ' . $webfront_instID . ' has wrong GUID ' . $moduleID, 0);
            return false;
        }

        $webfront_defaults = json_decode($this->ReadPropertyString('webfront_defaults'), true);
        if ($webfront_defaults == false) {
            $webfront_defaults = [];
        }
        if (is_array($params) == false) {
            $params = json_decode($params, true);
        }
        if ($params == false) {
            $params = [];
        }
        $params = array_merge($webfront_defaults, $params);

        $message = $this->GetArrayElem($params, 'message', $message);
        $subject = $this->GetArrayElem($params, 'subject', '');
        if ($message == '' && $subject != '') {
            $message = $subject;
            $subject = '';
        }
        if (strlen($subject) > 32) {
            $subject = substr($subject, 0, 32);
        }
        $sound = $this->GetArrayElem($params, 'sound', '');
        if ($sound == '') {
            $severity = $this->GetArrayElem($params, 'severity', self::$SEVERITY_UNKNOWN);
            switch ($severity) {
                case self::$SEVERITY_INFO:
                    $sound = $default_webfront_sound_info;
                    break;
                case self::$SEVERITY_NOTICE:
                    $sound = $default_webfront_sound_notice;
                    break;
                case self::$SEVERITY_WARN:
                    $sound = $default_webfront_sound_warn;
                    break;
                case self::$SEVERITY_ALERT:
                    $sound = $default_webfront_sound_alert;
                    break;
                default:
                    break;
            }
            $this->SendDebug(__FUNCTION__, 'severity=' . $severity . ', sound=' . $sound, 0);
        }
        $targetID = $this->GetArrayElem($params, 'TargetID', 0);

        @$r = WFC_PushNotification($webfront_instID, $subject, $message, $sound, $targetID);
        $this->SendDebug(__FUNCTION__, 'WFC_PushNotification(' . $webfront_instID . ', "' . $subject . '", "' . $message . '", "' . $sound . '", ' . $targetID . ') ' . ($r ? 'succeed' : 'failed'), 0);

        $chainS = $this->PrintCallChain(false);
        if ($r) {
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_MESSAGE) {
                $s = 'push message @' . $user_id . '(' . IPS_GetName($webfront_instID) . ') "' . $message . '" succeed (' . $chainS . ')';
                $this->LogMessage($s, KL_MESSAGE);
            }

            $s = $this->TranslateFormat('Notify {$target} succeed', ['{$target}' => $this->TargetEncode($user_id, 'wf')]);
            $this->Log($s, 'debug', []);
        } else {
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                $s = 'push message @' . $user_id . '(' . IPS_GetName($webfront_instID) . ') "' . $message . '" failed (' . $chainS . ')';
                $this->LogMessage($s, KL_NOTIFY);
            }

            $s = $this->TranslateFormat('Notify {$target} failed', ['{$target}' => $this->TargetEncode($user_id, 'wf')]);
            $this->Log($s, 'warn', []);
        }

        return $r;
    }

    private function DeliverMail(string $user_id, string $message, array $params)
    {
        $this->SendDebug(__FUNCTION__, 'user_id=' . $user_id . ', message=' . $message . ', params=' . print_r($params, true), 0);

        $user = $this->GetUser($user_id);
        if ($user == false) {
            $this->SendDebug(__FUNCTION__, 'unknown user "' . $user_id . '"', 0);
            return false;
        }
        if ($user['inactive']) {
            $this->SendDebug(__FUNCTION__, 'user "' . $user_id . '" is inactive', 0);
            return false;
        }

        $mail_instID = $this->ReadPropertyInteger('mail_instID');
        if ($mail_instID == 0) {
            $this->SendDebug(__FUNCTION__, 'no STMP-instance given', 0);
            return false;
        }
        @$inst = IPS_GetInstance($mail_instID);
        if ($inst == false) {
            $this->SendDebug(__FUNCTION__, 'STMP-instance ' . $mail_instID . ' is invalid', 0);
            return false;
        }
        $status = $inst['InstanceStatus'];
        if ($status != IS_ACTIVE) {
            $this->SendDebug(__FUNCTION__, 'STMP-instance ' . $mail_instID . ' is not active (status=' . $status . ')', 0);
            return false;
        }
        $moduleID = $inst['ModuleInfo']['ModuleID'];
        if ($moduleID != '{375EAF21-35EF-4BC4-83B3-C780FD8BD88A}') {
            $this->SendDebug(__FUNCTION__, 'STMP-instance ' . $mail_instID . ' has wrong GUID ' . $moduleID, 0);
            return false;
        }

        $mail_addr = $user['mail_addr'];
        if ($mail_addr == false) {
            $this->SendDebug(__FUNCTION__, 'user "' . $user_id . '" has no given mail-address', 0);
            return false;
        }

        $mail_defaults = json_decode($this->ReadPropertyString('mail_defaults'), true);
        if ($mail_defaults == false) {
            $mail_defaults = [];
        }
        if (is_array($params) == false) {
            $params = json_decode($params, true);
        }
        if ($params == false) {
            $params = [];
        }
        $params = array_merge($mail_defaults, $params);

        $message = $this->GetArrayElem($params, 'message', $message);
        $subject = $this->GetArrayElem($params, 'subject', $message);

        @$r = SMTP_SendMailEx($mail_instID, $mail_addr, $subject, $message);
        $this->SendDebug(__FUNCTION__, 'SMTP_SendMailEx(' . $mail_instID . ', "' . $mail_addr . '", "' . $subject . '", "' . $message . '") ' . ($r ? 'succeed' : 'failed'), 0);

        $chainS = $this->PrintCallChain(false);
        if ($r) {
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_MESSAGE) {
                $s = 'send mail @' . $user_id . '(' . $mail_addr . ') "' . $message . '" succeed (' . $chainS . ')';
                $this->LogMessage($s, KL_MESSAGE);
            }

            $s = $this->TranslateFormat('Notify {$target} succeed', ['{$target}' => $this->TargetEncode($user_id, 'mail')]);
            $this->Log($s, 'debug', []);
        } else {
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                $s = 'send mail @' . $user_id . '(' . $mail_addr . ') "' . $message . '" failed (' . $chainS . ')';
                $this->LogMessage($s, KL_NOTIFY);
            }

            $s = $this->TranslateFormat('Notify {$target} failed', ['{$target}' => $this->TargetEncode($user_id, 'mail')]);
            $this->Log($s, 'warn', []);
        }

        return $r;
    }

    private function DeliverSMS(string $user_id, string $message, array $params)
    {
        $this->SendDebug(__FUNCTION__, 'user_id=' . $user_id . ', message=' . $message . ', params=' . print_r($params, true), 0);

        $user = $this->GetUser($user_id);
        if ($user == false) {
            $this->SendDebug(__FUNCTION__, 'unknown user "' . $user_id . '"', 0);
            return false;
        }
        if ($user['inactive']) {
            $this->SendDebug(__FUNCTION__, 'user "' . $user_id . '" is inactive', 0);
            return false;
        }

        $sms_instID = $this->ReadPropertyInteger('sms_instID');
        if ($sms_instID == 0) {
            $this->SendDebug(__FUNCTION__, 'no SMS-instance given', 0);
            return false;
        }
        @$inst = IPS_GetInstance($sms_instID);
        if ($inst == false) {
            $this->SendDebug(__FUNCTION__, 'SMS-instance ' . $sms_instID . ' is invalid', 0);
            return false;
        }
        $status = $inst['InstanceStatus'];
        if ($status != IS_ACTIVE) {
            $this->SendDebug(__FUNCTION__, 'SMS-instance ' . $sms_instID . ' is not active (status=' . $status . ')', 0);
            return false;
        }

        $moduleID = $inst['ModuleInfo']['ModuleID'];
        if (in_array($moduleID, ['{96102E00-FD8C-4DD3-A3C2-376A44895AC2}', '{D8C71279-8E04-4466-8996-04B6B6CF2B1D}']) == false) {
            $this->SendDebug(__FUNCTION__, 'SMS-instance ' . $sms_instID . ' has wrong GUID ' . $moduleID, 0);
            return false;
        }

        $sms_telno = $user['sms_telno'];
        if ($sms_telno == false) {
            $this->SendDebug(__FUNCTION__, 'user "' . $user_id . '" has no given sms-telno', 0);
            return false;
        }
        $sms_telno = preg_replace('<^\\+>', '00', $sms_telno);
        $sms_telno = preg_replace('<\\D+>', '', $sms_telno);

        $sms_defaults = json_decode($this->ReadPropertyString('sms_defaults'), true);
        if ($sms_defaults == false) {
            $sms_defaults = [];
        }
        if (is_array($params) == false) {
            $params = json_decode($params, true);
        }
        if ($params == false) {
            $params = [];
        }
        $params = array_merge($sms_defaults, $params);

        $message = $this->GetArrayElem($params, 'message', $message);
        if ($message == '') {
            $message = $this->GetArrayElem($params, 'subject', '');
        }

        switch ($moduleID) {
            case '{96102E00-FD8C-4DD3-A3C2-376A44895AC2}': // SMS REST
                @$r = SMS_Send($sms_instID, $sms_telno, $message);
                $this->SendDebug(__FUNCTION__, 'SMS_Send(' . $sms_instID . ', "' . $sms_telno . '", "' . $message . '") ' . ($r ? 'succeed' : 'failed'), 0);
                break;
            case '{D8C71279-8E04-4466-8996-04B6B6CF2B1D}': // Sipgate
                @$r = Sipgate_SendSMS($sms_instID, $sms_telno, $message);
                $this->SendDebug(__FUNCTION__, 'Sipgate_SendSMS(' . $sms_instID . ', "' . $sms_telno . '", "' . $message . '") ' . ($r ? 'succeed' : 'failed'), 0);
                break;
            default:
                break;
        }

        $chainS = $this->PrintCallChain(false);
        if ($r) {
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_MESSAGE) {
                $s = 'send sms @' . $user_id . '(' . $sms_telno . ') "' . $message . '" succeed (' . $chainS . ')';
                $this->LogMessage($s, KL_MESSAGE);
            }

            $s = $this->TranslateFormat('Notify {$target} succeed', ['{$target}' => $this->TargetEncode($user_id, 'sms')]);
            $this->Log($s, 'debug', []);
        } else {
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                $s = 'send sms @' . $user_id . '(' . $sms_telno . ') "' . $message . '" failed (' . $chainS . ')';
                $this->LogMessage($s, KL_NOTIFY);
            }

            $s = $this->TranslateFormat('Notify {$target} failed', ['{$target}' => $this->TargetEncode($user_id, 'sms')]);
            $this->Log($s, 'warn', []);
        }

        return $r;
    }

    private function DeliverScript(string $user_id, string $message, array $params)
    {
        $this->SendDebug(__FUNCTION__, 'user_id=' . $user_id . ', message=' . $message . ', params=' . print_r($params, true), 0);

        $default_script_signal_info = $this->ReadPropertyString('default_script_signal_info');
        $default_script_signal_notice = $this->ReadPropertyString('default_script_signal_notice');
        $default_script_signal_warn = $this->ReadPropertyString('default_script_signal_warn');
        $default_script_signal_alert = $this->ReadPropertyString('default_script_signal_alert');

        $user = $this->GetUser($user_id);
        if ($user == false) {
            $this->SendDebug(__FUNCTION__, 'unknown user "' . $user_id . '"', 0);
            return false;
        }
        if ($user['inactive']) {
            $this->SendDebug(__FUNCTION__, 'user "' . $user_id . '" is inactive', 0);
            return false;
        }

        $scriptID = $this->ReadPropertyInteger('scriptID');
        if ($scriptID == 0) {
            $this->SendDebug(__FUNCTION__, 'no script given', 0);
            return false;
        }
        @$obj = IPS_GetObject($scriptID);
        if ($obj == false) {
            $this->SendDebug(__FUNCTION__, 'script ' . $scriptID . ' is invalid', 0);
            return false;
        }
        if ($obj['ObjectType'] != OBJECTTYPE_SCRIPT) {
            $this->SendDebug(__FUNCTION__, 'script ' . $scriptID . ' has wrong type', 0);
            return false;
        }

        $script_defaults = json_decode($this->ReadPropertyString('script_defaults'), true);
        if ($script_defaults == false) {
            $script_defaults = [];
        }

        $script_params = json_decode($user['script_params'], true);
        if ($script_params == false) {
            $script_params = [];
        }

        if (is_array($params) == false) {
            $params = json_decode($params, true);
        }
        if ($params == false) {
            $params = [];
        }
        $params = array_merge($script_defaults, $script_params, $params);
        $params['user_id'] = $user_id;
        $params['name'] = $user['name'];
        $params['message'] = $this->GetArrayElem($params, 'message', $message);
        $signal = $this->GetArrayElem($params, 'signal', '');
        if ($signal == '') {
            $severity = $this->GetArrayElem($params, 'severity', self::$SEVERITY_UNKNOWN);
            switch ($severity) {
                case self::$SEVERITY_INFO:
                    $signal = $default_script_signal_info;
                    break;
                case self::$SEVERITY_NOTICE:
                    $signal = $default_script_signal_notice;
                    break;
                case self::$SEVERITY_WARN:
                    $signal = $default_script_signal_warn;
                    break;
                case self::$SEVERITY_ALERT:
                    $signal = $default_script_signal_alert;
                    break;
                default:
                    break;
            }
            $this->SendDebug(__FUNCTION__, 'severity=' . $severity . ', signal=' . $signal, 0);
            $params['signal'] = $signal;
        }

        @$r = IPS_RunScriptWaitEx($scriptID, $params);
        $this->SendDebug(__FUNCTION__, 'IPS_RunScriptWaitEx(' . $scriptID . ', ' . print_r($params, true) . ') ' . ($r ? 'succeed' : 'failed'), 0);

        $chainS = $this->PrintCallChain(false);
        if ($r) {
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_MESSAGE) {
                $s = 'scripting @' . $user_id . ' "' . $message . '" succeed (' . $chainS . ')';
                $this->LogMessage($s, KL_MESSAGE);
            }

            $s = $this->TranslateFormat('Notify {$target} succeed', ['{$target}' => $this->TargetEncode($user_id, 'script')]);
            $this->Log($s, 'debug', []);
        } else {
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                $s = 'scripting @' . $user_id . ' "' . $message . '" failed (' . $chainS . ')';
                $this->LogMessage($s, KL_NOTIFY);
            }

            $s = $this->TranslateFormat('Notify {$target} failed', ['{$target}' => $this->TargetEncode($user_id, 'failed')]);
            $this->Log($s, 'warn', []);
        }

        return $r;
    }

    public function Deliver(string $target, string $message, array $params)
    {
        $this->PushCallChain(__FUNCTION__);

        $r = $this->TargetDecode($target);
        $this->SendDebug(__FUNCTION__, 'target=' . $target . '(' . print_r($r, true) . '), message=' . $message . ', params=' . print_r($params, true), 0);
        $user_id = $r['user_id'];
        $mode = $this->ModeDecode($r['mode']);

        switch ($mode) {
            case self::$MODE_WEBFRONT:
                $res = $this->DeliverWebfront($user_id, $message, $params);
                break;
            case self::$MODE_MAIL:
                $res = $this->DeliverMail($user_id, $message, $params);
                break;
            case self::$MODE_SMS:
                $res = $this->DeliverSMS($user_id, $message, $params);
                break;
            case self::$MODE_SCRIPT:
                $res = $this->DeliverScript($user_id, $message, $params);
                break;
            default:
                $res = false;
                $this->SendDebug(__FUNCTION__, 'unknown mode "' . $r['mode'] . '"', 0);
                break;
        }

        $this->PopCallChain(__FUNCTION__);
        return $res;
    }

    public function GetTargetList()
    {
        $mail_instID = $this->ReadPropertyInteger('mail_instID');
        $sms_instID = $this->ReadPropertyInteger('sms_instID');
        $scriptID = $this->ReadPropertyInteger('scriptID');

        $targets = [];

        $users = json_decode($this->ReadPropertyString('users'), true);
        if ($users != false) {
            foreach ($users as $user) {
                if ($user['inactive']) {
                    continue;
                }
                $webfront_instID = $this->GetArrayElem($user, 'webfront_instID', 0);
                if (IPS_InstanceExists($webfront_instID)) {
                    $targets[] = [
                        'user' => $user,
                        'mode' => self::$MODE_WEBFRONT,
                    ];
                }
                $mail_addr = $this->GetArrayElem($user, 'mail_addr', '');
                if (IPS_InstanceExists($mail_instID) && $mail_addr != '') {
                    $targets[] = [
                        'user' => $user,
                        'mode' => self::$MODE_MAIL,
                    ];
                }
                $sms_telno = $this->GetArrayElem($user, 'sms_telno', '');
                if (IPS_InstanceExists($sms_instID) && $sms_telno != '') {
                    $targets[] = [
                        'user' => $user,
                        'mode' => self::$MODE_SMS,
                    ];
                }
                if (IPS_ScriptExists($scriptID)) {
                    $targets[] = [
                        'user' => $user,
                        'mode' => self::$MODE_SCRIPT,
                    ];
                }
            }
        }
        return $targets;
    }

    public function GetPresence()
    {
        $presence = [];
        $users = json_decode($this->ReadPropertyString('users'), true);
        if ($users != false) {
            $presence['last_gone'] = $this->GetValue('LastGone');
            $presence['first_come'] = $this->GetValue('FirstCome');
            $states = [];
            foreach ($users as $user) {
                if ($user['inactive'] || $user['immobile']) {
                    continue;
                }
                $user_id = $user['id'];
                $ident = 'PresenceState_' . strtoupper($user_id);
                $state = $this->GetValue($ident);
                $presence['states'][$state][] = $user_id;
            }
        }
        return $presence;
    }

    private function cmp_notices($a, $b)
    {
        $a_tstamp = $a['tstamp'];
        $b_tstamp = $b['tstamp'];
        if ($a_tstamp != $b_tstamp) {
            return ($a_tstamp < $b_tstamp) ? -1 : 1;
        }
        $a_id = $a['id'];
        $b_id = $b['id'];
        return ($a_id < $b_id) ? -1 : 1;
    }

    public function RebuildHtml()
    {
        $logger_scriptID = $this->ReadPropertyInteger('logger_scriptID');
        if (IPS_ScriptExists($logger_scriptID)) {
            $this->SendDebug(__FUNCTION__, 'external logging enabled', 0);
            return;
        }
        $html = $this->BuildHtmlBox(false);
        $this->SetValue('Notices', $html);
    }

    public function Log(string $message, string $severity, array $params)
    {
        $this->SendDebug(__FUNCTION__, 'message=' . $message . ', severity=' . $severity . ', params=' . print_r($params, true), 0);
        $logger_scriptID = $this->ReadPropertyInteger('logger_scriptID');
        if (IPS_ScriptExists($logger_scriptID)) {
            $params['message'] = $message;
            $params['severity'] = $severity;
            @$r = IPS_RunScriptWaitEx($logger_scriptID, $params);
            $this->SendDebug(__FUNCTION__, 'IPS_RunScriptWaitEx(' . $logger_scriptID . ', ' . print_r($params, true) . ') ' . ($r ? 'succeed' : 'failed'), 0);
            return $r;
        }

        $max_age = $this->ReadPropertyInteger('max_age');

        $now = time();

        if (preg_match('/^[0-9]+$/', $severity) == false) {
            $severity = $this->SeverityDecode($severity);
            $this->SendDebug(__FUNCTION__, 'severity=' . $severity, 0);
        }
        if ($severity == self::$SEVERITY_UNKNOWN) {
            $severity = self::$SEVERITY_INFO;
        }

        $expires = $this->GetArrayElem($params, 'expires', '');

        if (IPS_SemaphoreEnter(self::$semaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'sempahore ' . self::$semaphoreID . ' is not accessable', 0);
            return false;
        }

        $ref_ts = $now - ($max_age * 24 * 60 * 60);

        $new_notices = [];
        $s = $this->GetMediaData('Data');
        $old_data = json_decode((string) $s, true);
        $old_notices = isset($old_data['notices']) ? $old_data['notices'] : [];
        $counter = isset($old_data['counter']) ? $old_data['counter'] : 1;
        if ($old_notices != '') {
            foreach ($old_notices as $old_notice) {
                if ($old_notice['tstamp'] < $ref_ts) {
                    $this->SendDebug(__FUNCTION__, 'delete notice from ' . date('d.m.Y H:i:s', $old_notice['tstamp']), 0);
                    continue;
                }
                $new_notices[] = $old_notice;
            }
        }
        $new_notice = [
            'id'          => $counter++,
            'tstamp'      => $now,
            'message'     => $message,
            'severity'    => $severity,
            'expires'     => $expires,
        ];
        if (isset($params['targets'])) {
            $new_notice['targets'] = $params['targets'];
        }
        $new_notices[] = $new_notice;
        usort($new_notices, ['NoticeBase', 'cmp_notices']);
        $new_data = $old_data;
        $new_data['counter'] = $counter;
        $new_data['notices'] = $new_notices;
        $s = json_encode($new_data);
        $this->SetMediaData('Data', $s, MEDIATYPE_DOCUMENT, '.dat', false);

        IPS_SemaphoreLeave(self::$semaphoreID);

        $html = $this->BuildHtmlBox($new_notices);
        $this->SetValue('Notices', $html);

        return true;
    }

    private function BuildHtmlBox($notices)
    {
        $severity_info_show = $this->ReadPropertyBoolean('severity_info_show');
        $severity_info_expire = $this->ReadPropertyInteger('severity_info_expire');
        $severity_info_color = $this->ReadPropertyInteger('severity_info_color');

        $severity_notice_show = $this->ReadPropertyBoolean('severity_notice_show');
        $severity_notice_expire = $this->ReadPropertyInteger('severity_notice_expire');
        $severity_notice_color = $this->ReadPropertyInteger('severity_notice_color');

        $severity_warn_show = $this->ReadPropertyBoolean('severity_warn_show');
        $severity_warn_expire = $this->ReadPropertyInteger('severity_warn_expire');
        $severity_warn_color = $this->ReadPropertyInteger('severity_warn_color');

        $severity_alert_show = $this->ReadPropertyBoolean('severity_alert_show');
        $severity_alert_expire = $this->ReadPropertyInteger('severity_alert_expire');
        $severity_alert_color = $this->ReadPropertyInteger('severity_alert_color');

        $severity_debug_show = $this->ReadPropertyBoolean('severity_debug_show');
        $severity_debug_expire = $this->ReadPropertyInteger('severity_debug_expire');
        $severity_debug_color = $this->ReadPropertyInteger('severity_debug_color');

        if ($notices == false) {
            $s = $this->GetMediaData('Data');
            $data = json_decode((string) $s, true);
            $notices = isset($data['notices']) ? $data['notices'] : [];
        }

        $now = time();
        $b = false;

        $html = '';
        $html .= '<html>' . PHP_EOL;
        $html .= '<body>' . PHP_EOL;
        $html .= '<style>' . PHP_EOL;
        $html .= 'body { margin: 1; padding: 0; font-family: "Open Sans", sans-serif; font-size: 20px; }' . PHP_EOL;
        $html .= 'table { border-collapse: collapse; border: 0px solid; margin: 0.5em;}' . PHP_EOL;
        $html .= 'th, td { padding: 1; }' . PHP_EOL;
        $html .= 'thead, tdata { text-align: left; }' . PHP_EOL;
        $html .= '#spalte_zeitpunkt { width: 125px; }' . PHP_EOL;
        $html .= '#spalte_message { }' . PHP_EOL;
        $html .= '</style>' . PHP_EOL;

        for ($i = count($notices) - 1; $i; $i--) {
            $notice = $notices[$i];
            $tstamp = $notice['tstamp'];
            $message = $notice['message'];
            $severity = $notice['severity'];
            $expires = $notice['expires'];
            $skip = false;
            $color = '';
            switch ($severity) {
                case self::$SEVERITY_INFO:
                    $skip = $severity_info_show == false;
                    if ($severity_info_color > 0) {
                        $color = '#' . dechex($severity_info_color);
                    }
                    if ($expires == '' && $severity_info_expire > 0) {
                        $expires = $severity_info_expire * 60 * 60;
                    }
                    break;
                case self::$SEVERITY_NOTICE:
                    $skip = $severity_notice_show == false;
                    if ($severity_notice_color > 0) {
                        $color = '#' . dechex($severity_notice_color);
                    }
                    if ($expires == '' && $severity_notice_expire > 0) {
                        $expires = $severity_notice_expire * 60 * 60;
                    }
                    break;
                case self::$SEVERITY_WARN:
                    $skip = $severity_warn_show == false;
                    if ($severity_warn_color > 0) {
                        $color = '#' . dechex($severity_warn_color);
                    }
                    if ($expires == '' && $severity_warn_expire > 0) {
                        $expires = $severity_warn_expire * 60 * 60;
                    }
                    break;
                case self::$SEVERITY_ALERT:
                    $skip = $severity_alert_show == false;
                    if ($severity_alert_color > 0) {
                        $color = '#' . dechex($severity_alert_color);
                    }
                    if ($expires == '' && $severity_alert_expire > 0) {
                        $expires = $severity_alert_expire * 60 * 60;
                    }
                    break;
                case self::$SEVERITY_DEBUG:
                    $skip = $severity_debug_show == false;
                    if ($severity_debug_color > 0) {
                        $color = '#' . dechex($severity_debug_color);
                    }
                    if ($expires == '' && $severity_debug_expire > 0) {
                        $expires = $severity_debug_expire * 60 * 60;
                    }
                    break;
                default:
                    $skip = true;
                    break;
            }

            if ($skip) {
                continue;
            }

            if ($expires != '') {
                $expires = $tstamp + $expires;
                if ($expires < $now) {
                    continue;
                }
            }

            if (!$b) {
                $html .= '<table>' . PHP_EOL;
                $html .= '<colgroup><col id="spalte_zeitpunkt"></colgroup>' . PHP_EOL;
                $html .= '<colgroup><col id="spalte_message"></colgroup>' . PHP_EOL;
                $html .= '<colgroup></colgroup>' . PHP_EOL;
                $html .= '<thead>' . PHP_EOL;
                $html .= '<tr>' . PHP_EOL;
                $html .= '<th>Zeitpunkt</th>' . PHP_EOL;
                $html .= '<th>Nachricht</th>' . PHP_EOL;
                $html .= '</tr>' . PHP_EOL;
                $html .= '</thead>' . PHP_EOL;
                $html .= '<tdata>' . PHP_EOL;
                $b = true;
            }

            $dt = date('d.m. H:i', $tstamp);

            $html .= '<tr>' . PHP_EOL;
            $html .= '<td>' . $dt . '</td>' . PHP_EOL;
            $html .= '<td' . ($color != '' ? ' style="color:' . $color . '"' : '') . '>' . $message . '</td>' . PHP_EOL;
            $html .= '</tr>' . PHP_EOL;
        }

        if ($b) {
            $html .= '</tdata>' . PHP_EOL;
            $html .= '</table>' . PHP_EOL;
        } else {
            $html .= '<center>keine Mitteilungen</center><br>' . PHP_EOL;
        }
        $html .= '</body>' . PHP_EOL;
        $html .= '</html>' . PHP_EOL;
        return $html;
    }
}
