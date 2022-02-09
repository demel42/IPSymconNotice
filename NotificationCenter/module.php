<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php'; // globale Funktionen
require_once __DIR__ . '/../libs/local.php';  // lokale Funktionen

class NotificationCenter extends IPSModule
{
    use NotificationCommonLib;
    use NotificationLocalLib;

    private static $notification_max_age = 180;
    private static $semaphoreID = __CLASS__ . 'Data';
    private static $semaphoreTM = 5 * 1000;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('persons', json_encode([]));

        // MODE_WFC
        $this->RegisterPropertyString('default_wfc_sound_info', '');
        $this->RegisterPropertyString('default_wfc_sound_notice', '');
        $this->RegisterPropertyString('default_wfc_sound_warn', '');
        $this->RegisterPropertyString('default_wfc_sound_alert', '');
        $this->RegisterPropertyString('wfc_defaults', '');

        // MODE_MAIL
        $this->RegisterPropertyInteger('mail_instID', 0);
        $this->RegisterPropertyString('mail_defaults', '');

        // MODE_SMS
        $this->RegisterPropertyInteger('sms_instID', 0);
        $this->RegisterPropertyString('sms_defaults', '');

        // MODE_SCRIPT
        $this->RegisterPropertyInteger('scriptID', 0);
        $this->RegisterPropertyString('default_script_sound_info', '');
        $this->RegisterPropertyString('default_script_sound_notice', '');
        $this->RegisterPropertyString('default_script_sound_warn', '');
        $this->RegisterPropertyString('default_script_sound_alert', '');
        $this->RegisterPropertyString('script_defaults', '');

