<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/CommonStubs/common.php'; // globale Funktionen
require_once __DIR__ . '/../libs/local.php';  // lokale Funktionen

class NotificationTimer extends IPSModule
{
    use StubsCommonLib;
    use NotificationLocalLib;

    public static $TIMEMODE_SECONDS = 0;
    public static $TIMEMODE_MINUTES = 1;
    public static $TIMEMODE_HOURS = 2;
    public static $TIMEMODE_DAYS = 3;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('conditions', json_encode([]));

        $this->RegisterPropertyInteger('ruleID', 0);
        $this->RegisterPropertyInteger('severity', 0);
        $this->RegisterPropertyBoolean('severity_increase', false);
        $this->RegisterPropertyString('message', '');
        $this->RegisterPropertyString('subject', '');
        $this->RegisterPropertyString('script', '');

        $this->RegisterPropertyInteger('delay_value', 0);
        $this->RegisterPropertyInteger('delay_varID', 0);
        $this->RegisterPropertyInteger('delay_timemode', self::$TIMEMODE_MINUTES);

        $this->RegisterPropertyInteger('pause_value', 0);
        $this->RegisterPropertyInteger('pause_varID', 0);
        $this->RegisterPropertyInteger('pause_timemode', self::$TIMEMODE_MINUTES);

        $this->RegisterPropertyInteger('max_repetitions', 0);

        $this->InstallVarProfiles(false);

