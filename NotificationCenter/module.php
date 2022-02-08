<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php'; // globale Funktionen
require_once __DIR__ . '/../libs/local.php';  // lokale Funktionen

class NotificationCenter extends IPSModule
{
    use NotificationCommonLib;
    use NotificationLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('persons', json_encode([]));

        // MODE_WFC
        $wfc_sounds = [
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
            ['value' => '', 'caption' => $this->Translate('- no selection -')],
        ];
        $this->RegisterPropertyString('wfc_sounds', json_encode($wfc_sounds));
        $this->RegisterPropertyString('wfc_defaults', '');

        // MODE_MAIL
        $this->RegisterPropertyInteger('mail_instID', 0);
        $this->RegisterPropertyString('mail_defaults', '');

        // MODE_SMS
        $this->RegisterPropertyInteger('sms_instID', 0);
        $this->RegisterPropertyString('sms_defaults', '');

        // MODE_SCRIPT
        $this->RegisterPropertyInteger('scriptID', 0);
        $script_sounds = [
            ['value' => '', 'caption' => $this->Translate('- no selection -')],
        ];
        $this->RegisterPropertyString('script_sounds', json_encode($script_sounds));
        $script_icons = [
            ['value' => '', 'caption' => $this->Translate('- no selection -')],
        ];
        $this->RegisterPropertyString('script_icons', json_encode($script_icons));
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

        $wfc_sounds = json_decode($this->ReadPropertyString('wfc_sounds'), true);
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
                            'type'     => 'List',
                            'name'     => 'wfc_sounds',
                            'caption'  => 'Sounds',
                            'rowCount' => 5,
                            'add'      => true,
                            'delete'   => true,
                            'columns'  => [
                                [
                                    'name'    => 'caption',
                                    'width'   => 'auto',
                                    'caption' => 'Description',
                                    'add'     => '',
                                    'edit'    => [
                                        'type'    => 'ValidationTextBox',
                                    ],
                                ],
                                [
                                    'name'    => 'value',
                                    'width'   => '200px',
                                    'caption' => 'Value',
                                    'add'     => '',
                                    'edit'    => [
                                        'type'    => 'ValidationTextBox',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'wfc_defaults',
                            'caption' => 'Defaults'
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
                            'caption' => 'Defaults'
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
                            'caption' => 'Defaults'
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
                            'type'     => 'List',
                            'name'     => 'script_sounds',
                            'caption'  => 'Sounds',
                            'rowCount' => 5,
                            'add'      => true,
                            'delete'   => true,
                            'columns'  => [
                                [
                                    'name'    => 'caption',
                                    'width'   => 'auto',
                                    'caption' => 'Description',
                                    'add'     => '',
                                    'edit'    => [
                                        'type'    => 'ValidationTextBox',
                                    ],
                                ],
                                [
                                    'name'    => 'value',
                                    'width'   => '200px',
                                    'caption' => 'Value',
                                    'add'     => '',
                                    'edit'    => [
                                        'type'    => 'ValidationTextBox',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type'     => 'List',
                            'name'     => 'script_icons',
                            'caption'  => 'Icons',
                            'rowCount' => 5,
                            'add'      => true,
                            'delete'   => true,
                            'columns'  => [
                                [
                                    'name'    => 'caption',
                                    'width'   => 'auto',
                                    'caption' => 'Description',
                                    'add'     => '',
                                    'edit'    => [
                                        'type'    => 'ValidationTextBox',
                                    ],
                                ],
                                [
                                    'name'    => 'value',
                                    'width'   => '200px',
                                    'caption' => 'Value',
                                    'add'     => '',
                                    'edit'    => [
                                        'type'    => 'ValidationTextBox',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'script_defaults',
                            'caption' => 'Script defaults'
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

        @$r = WFC_PushNotification($wfc_instID, $subject, $text, $sound, 0);
        $this->SendDebug(__FUNCTION__, 'WFC_PushNotification(' . $wfc_instID . ', "' . $subject . '", "' . $text . '", ' . $sound . ', 0)=' . $r, 0);
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

        @$r = IPS_RunScriptWaitEx($scriptID, $params);
        $this->SendDebug(__FUNCTION__, 'IPS_RunScriptWaitEx(' . $scriptID . ', ' . print_r($params, true) . ')=' . $r, 0);
        return $r;
    }

    public function Deliver(string $target, string $text, array $params)
    {
        $r = $this->TargetDecode($target);
        $abbreviation = $r['abbreviation'];
        switch (strtoupper($r['mode'])) {
            case 'WFC':
                $res = $this->DeliverWFC($abbreviation, $text, $params);
                break;
            case 'MAIL':
                $res = $this->DeliverMail($abbreviation, $text, $params);
                break;
            case 'SMS':
                $res = $this->DeliverSMS($abbreviation, $text, $params);
                break;
            case 'Script':
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

    public function GetStemdata()
    {
        $stemdata = [
            'wfc_sounds'    => json_decode($this->ReadPropertyString('wfc_sounds'), true),
            'script_sounds' => json_decode($this->ReadPropertyString('script_sounds'), true),
            'script_icons'  => json_decode($this->ReadPropertyString('script_icons'), true),
        ];
        return $stemdata;
    }
}