        $this->InstallVarProfiles(false);
    }

    private function CheckConfiguration()
    {
        $s = '';
        $r = [];

        $s = $this->ReadPropertyString('wfc_defaults');
        if ($s != false) {
            @$j = json_decode($s, true);
            if ($j == false) {
                $this->SendDebug(__FUNCTION__, '"wfc_defaults" has no json-coded content "' . $s . '"', 0);
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

        $persons = json_decode($this->ReadPropertyString('persons'), true);
        $n_persons = 0;
        if ($persons != false) {
            foreach ($persons as $person) {
                $ignore = $person['ignore'];
                if ($ignore == true) {
                    continue;
                }
                $n_persons++;
                $abbreviation = $person['abbreviation'];
                $s = $person['script_params'];
                if ($s != false) {
                    @$j = json_decode($s, true);
                    if ($j == false) {
                        $this->SendDebug(__FUNCTION__, '"persons.script_params" has no json-coded content "' . $s . '"', 0);
                        $field = $this->Translate('Script params');
                        $r[] = $this->TranslateFormat('Person "{$abbreviation}": field "{$field}" must be json-coded', ['{$abbreviation}' => $abbreviation, '{$field}' => $field]);
                    }
                }
            }
        }
        if ($n_persons == 0) {
            $this->SendDebug(__FUNCTION__, '"persons" is empty', 0);
            $r[] = $this->Translate('at minimum one valid persons must be defined');
        }

        if ($r != []) {
            $s = $this->Translate('The following points of the configuration are incorrect') . ':' . PHP_EOL;
            foreach ($r as $p) {
                $s .= '- ' . $p . PHP_EOL;
            }
        }

        return $s;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $vpos = 0;
        $this->MaintainVariable('AllAbsent', $this->Translate('all absent'), VARIABLETYPE_BOOLEAN, 'Notification.YesNo', $vpos++, true);
        $this->MaintainVariable('LastGone', $this->Translate('last gone'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('FirstCome', $this->Translate('first come'), VARIABLETYPE_STRING, '', $vpos++, true);

        $vpos = 90;
        $this->MaintainVariable('Notifications', $this->Translate('Notifications'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, true);

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

        $persons = json_decode($this->ReadPropertyString('persons'), true);
        if ($persons == false) {
            $persons = [];
        }

        $identList = [];
        foreach ($persons as $person) {
            $abbreviation = $person['abbreviation'];
            if ($abbreviation == false) {
                continue;
            }
            $name = $person['name'];

            $ident = 'PresenceState_' . strtoupper($abbreviation);
            $desc = $this->Translate('Presence state of') . ' ' . $name;

            $this->MaintainVariable($ident, $desc, VARIABLETYPE_INTEGER, 'Notification.Presence', $vpos++, true);
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

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['mail_instID', 'sms_instID', 'scriptID'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid > 0) {
                $this->RegisterReference($oid);
            }
        }
        $persons = json_decode($this->ReadPropertyString('persons'), true);
        if ($persons != false) {
            foreach ($persons as $person) {
                $oid = $this->GetArrayElem($person, 'wfc_instID', 0);
                if ($oid > 0) {
                    $this->RegisterReference($oid);
                }
            }
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    protected function GetFormElements()
    {
        $formElements = [];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Notification center'
        ];

        $s = $this->CheckConfiguration();
        if ($s != '') {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => $s
            ];
            $formElements[] = [
                'type'    => 'Label',
            ];
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Basic configuration',
            'expanded ' => false,
            'items'     => [
                [
                    'type'      => 'ExpansionPanel',
                    'caption'   => 'Webfront',
                    'expanded ' => false,
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
                                    'options'  => $this->WfcSounds(),
                                    'name'     => 'default_wfc_sound_info',
                                    'caption'  => 'Information',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'Select',
                                    'options'  => $this->WfcSounds(),
                                    'name'     => 'default_wfc_sound_notice',
                                    'caption'  => 'Notice',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'Select',
                                    'options'  => $this->WfcSounds(),
                                    'name'     => 'default_wfc_sound_warn',
                                    'caption'  => 'Warning',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'Select',
                                    'options'  => $this->WfcSounds(),
                                    'name'     => 'default_wfc_sound_alert',
                                    'caption'  => 'Alert',
                                    'width'    => '200px',
                                ],
                            ]
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'wfc_defaults',
                            'caption' => 'Other defaults',
                            'width'   => '800px',
                        ],
                    ],
                ],
                [
                    'type'      => 'ExpansionPanel',
                    'caption'   => 'E-Mail',
                    'expanded ' => false,
                    'items'     => [
                        [
                            'type'         => 'SelectInstance',
                            'validModules' => ['{375EAF21-35EF-4BC4-83B3-C780FD8BD88A}'],
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
                    'expanded ' => false,
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
                    'expanded ' => false,
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
                                    'caption' => 'Default sounds',
                                ],
                                [
                                    'type'     => 'ValidationTextBox',
                                    'name'     => 'default_script_sound_info',
                                    'caption'  => 'Information',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'ValidationTextBox',
                                    'name'     => 'default_script_sound_notice',
                                    'caption'  => 'Notice',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'ValidationTextBox',
                                    'name'     => 'default_script_sound_warn',
                                    'caption'  => 'Warning',
                                    'width'    => '200px',
                                ],
                                [
                                    'type'     => 'ValidationTextBox',
                                    'name'     => 'default_script_sound_alert',
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
            'caption' => 'Abbreviation',
            'name'    => 'abbreviation',
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
            'name'    => 'wfc_instID',
            'width'   => '200px',
            'add'     => 0,
            'edit'    => [
                'type'         => 'SelectInstance',
                'validModules' => ['{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}'],
            ],
        ];
        if ($this->ReadPropertyInteger('mail_instID') > 0) {
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
        if ($this->ReadPropertyInteger('sms_instID') > 0) {
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
        if ($this->ReadPropertyInteger('scriptID') > 0) {
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
            'caption' => 'Ignore',
            'name'    => 'ignore',
            'add'     => false,
            'width'   => '100px',
            'edit'    => [
                'type' => 'CheckBox',
            ],
        ];

        $formElements[] = [
            'type'     => 'List',
            'name'     => 'persons',
            'caption'  => 'Persons',
            'rowCount' => 5,
            'add'      => true,
            'delete'   => true,
            'columns'  => $columns,
            'sort'     => [
                'column'    => 'name',
                'direction' => 'ascending'
            ],
        ];

        return $formElements;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'Button',
                    'caption' => 'Re-install variable-profiles',
                    'onClick' => 'Notification_InstallVarProfiles($id, true);'
                ]
            ]
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ],
            ]
        ];

        $formActions[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Information',
            'items'   => [
                [
                    'type'    => 'Label',
                    'caption' => $this->InstanceInfo($this->InstanceID),
                ],
            ],
        ];

        return $formActions;
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $r = false;

        if (preg_match('#^PresenceState_#', $Ident)) {
            $persons = json_decode($this->ReadPropertyString('persons'), true);
            if ($persons != false) {
                foreach ($persons as $person) {
                    $abbreviation = $person['abbreviation'];
                    $ident = 'PresenceState_' . strtoupper($abbreviation);
                    if ($ident == $Ident) {
                        @$varID = $this->GetIDForIdent($Ident);
                        $r = $this->SetPresenceState($Ident, (int) $Value);
                        return;
                    }
                }
            }
        }

        switch ($Ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $Ident, 0);
                break;
        }
    }

    private function CheckPresenceState(int $state)
    {
        $r = IPS_GetVariableProfile('Notification.Presence');
        foreach ($r['Associations'] as $a) {
            if ($a['Value'] == (int) $state) {
                return true;
            }
        }
        return false;
    }

    public function SetPresenceState(string $ident, int $state)
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

        $pers = false;
        if (preg_match('#^PresenceState_(.*)$#', $ident, $r)) {
            $pers = $r[1];
        }

        $old_state = $this->GetValue($ident);
        if ($old_state != $state) {
            $this->SetValue($ident, $state);
        }

        $n_person = 0;
        $n_present = 0;
        $n_absent = 0;

        $persons = json_decode($this->ReadPropertyString('persons'), true);
        if ($persons != false) {
            foreach ($persons as $person) {
                if ($person['ignore']) {
                    continue;
                }
                $n_person++;
                $abbreviation = $person['abbreviation'];
                $ident = 'PresenceState_' . strtoupper($abbreviation);
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
            $last_gone = $pers;
        }
        if ($n_present == 1 && $old_state != self::$STATE_AT_HOME && $state == self::$STATE_AT_HOME) {
            $first_come = $pers;
        }
        $this->SendDebug(__FUNCTION__, 'pers=' . $pers . ' (' . $dir . '), #total=' . $n_person . ', #present=' . $n_present . ', #absent=' . $n_absent, 0);

        if ($old_state != $state) {
            $this->SetValue('LastGone', $last_gone);
            $this->SetValue('FirstCome', $first_come);
        }

        $this->SetValue('AllAbsent', $n_present == 0);

        return true;
    }

    public function GetPersonName(string $abbreviation)
    {
        $abbreviation = strtoupper($abbreviation);
        $persons = json_decode($this->ReadPropertyString('persons'), true);
        if ($persons != false) {
            foreach ($persons as $person) {
                if (strtoupper($person['abbreviation']) == $abbreviation) {
                    return $person['name'];
                }
            }
        }
        return false;
    }

    public function GetPerson(string $abbreviation)
    {
        $abbreviation = strtoupper($abbreviation);
        $persons = json_decode($this->ReadPropertyString('persons'), true);
        if ($persons != false) {
            foreach ($persons as $person) {
                if (strtoupper($person['abbreviation']) == $abbreviation) {
                    return $person;
                }
            }
        }
        return false;
    }

    public function DeliverWFC(string $abbreviation, string $text, array $params)
    {
        $this->SendDebug(__FUNCTION__, 'abbreviation=' . $abbreviation . ', text=' . $text . ', params=' . print_r($params, true), 0);

        $default_wfc_sound_info = $this->ReadPropertyString('default_wfc_sound_info');
        $default_wfc_sound_notice = $this->ReadPropertyString('default_wfc_sound_notice');
        $default_wfc_sound_warn = $this->ReadPropertyString('default_wfc_sound_warn');
        $default_wfc_sound_alert = $this->ReadPropertyString('default_wfc_sound_alert');

        $person = $this->GetPerson($abbreviation);
        if ($person == false) {
            $this->SendDebug(__FUNCTION__, 'unknown person "' . $abbreviation . '"', 0);
            return false;
        }

        $wfc_instID = $person['wfc_instID'];
        if ($wfc_instID == 0) {
            $this->SendDebug(__FUNCTION__, 'no WFC-instance given', 0);
            return false;
        }
        @$inst = IPS_GetInstance($wfc_instID);
        if ($inst == false) {
            $this->SendDebug(__FUNCTION__, 'WFC-instance ' . $wfc_instID . ' is invalid', 0);
            return false;
        }
        $status = $inst['InstanceStatus'];
        if ($status != IS_ACTIVE) {
            $this->SendDebug(__FUNCTION__, 'WFC-instance ' . $wfc_instID . ' is not active (status=' . $status . ')', 0);
            return false;
        }
        $moduleID = $inst['ModuleInfo']['ModuleID'];
        if ($moduleID != '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}') {
            $this->SendDebug(__FUNCTION__, 'WFC-instance ' . $wfc_instID . ' has wrong GUID ' . $moduleID, 0);
            return false;
        }

        $wfc_defaults = json_decode($this->ReadPropertyString('wfc_defaults'), true);
        if ($wfc_defaults == false) {
            $wfc_defaults = [];
        }
        if (is_array($params) == false) {
            $params = json_decode($params, true);
        }
        if ($params == false) {
            $params = [];
        }
        $params = array_merge($wfc_defaults, $params);

        $text = $this->GetArrayElem($params, 'text', $text);
        $subject = $this->GetArrayElem($params, 'subject', $text);
        $sound = $this->GetArrayElem($params, 'sound', '');
        if ($sound == '') {
            $severity = $this->GetArrayElem($params, 'severity', self::$SEVERITY_UNKNOWN);
            switch ($severity) {
                case self::$SEVERITY_INFO:
                    $sound = $default_wfc_sound_info;
                    break;
                case self::$SEVERITY_NOTICE:
                    $sound = $default_wfc_sound_notice;
                    break;
                case self::$SEVERITY_WARN:
                    $sound = $default_wfc_sound_warn;
                    break;
                case self::$SEVERITY_ALERT:
                    $sound = $default_wfc_sound_alert;
                    break;
                default:
                    break;
            }
            $this->SendDebug(__FUNCTION__, 'severity=' . $severity . ', sound=' . $sound, 0);
        }
        $targetID = $this->GetArrayElem($params, 'TargetID', 0);

        @$r = WFC_PushNotification($wfc_instID, $subject, $text, $sound, $targetID);
        $this->SendDebug(__FUNCTION__, 'WFC_PushNotification(' . $wfc_instID . ', "' . $subject . '", "' . $text . '", ' . $sound . ', ' . $targetID . ')=' . $r, 0);
        return $r;
    }

    public function DeliverMail(string $abbreviation, string $text, array $params)
    {
        $this->SendDebug(__FUNCTION__, 'abbreviation=' . $abbreviation . ', text=' . $text . ', params=' . print_r($params, true), 0);

        $person = $this->GetPerson($abbreviation);
        if ($person == false) {
            $this->SendDebug(__FUNCTION__, 'unknown person "' . $abbreviation . '"', 0);
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

        $mail_addr = $person['mail_addr'];
        if ($mail_addr == false) {
            $this->SendDebug(__FUNCTION__, 'person "' . $abbreviation . '" has no given mail-address', 0);
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

        $text = $this->GetArrayElem($params, 'text', $text);
        $subject = $this->GetArrayElem($params, 'subject', $text);

        @$r = SMTP_SendMailEx($mail_instID, $mail_addr, $subject, $text);
        $this->SendDebug(__FUNCTION__, 'SMTP_SendMailEx(' . $mail_instID . ', ' . $mail_addr . ', "' . $subject . '", "' . $text . '")=' . $r, 0);
        return $r;
    }

    public function DeliverSMS(string $abbreviation, string $text, array $params)
    {
        $this->SendDebug(__FUNCTION__, 'abbreviation=' . $abbreviation . ', text=' . $text . ', params=' . print_r($params, true), 0);

        $person = $this->GetPerson($abbreviation);
        if ($person == false) {
            $this->SendDebug(__FUNCTION__, 'unknown person "' . $abbreviation . '"', 0);
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

        $sms_telno = $person['sms_telno'];
        if ($sms_telno == false) {
            $this->SendDebug(__FUNCTION__, 'person "' . $abbreviation . '" has no given sms-telno', 0);
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

        $text = $this->GetArrayElem($params, 'text', $text);

        switch ($moduleID) {
            case '{96102E00-FD8C-4DD3-A3C2-376A44895AC2}': // SMS REST
                @$r = SMS_Send($sms_instID, $sms_telno, $text);
                $this->SendDebug(__FUNCTION__, 'SMS_Send(' . $sms_instID . ', ' . $sms_telno . ', "' . $text . '")=' . $r, 0);
                break;
            case '{D8C71279-8E04-4466-8996-04B6B6CF2B1D}': // Sipgate
                @$r = Sipgate_SendSMS($sms_instID, $sms_telno, $text);
                $this->SendDebug(__FUNCTION__, 'Sipgate_SendSMS(' . $sms_instID . ', ' . $sms_telno . ', "' . $text . '")=' . $r, 0);
                break;
            default:
                break;
        }
        return $r;
    }

    public function DeliverScript(string $abbreviation, string $text, array $params)
    {
        $this->SendDebug(__FUNCTION__, 'abbreviation=' . $abbreviation . ', text=' . $text . ', params=' . print_r($params, true), 0);

        $default_script_sound_info = $this->ReadPropertyString('default_script_sound_info');
        $default_script_sound_notice = $this->ReadPropertyString('default_script_sound_notice');
        $default_script_sound_warn = $this->ReadPropertyString('default_script_sound_warn');
        $default_script_sound_alert = $this->ReadPropertyString('default_script_sound_alert');

        $person = $this->GetPerson($abbreviation);
        if ($person == false) {
            $this->SendDebug(__FUNCTION__, 'unknown person "' . $abbreviation . '"', 0);
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

        $script_params = json_decode($person['script_params'], true);
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
        $params['abbreviation'] = $abbreviation;
        $params['name'] = $person['name'];
        $params['text'] = $this->GetArrayElem($params, 'text', $text);
        $sound = $this->GetArrayElem($params, 'sound', '');
        if ($sound == '') {
            $severity = $this->GetArrayElem($params, 'severity', self::$SEVERITY_UNKNOWN);
            switch ($severity) {
                case self::$SEVERITY_INFO:
                    $sound = $default_script_sound_info;
                    break;
                case self::$SEVERITY_NOTICE:
                    $sound = $default_script_sound_notice;
                    break;
                case self::$SEVERITY_WARN:
                    $sound = $default_script_sound_warn;
                    break;
                case self::$SEVERITY_ALERT:
                    $sound = $default_script_sound_alert;
                    break;
                default:
                    break;
            }
            $this->SendDebug(__FUNCTION__, 'severity=' . $severity . ', sound=' . $sound, 0);
        }

        @$r = IPS_RunScriptWaitEx($scriptID, $params);
        $this->SendDebug(__FUNCTION__, 'IPS_RunScriptWaitEx(' . $scriptID . ', ' . print_r($params, true) . ')=' . $r, 0);
        return $r;
    }

    public function Deliver(string $target, string $text, array $params)
    {
        $r = $this->TargetDecode($target);
        $this->SendDebug(__FUNCTION__, 'target=' . $target . '(' . print_r($r, true) . '), text=' . $text . ', params=' . print_r($params, true), 0);
        $abbreviation = $r['abbreviation'];
        $mode = $this->ModeDecode($r['mode']);

        switch ($mode) {
            case self::$MODE_WFC:
                $res = $this->DeliverWFC($abbreviation, $text, $params);
                break;
            case self::$MODE_MAIL:
                $res = $this->DeliverMail($abbreviation, $text, $params);
                break;
            case self::$MODE_SMS:
                $res = $this->DeliverSMS($abbreviation, $text, $params);
                break;
            case self::$MODE_SCRIPT:
                $res = $this->DeliverScript($abbreviation, $text, $params);
                break;
            default:
                $res = false;
                $this->SendDebug(__FUNCTION__, 'unknown mode "' . $r['mode'] . '"', 0);
                break;
        }
        return $res;
    }

    public function GetTargetList()
    {
        $mail_instID = $this->ReadPropertyInteger('mail_instID');
        $sms_instID = $this->ReadPropertyInteger('sms_instID');
        $scriptID = $this->ReadPropertyInteger('scriptID');

        $targets = [];

        $persons = json_decode($this->ReadPropertyString('persons'), true);
        if ($persons != false) {
            foreach ($persons as $person) {
                if ($person['ignore']) {
                    continue;
                }
                $wfc_instID = $this->GetArrayElem($person, 'wfc_instID', 0);
                if ($wfc_instID > 0) {
                    $targets[] = [
                        'person'     => $person,
                        'mode'       => self::$MODE_WFC,
                    ];
                }
                $mail_addr = $this->GetArrayElem($person, 'mail_addr', '');
                if ($mail_instID > 0 && $mail_addr != '') {
                    $targets[] = [
                        'person'     => $person,
                        'mode'       => self::$MODE_MAIL,
                    ];
                }
                $sms_telno = $this->GetArrayElem($person, 'sms_telno', '');
                if ($sms_instID > 0 && $sms_telno != '') {
                    $targets[] = [
                        'person'     => $person,
                        'mode'       => self::$MODE_SMS,
                    ];
                }
                if ($scriptID > 0) {
                    $targets[] = [
                        'person'     => $person,
                        'mode'       => self::$MODE_SCRIPT,
                    ];
                }
            }
        }
        return $targets;
    }

    public function GetPresence()
    {
        $presence = [];
        $persons = json_decode($this->ReadPropertyString('persons'), true);
        if ($persons != false) {
            $presence['last_gone'] = $this->GetValue('LastGone');
            $presence['first_come'] = $this->GetValue('FirstCome');
            $states = [];
            foreach ($persons as $person) {
                if ($person['ignore']) {
                    continue;
                }
                $abbreviation = $person['abbreviation'];
                $ident = 'PresenceState_' . strtoupper($abbreviation);
                $state = $this->GetValue($ident);
                $presence['states'][$state][] = $abbreviation;
            }
        }
        return $presence;
    }

    private function cmp_notifications($a, $b)
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

    public function Log(string $text, array $params)
    {
        $now = time();

        $severity = $this->SeverityDecode($this->GetArrayElem($params, 'severity', 'info'));
        $expires = $this->GetArrayElem($params, 'expires', '');

        if (IPS_SemaphoreEnter(self::$semaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'sempahore ' . self::$semaphoreID . ' is not accessable', 0);
            return false;
        }

        $ref_ts = $now - (self::$notification_max_age * 24 * 60 * 60);

        $new_notifications = [];
        $s = $this->GetMediaData('Data');
        $old_data = json_decode((string) $s, true);
        $old_notifications = isset($old_data['notifications']) ? $old_data['notifications'] : [];
        $counter = isset($old_data['counter']) ? $old_data['counter'] : 1;
        if ($old_notifications != '') {
            foreach ($old_notifications as $old_notification) {
                if ($old_notification['tstamp'] < $ref_ts) {
                    $this->SendDebug(__FUNCTION__, 'delete notification from ' . date('d.m.Y H:i:s', $old_notification['tstamp']), 0);
                    continue;
                }
                $new_notifications[] = $old_notification;
            }
        }
        $new_notification = [
            'id'       => $counter++,
            'tstamp'   => $now,
            'text'     => $text,
            'severity' => $severity,
            'expires'  => $expires,
        ];
        if (isset($params['targets'])) {
            $new_notification['targets'] = $params['targets'];
        }
        $new_notifications[] = $new_notification;
        usort($new_notifications, ['NotificationCenter', 'cmp_notifications']);
        $new_data = $old_data;
        $new_data['counter'] = $counter;
        $new_data['notifications'] = $new_notifications;
        $s = json_encode($new_data);
        $this->SetMediaData('Data', $s, MEDIATYPE_DOCUMENT, '.dat', false);

        IPS_SemaphoreLeave(self::$semaphoreID);

        $html = $this->BuildHtmlBox($new_notifications);
        $this->SetValue('Notifications', $html);
    }

    private function BuildHtmlBox($notifications)
    {
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
        $html .= '#spalte_text { }' . PHP_EOL;
        $html .= '</style>' . PHP_EOL;

        foreach ($notifications as $notification) {
            $tstamp = $notification['tstamp'];
            $text = $notification['text'];
            $severity = $notification['severity'];
            $expires = $notification['expires'];
            $color = '';
            switch ($severity) {
                case self::$SEVERITY_INFO:
                    if ($expires == '') {
                        $expires = 24 * 60 * 60;
                    }
                    break;
                case self::$SEVERITY_NOTICE:
                    $color = '#507dca';
                    if ($expires == '') {
                        $expires = 2 * 24 * 60 * 60;
                    }
                    break;
                case self::$SEVERITY_WARN:
                    if ($expires == '') {
                        $expires = 7 * 24 * 60 * 60;
                    }
                    $color = '#f57d0c';
                    break;
                case self::$SEVERITY_ALERT:
                    $color = '#fc1a29';
                    break;
                default:
                    break;
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
                $html .= '<colgroup><col id="spalte_text"></colgroup>' . PHP_EOL;
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
            $html .= '<td' . ($color != '' ? ' style="color:' . $color . '"' : '') . '>' . $text . '</td>' . PHP_EOL;
            $html .= '</tr>' . PHP_EOL;
        }

        if ($b) {
            $html .= '</tdata>' . PHP_EOL;
            $html .= '</table>' . PHP_EOL;
        } else {
            $html .= '<center>keine Benachrichtigungen</center><br>' . PHP_EOL;
        }
        $html .= '</body>' . PHP_EOL;
        $html .= '</html>' . PHP_EOL;
        return $html;
    }
}