        $this->RegisterAttributeInteger('repetition', 0);
        $this->RegisterTimer('LoopTimer', 0, 'Notification_CheckTimer(' . $this->InstanceID . ');');
    }

    private function CheckConfiguration()
    {
        $s = '';
        $r = [];

        $ruleID = $this->ReadPropertyInteger('ruleID');
        if ($ruleID == 0) {
            $field = $this->Translate('Notification rule');
            $r[] = $this->TranslateFormat('Field "{$field}" is not configured', ['{$field}' => $field]);
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
        $this->MaintainVariable('TimerStarted', $this->Translate('Start of timer'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['ruleID', 'delay_varID', 'pause_varID'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid > 0) {
                $this->RegisterReference($oid);
            }
        }

        $conditions = json_decode($this->ReadPropertyString('conditions'), true);
        if ($conditions != false) {
            foreach ($conditions as $condition) {
                $vars = $condition['rules']['variable'];
                foreach ($vars as $var) {
                    $variableID = $var['variableID'];
                    if ($variableID > 0) {
                        $this->RegisterReference($variableID);
                    }
                    if ($var['type'] == 1) {
                        $oid = $var['value'];
                        if ($oid > 0) {
                            $this->RegisterReference($oid);
                        }
                    }
                }
            }
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('LoopTimer', 0);
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
            'caption' => 'Notification timer'
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
            'type'     => 'ExpansionPanel',
            'expanded' => false,
            'items'    => [
                [
                    'name'    => 'conditions',
                    'type'    => 'SelectCondition',
                    'multi'   => true,
                ],
            ],
            'caption' => 'Conditions',
        ];

        $formElements[] = [
            'type'     => 'ExpansionPanel',
            'expanded' => false,
            'items'    => [
                [
                    'type'     => 'SelectModule',
                    'moduleID' => '{2335FF1E-9628-E363-AAEC-11DE75788A13}',
                    'name'     => 'ruleID',
                    'caption'  => 'Notification rule',
                ],
                [
                    'type'    => 'RowLayout',
                    'items'   => [
                        [
                            'type'      => 'Select',
                            'options'   => $this->SeverityAsOptions(true),
                            'name'      => 'severity',
                            'caption'   => 'Initial severity',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'severity_increase',
                            'caption' => 'Increase severity each repetition'
                        ],
                    ],
                ],
                [
                    'type'      => 'ValidationTextBox',
                    'name'      => 'subject',
                    'caption'   => 'Static "subject"',
                ],
                [
                    'type'      => 'ValidationTextBox',
                    'name'      => 'message',
                    'caption'   => 'Static "message text"',
                    'multiline' => true,
                ],
                [
                    'type'      => 'Label',
                    'caption'   => 'Script for generating individual notification texts etc. For information on transfer and return value see documentation',
                ],
                [
                    'type'      => 'ScriptEditor',
                    'rowCount'  => 10,
                    'name'      => 'script',
                ],
            ],
            'caption' => 'Notification details',
        ];

        $formElements[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'     => 'ExpansionPanel',
                    'expanded' => false,
                    'caption'  => 'Initial delay',
                    'items'    => [
                        [
                            'type'    => 'Select',
                            'name'    => 'delay_timemode',
                            'options' => $this->GetTimemodeAsOptions(),
                            'caption' => 'Time unit',
                        ],
                        [
                            'type'    => 'NumberSpinner',
                            'minimum' => 0,
                            'name'    => 'delay_value',
                            'caption' => 'Fix value'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'or'
                        ],
                        [
                            'type'               => 'SelectVariable',
                            'validVariableTypes' => [VARIABLETYPE_INTEGER],
                            'name'               => 'delay_varID',
                            'caption'            => 'Variable',
                            'width'              => '500px',
                        ],
                    ],
                ],
                [
                    'type'     => 'ExpansionPanel',
                    'expanded' => false,
                    'caption'  => 'Repetitions',
                    'items'    => [
                        [
                            'type'    => 'Select',
                            'name'    => 'pause_timemode',
                            'options' => $this->GetTimemodeAsOptions(),
                            'caption' => 'Time unit',
                        ],
                        [
                            'type'    => 'NumberSpinner',
                            'minimum' => 0,
                            'name'    => 'pause_value',
                            'caption' => 'Fix value'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'or'
                        ],
                        [
                            'type'               => 'SelectVariable',
                            'validVariableTypes' => [VARIABLETYPE_INTEGER],
                            'name'               => 'pause_varID',
                            'caption'            => 'Variable',
                            'width'              => '500px',
                        ],
                        [
                            'type'    => 'NumberSpinner',
                            'minimum' => 0,
                            'name'    => 'max_repetitions',
                            'caption' => 'Maximum repetitions'
                        ],
                    ],
                ],
            ],
        ];

        return $formElements;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'      => 'Button',
                    'caption'   => 'Start timer',
                    'onClick'   => 'Notification_StartTimer($id, true);'
                ],
                [
                    'type'      => 'Button',
                    'caption'   => 'Stop timer',
                    'onClick'   => 'Notification_StopTimer($id);'
                ],
            ],
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
                [
                    'type'    => 'RowLayout',
                    'items'   => [
                        [
                            'type'     => 'NumberSpinner',
                            'name'     => 'repetition',
                            'caption'  => 'Repetition',
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Show notification details',
                            'onClick' => 'Notification_ShowNotificationDetails($id, $repetition, time());',
                        ],
                    ],
                ],
            ]
        ];

        $formActions[] = $this->GetInformationForm();

        return $formActions;
    }

    public function StartTimer(bool $force = false)
    {
        $conditions = $this->ReadPropertyString('conditions');
        if ($conditions != '' && $conditions != []) {
            $passed = IPS_IsConditionPassing($conditions);
            $conditionsS = 'conditions ' . ($passed ? 'passed' : 'blocked');
        } else {
            $passed = true;
            $conditionsS = 'no conditions';
        }

        $started = $this->GetValue('TimerStarted');
        $startedS = $started ? date('d.m.Y H:i:s', $started) : '';

        $this->SendDebug(__FUNCTION__, $conditionsS . ', started=' . $startedS, 0);
        if ($passed) {
            if ($started == 0 || $force) {
                $started = time();
                $this->SetValue('TimerStarted', $started);
                $repetition = 0;

                $delay_value = $this->ReadPropertyInteger('delay_value');
                $delay_varID = $this->ReadPropertyInteger('delay_varID');
                if ($delay_varID != 0) {
                    $delay_value = GetValueInteger($delay_varID);
                }
                $delay_timemode = $this->ReadPropertyInteger('delay_timemode');
                $msec = $this->CalcByTimemode($delay_timemode, $delay_value) * 1000;
                if ($msec == 0) {
                    $this->Notify($repetition++, $started);
                    $pause_value = $this->ReadPropertyInteger('pause_value');
                    $pause_varID = $this->ReadPropertyInteger('pause_varID');
                    $pause_timemode = $this->ReadPropertyInteger('pause_timemode');
                    if ($pause_varID != 0) {
                        $pause_value = GetValueInteger($pause_varID);
                    }
                    $msec = $this->CalcByTimemode($pause_timemode, $pause_value) * 1000;
                }
                $this->WriteAttributeInteger('repetition', $repetition);
                $this->SendDebug(__FUNCTION__, 'timer=' . $msec . ' msec', 0);
                $this->SetTimerInterval('LoopTimer', $msec);
            }
        } else {
            if ($started) {
                $this->SendDebug(__FUNCTION__, 'trigger stopped (conditions)', 0);
                $this->SetValue('TimerStarted', 0);
                $this->WriteAttributeInteger('repetition', 0);
                $this->SetTimerInterval('LoopTimer', 0);
            }
        }
    }

    public function StopTimer()
    {
        $started = $this->GetValue('TimerStarted');
        $startedS = $started ? date('d.m.Y H:i:s', $started) : '';

        $this->SendDebug(__FUNCTION__, 'started=' . $startedS, 0);
        if ($started) {
            $this->SendDebug(__FUNCTION__, 'timer stopped (manual)', 0);
            $this->SetValue('TimerStarted', 0);
            $this->WriteAttributeInteger('repetition', 0);
            $this->SetTimerInterval('LoopTimer', 0);
        }
    }

    public function CheckTimer()
    {
        $conditions = $this->ReadPropertyString('conditions');
        if ($conditions != '' && $conditions != []) {
            $passed = IPS_IsConditionPassing($conditions);
            $conditionsS = 'conditions ' . ($passed ? 'passed' : 'blocked');
        } else {
            $passed = true;
            $conditionsS = 'no conditions';
        }

        $started = $this->GetValue('TimerStarted');
        $startedS = $started ? date('d.m.Y H:i:s', $started) : '';

        $repetition = $this->ReadAttributeInteger('repetition');

        $this->SendDebug(__FUNCTION__, $conditionsS . ', started=' . $startedS . ', repetition=' . $repetition, 0);
        if ($passed) {
            $this->Notify($repetition++, $started);
            $max_repetitions = $this->ReadPropertyInteger('max_repetitions');
            if ($max_repetitions == 0 || $repetition < $max_repetitions) {
                $pause_value = $this->ReadPropertyInteger('pause_value');
                $pause_varID = $this->ReadPropertyInteger('pause_varID');
                $pause_timemode = $this->ReadPropertyInteger('pause_timemode');
                if ($pause_varID != 0) {
                    $pause_value = GetValueInteger($pause_varID);
                }
                $msec = $this->CalcByTimemode($pause_timemode, $pause_value) * 1000;
                $this->WriteAttributeInteger('repetition', $repetition);
                $this->SendDebug(__FUNCTION__, 'timer=' . $msec . ' msec', 0);
                $this->SetTimerInterval('LoopTimer', $msec);
            } else {
                $this->SendDebug(__FUNCTION__, 'timer stopped (max_repetitions)', 0);
                $this->SetValue('TimerStarted', 0);
                $this->WriteAttributeInteger('repetition', 0);
                $this->SetTimerInterval('LoopTimer', 0);
            }
        } else {
            if ($started) {
                $this->SendDebug(__FUNCTION__, 'timer stopped (conditions)', 0);
                $this->SetValue('TimerStarted', 0);
                $this->WriteAttributeInteger('repetition', 0);
                $this->SetTimerInterval('LoopTimer', 0);
            }
        }
    }

    private function GetTimemodeAsOptions()
    {
        return [
            [
                'value'   => self::$TIMEMODE_SECONDS,
                'caption' => $this->Translate('Seconds'),
            ],
            [
                'value'   => self::$TIMEMODE_MINUTES,
                'caption' => $this->Translate('Minutes'),
            ],
            [
                'value'   => self::$TIMEMODE_HOURS,
                'caption' => $this->Translate('Hours'),
            ],
            [
                'value'   => self::$TIMEMODE_DAYS,
                'caption' => $this->Translate('Days'),
            ],
        ];
    }

    private function CalcByTimemode(int $mode, int $val)
    {
        switch ($mode) {
            case self::$TIMEMODE_SECONDS:
                $mul = 1;
                break;
            case self::$TIMEMODE_MINUTES:
                $mul = 60;
                break;
            case self::$TIMEMODE_HOURS:
                $mul = 60 * 60;
                break;
            case self::$TIMEMODE_DAYS:
                $mul = 60 * 60 * 24;
                break;
            default:
                $mul = 0;
                break;
        }
        return $val * $mul;
    }

    private function Timemode2Suffix(int $mode)
    {
        switch ($mode) {
            case self::$TIMEMODE_SECONDS:
                $s = 's';
                break;
            case self::$TIMEMODE_MINUTES:
                $s = 'm';
                break;
            case self::$TIMEMODE_HOURS:
                $s = 'h';
                break;
            case self::$TIMEMODE_DAYS:
                $s = 'd';
                break;
            default:
                $mul = 0;
                break;
        }
        return $val * $mul;
    }

    private function Notify(int $repetition, int $started)
    {
        $ruleID = $this->ReadPropertyInteger('ruleID');
        $severity = $this->ReadPropertyInteger('severity');
        $severity_increase = $this->ReadPropertyBoolean('severity_increase');
        if ($severity_increase) {
            $severity = min(self::$SEVERITY_ALERT, $severity + $repetition);
        }
        $subject = $this->ReadPropertyString('subject');
        $message = $this->ReadPropertyString('message');
        $script = $this->ReadPropertyString('script');
        if ($script != '') {
            $params = [
                'repetition' => $repetition,
                'started'    => $started,
                'severity'   => $severity,
                'ruleID'     => $ruleID,
            ];
            @$r = IPS_RunScriptTextWaitEx($script, $params);
            $this->SendDebug(__FUNCTION__, 'script(..., ' . print_r($params, true) . ' => ' . $r, 0);
            if ($r != false) {
                @$j = json_decode($r, true);
                if ($j != false) {
                    if (isset($j['message'])) {
                        $message = $j['message'];
                    }
                    if (isset($j['subject'])) {
                        $subject = $j['subject'];
                    }
                    if (isset($j['severity'])) {
                        $severity = $j['severity'];
                    }
                    if (isset($j['ruleID'])) {
                        $ruleID = $j['ruleID'];
                    }
                } else {
                    $message = $r;
                }
            }
        }

        if ($ruleID == 0) {
            $this->SendDebug(__FUNCTION__, 'no notification rule', 0);
            return false;
        }
        @$inst = IPS_GetInstance($ruleID);
        if ($inst == false) {
            $this->SendDebug(__FUNCTION__, 'instance ' . $ruleID . ' is invalid', 0);
            return false;
        }
        $status = $inst['InstanceStatus'];
        if ($status != IS_ACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance ' . $ruleID . ' is not active (status=' . $status . ')', 0);
            return false;
        }
        $moduleID = $inst['ModuleInfo']['ModuleID'];
        if ($moduleID != '{2335FF1E-9628-E363-AAEC-11DE75788A13}') {
            $this->SendDebug(__FUNCTION__, 'instance ' . $ruleID . ' has wrong GUID ' . $moduleID, 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'trigger rule "' . IPS_GetName($ruleID) . '" (#' . $repetition . ')', 0);
        $r = Notification_TriggerRule($ruleID, $message, $subject, $severity, []);
        return $r;
    }

    public function ShowNotificationDetails(int $repetition, int $started)
    {
        $ruleID = $this->ReadPropertyInteger('ruleID');
        $severity = $this->ReadPropertyInteger('severity');
        $subject = $this->ReadPropertyString('subject');
        $message = $this->ReadPropertyString('message');
        $script = $this->ReadPropertyString('script');
        if ($script != '') {
            $params = [
                'repetition'    => $repetition,
                'started'       => $started,
                'severity'      => $severity,
            ];
            $r = IPS_RunScriptTextWaitEx($script, $params);
            $this->SendDebug(__FUNCTION__, 'script(..., ' . print_r($params, true) . ' => ' . $r, 0);
            if ($r != false) {
                @$j = json_decode($r, true);
                if ($j != false) {
                    if (isset($j['message'])) {
                        $message = $j['message'];
                    }
                    if (isset($j['subject'])) {
                        $subject = $j['subject'];
                    }
                    if (isset($j['severity'])) {
                        $severity = $j['severity'];
                    }
                    if (isset($j['ruleID'])) {
                        $ruleID = $j['ruleID'];
                    }
                } else {
                    $message = $r;
                }
            }
        }
        $s = $this->Translate('Result of notification details') . PHP_EOL;
        $s .= PHP_EOL;
        $s .= $this->Translate('Severity') . '=' . $severity . PHP_EOL;
        $s .= $this->Translate('Notification rule') . '=' . IPS_GetName($ruleID) . '(' . $ruleID . ')' . PHP_EOL;
        $s .= PHP_EOL;
        $s .= '- ' . $this->Translate('Subject') . ' -' . PHP_EOL;
        if ($subject != '') {
            $s .= $subject . PHP_EOL;
        }
        $s .= PHP_EOL;
        $s .= '- ' . $this->Translate('Message text') . ' -' . PHP_EOL;
        if ($message != '') {
            $s .= $message . PHP_EOL;
        }
        echo $s;
    }
}
