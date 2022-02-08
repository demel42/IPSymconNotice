<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php'; // globale Funktionen
require_once __DIR__ . '/../libs/local.php';  // lokale Funktionen

class NotificationRule extends IPSModule
{
    use NotificationCommonLib;
    use NotificationLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyInteger('notificationCenter', 0);

        $this->RegisterPropertyString('default_subject', '');
        $this->RegisterPropertyString('default_text', '');
        $this->RegisterPropertyString('default_wfc_sound', '');
        $this->RegisterPropertyString('default_script_sound', '');
        $this->RegisterPropertyString('default_script_icons', '');

        $this->RegisterPropertyString('recipients', json_encode([]));

        $this->InstallVarProfiles(false);
    }

    private function GetNotificationCenter()
    {
        $notificationCenter = $this->ReadPropertyInteger('notificationCenter');
        $ids = IPS_GetInstanceListByModuleID('{4CF21C1E-B0F8-5535-8B5D-01ADDDB5DFD7}');
        foreach ($ids as $id) {
            if ($notificationCenter == 0 || $notificationCenter == $id) {
                return $id;
            }
        }
        return 0;
    }

    private function GetTargetList()
    {
        $targets = false;
        $notificationCenter = $this->GetNotificationCenter();
        if ($notificationCenter > 0) {
            $targets = Notification_GetTargetList($notificationCenter);
        }
        $this->SendDebug(__FUNCTION__, 'targets=' . print_r($targets, true), 0);
        return $targets;
    }

    private function GetPresence()
    {
        $presence = false;
        $notificationCenter = $this->GetNotificationCenter();
        if ($notificationCenter > 0) {
            $presence = Notification_GetPresence($notificationCenter);
        }
        $this->SendDebug(__FUNCTION__, 'presence=' . print_r($presence, true), 0);
        return $presence;
    }

    private function GetStemdata()
    {
        $stemdata = false;
        $notificationCenter = $this->GetNotificationCenter();
        if ($notificationCenter > 0) {
            $stemdata = Notification_GetStemdata($notificationCenter);
        }
        $this->SendDebug(__FUNCTION__, 'stemdata=' . print_r($stemdata, true), 0);
        return $stemdata;
    }

    private function CheckConfiguration()
    {
        $s = '';
        $r = [];

        if ($this->GetNotificationCenter() == 0) {
            $this->SendDebug(__FUNCTION__, '"notificationCenter" ist empty and no global NotificationCenter-instance', 0);
            $field = $this->Translate('Notification center');
            $r[] = $this->TranslateFormat('Field "{$field}" is not configured', ['{$field}' => $field]);
        }

        $recipients = json_decode($this->ReadPropertyString('recipients'), true);
        $n_recipients = 0;
        if ($recipients != false) {
            foreach ($recipients as $recipient) {
                $n_recipients++;
                $target = $recipient['target'];
                $s = $this->GetArrayElem($recipient, 'params', '');
                if ($s != false) {
                    @$j = json_decode($s, true);
                    if ($j == false) {
                        $this->SendDebug(__FUNCTION__, '"recipients.params" has no json-coded content "' . $s . '"', 0);
                        $field = $this->Translate('Params');
                        $r[] = $this->TranslateFormat('Target "{$target}": field "{$field}" must be json-coded', ['{$target}' => $target, '{$field}' => $field]);
                    }
                }
            }
        }

        if ($n_recipients == 0) {
            $this->SendDebug(__FUNCTION__, '"recipients" is empty', 0);
            $r[] = $this->Translate('at minimum one valid recipients must be defined');
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

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }

        $propertyNames = ['notificationCenter'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid > 0) {
                $this->RegisterReference($oid);
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
            'caption' => 'Notification rule'
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
            'type'         => 'SelectInstance',
            'validModules' => ['{4CF21C1E-B0F8-5535-8B5D-01ADDDB5DFD7}'],
            'name'         => 'notificationCenter',
            'caption'      => 'Notification center'
        ];

        $usageMapping = $this->UsageMapping();
        $usage_opts = [];
        foreach ($usageMapping as $u => $e) {
            $usage_opts[] = [
                'caption' => $e['caption'],
                'value'   => $u,
            ];
        }

        $targets = $this->GetTargetList();
        $modeMapping = $this->ModeMapping();
        $target_opts = [];
        foreach ($targets as $target) {
            if (isset($modeMapping[$target['mode']]) == false) {
                continue;
            }
            $c = $modeMapping[$target['mode']]['caption'];
            $v = $modeMapping[$target['mode']]['tag'];
            $target_opts[] = [
                'caption' => $target['person']['name'] . '/' . $this->Translate($c),
                'value'   => $this->TargetEncode($target['person']['abbreviation'], $v),
            ];
        }

        $items = [];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'default_subject',
            'caption' => 'Default value for "subject"',
        ];
        $items[] = [
            'type'      => 'ValidationTextBox',
            'name'      => 'default_text',
            'caption'   => 'Default value for "message text"',
            'multiline' => true,
            'width'     => '1000px',
        ];

        $stemdata = $this->GetStemdata();

        $wfc_sounds = $stemdata['wfc_sounds'];
        $n_wfc_sounds = 0;
        foreach ($wfc_sounds as $wfc_sound) {
            if ($wfc_sound['value'] != '') {
                $n_wfc_sounds++;
            }
        }
        if ($n_wfc_sounds > 0) {
            $items[] = [
                'type'     => 'Select',
                'options'  => $wfc_sounds,
                'name'     => 'default_wfc_sound',
                'caption'  => 'Default webfront sound',
            ];
        }

        $script_sounds = $stemdata['script_sounds'];
        $n_script_sounds = 0;
        foreach ($script_sounds as $script_sound) {
            if ($script_sound['value'] != '') {
                $n_script_sounds++;
            }
        }
        if ($n_script_sounds > 0) {
            $items[] = [
                'type'     => 'Select',
                'options'  => $script_sounds,
                'name'     => 'default_script_sound',
                'caption'  => 'Default script sound',
            ];
        }
        $script_icons = $stemdata['script_icons'];
        $n_script_icons = 0;
        foreach ($script_icons as $script_icon) {
            if ($script_icon['value'] != '') {
                $n_script_icons++;
            }
        }
        if ($n_script_icons > 0) {
            $items[] = [
                'type'     => 'Select',
                'options'  => $script_icons,
                'name'     => 'default_script_icon',
                'caption'  => 'Default script icon',
            ];
        }

        $formElements[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Default values',
            'expanded ' => false,
            'items'     => $items,
        ];

        $formElements[] = [
            'type'        => 'List',
            'name'        => 'recipients',
            'caption'     => 'Recipients',
            'rowCount'    => 5,
            'add'         => true,
            'delete'      => true,
            'changeOrder' => true,
            'columns'     => [
                [
                    'caption' => 'Usage',
                    'name'    => 'usage',
                    'add'     => self::$USAGE_EVERYONE,
                    'width'   => '250px',
                    'edit'    => [
                        'type'     => 'Select',
                        'options'  => $usage_opts,
                    ],
                ],
                [
                    'name'    => 'target',
                    'caption' => 'Target',
                    'add'     => '',
                    'width'   => '300px',
                    'edit'    => [
                        'type'     => 'Select',
                        'options'  => $target_opts,
                    ],
                ],
                $columns[] = [
                    'name'    => 'params',
                    'caption' => 'Params',
                    'width'   => 'auto',
                    'add'     => '',
                    'edit'    => [
                        'type'    => 'ValidationTextBox',
                    ],
                ],
            ],
        ];

        return $formElements;
    }

    public function CheckRule()
    {
        $presence = $this->GetPresence();
        $presence_last_gone = $this->GetArrayElem($presence, 'last_gone', '');
        $presence_first_come = $this->GetArrayElem($presence, 'first_come', '');
        $presence_at_home = (array) $this->GetArrayElem($presence, 'states.' . self::$STATE_AT_HOME, []);
        $presence_be_away = (array) $this->GetArrayElem($presence, 'states.' . self::$STATE_BE_AWAY, []);

        $targets = $this->EvaluateRule();

        $s = $this->Translate('Result of the test') . ':' . PHP_EOL;
        $s .= PHP_EOL;
        $s .= $this->Translate('At home') . ': ' . implode(', ', $presence_at_home) . PHP_EOL;
        $s .= $this->Translate('Be away') . ': ' . implode(', ', $presence_be_away) . PHP_EOL;
        $s .= $this->Translate('Last gone') . ': ' . $presence_last_gone . PHP_EOL;
        $s .= $this->Translate('First come') . ': ' . $presence_first_come . PHP_EOL;
        $s .= PHP_EOL;
        $s .= $this->Translate('Targets') . ': ' . implode(', ', $targets) . PHP_EOL;

        echo $s;
    }

    public function EvaluateRule()
    {
        $recipients = json_decode($this->ReadPropertyString('recipients'), true);
        $this->SendDebug(__FUNCTION__, 'recipients=' . print_r($recipients, true), 0);

        $presence = $this->GetPresence();
        $presence_last_gone = $this->GetArrayElem($presence, 'last_gone', '');
        $presence_first_come = $this->GetArrayElem($presence, 'first_come', '');
        $presence_at_home = (array) $this->GetArrayElem($presence, 'states.' . self::$STATE_AT_HOME, []);
        $presence_be_away = (array) $this->GetArrayElem($presence, 'states.' . self::$STATE_BE_AWAY, []);

        $presence_last_gone = strtoupper($presence_last_gone);
        $presence_first_come = strtoupper($presence_first_come);
        $presence_at_homeS = strtoupper(implode('+', $presence_at_home));
        $presence_at_home = explode('+', $presence_at_homeS);
        $presence_be_awayS = strtoupper(implode('+', $presence_be_away));
        $presence_be_away = explode('+', $presence_be_awayS);

        $this->SendDebug(__FUNCTION__, 'at_home=' . $presence_at_homeS . ', be_away=' . $presence_be_awayS . ', last_gone=' . $presence_last_gone . ', first_come=' . $presence_first_come, 0);

        $everyoneV = [];
        $all_presentV = [];
        $all_absentV = [];
        $first_presentV = [];
        $last_goneV = [];
        $first_comeV = [];
        $if_no_oneV = [];

        foreach ($recipients as $recipient) {
            $usage = $recipient['usage'];
            $target = $recipient['target'];
            $r = $this->TargetDecode($target);
            $abbreviation = strtoupper($r['abbreviation']);
            switch ($usage) {
                case self::$USAGE_EVERYONE:
                    $everyoneV[] = $target;
                    break;
                case self::$USAGE_ALL_PRESENT:
                    if (in_array($abbreviation, $presence_at_home)) {
                        $all_presentV[] = $target;
                    }
                    break;
                case self::$USAGE_ALL_ABSENT:
                    if (in_array($abbreviation, $presence_be_away)) {
                        $all_absentV[] = $target;
                    }
                    break;
                case self::$USAGE_FIRST_OF_PERSENT:
                    if ($first_presentV == []) {
                        $first_presentV[] = $target;
                    }
                    break;
                case self::$USAGE_LAST_GONE:
                    if ($last_goneV == [] && $presence_last_gone == $abbreviation) {
                        $last_goneV[] = $target;
                    }
                    break;
                case self::$USAGE_FIRST_COME:
                    if ($first_comeV == [] && $presence_first_come == $abbreviation) {
                        $first_comeV[] = $target;
                    }
                    break;
                case self::$USAGE_IF_NO_ONE:
                    $if_no_oneV[] = $target;
                    break;
                default:
                    break;
            }
        }

        $targetV = array_merge($everyoneV, $all_presentV, $all_absentV, $first_presentV, $last_goneV, $first_comeV);
        if ($targetV == []) {
            $targetV = $if_no_oneV;
        }
        $this->SendDebug(__FUNCTION__, 'targets=' . print_r($targetV, true), 0);

        return $targetV;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Check rule',
            'onClick' => 'Notification_CheckRule($id, true);'
        ];

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
}
