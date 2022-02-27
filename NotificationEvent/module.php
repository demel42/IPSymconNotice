<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/CommonStubs/common.php'; // globale Funktionen
require_once __DIR__ . '/../libs/local.php';  // lokale Funktionen

class NotificationEvent extends IPSModule
{
    use StubsCommonLib;
    use NotificationLocalLib;

    public static $TIMEUNIT_SECONDS = 0;
    public static $TIMEUNIT_MINUTES = 1;
    public static $TIMEUNIT_HOURS = 2;
    public static $TIMEUNIT_DAYS = 3;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('conditions', json_encode([]));

        $this->RegisterPropertyInteger('ruleID', 0);
        $this->RegisterPropertyString('subject', '');
        $this->RegisterPropertyString('message', '');
        $this->RegisterPropertyInteger('severity', self::$SEVERITY_UNKNOWN);
        $this->RegisterPropertyBoolean('severity_increase', false);
        $this->RegisterPropertyString('script', '');

        $this->RegisterPropertyInteger('delay_value', 0);
        $this->RegisterPropertyInteger('delay_varID', 0);
        $this->RegisterPropertyInteger('delay_timeunit', self::$TIMEUNIT_MINUTES);

        $this->RegisterPropertyInteger('pause_value', 0);
        $this->RegisterPropertyInteger('pause_varID', 0);
        $this->RegisterPropertyInteger('pause_timeunit', self::$TIMEUNIT_MINUTES);

        $this->RegisterPropertyInteger('max_repetitions', -1);

        $this->RegisterPropertyBoolean('recovery_notify', 0);
        $this->RegisterPropertyString('recovery_subject', '');
        $this->RegisterPropertyString('recovery_message', '');
        $this->RegisterPropertyInteger('recovery_severity', self::$SEVERITY_UNKNOWN);

        $this->RegisterPropertyInteger('activity_loglevel', self::$LOGLEVEL_NOTIFY);

        $this->InstallVarProfiles(false);

        $this->RegisterAttributeInteger('repetition', 0);
        $this->RegisterTimer('LoopTimer', 0, 'Notification_CheckTimer(' . $this->InstanceID . ');');
    }

    private function CheckConfiguration()
    {
        $s = '';
        $r = [];

        $ruleID = $this->ReadPropertyInteger('ruleID');
        if ($ruleID < 10000) {
            $field = $this->Translate('Notification rule');
            $r[] = $this->TranslateFormat('Field "{$field}" is not configured', ['{$field}' => $field]);
        }

        $max_repetitions = $this->ReadPropertyInteger('max_repetitions');
        if ($max_repetitions != 0) {
            $pause_value = $this->ReadPropertyInteger('pause_value');
            $pause_varID = $this->ReadPropertyInteger('pause_varID');
            if ($pause_value == 0 && $pause_varID < 10000) {
                $r[] = $this->Translate('Number of repetitions is configured, but neither a fixed value nor the variable');
            }
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
        $this->MaintainVariable('TimerStarted', $this->Translate('Start of event'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['ruleID', 'delay_varID', 'pause_varID'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid >= 10000) {
                $this->RegisterReference($oid);
            }
        }

        $conditions = json_decode($this->ReadPropertyString('conditions'), true);
        if ($conditions != false) {
            foreach ($conditions as $condition) {
                $vars = $condition['rules']['variable'];
                foreach ($vars as $var) {
                    $variableID = $var['variableID'];
                    if ($variableID >= 10000) {
                        $this->RegisterReference($variableID);
                    }
                    if ($this->GetArrayElem($var, 'type', 0) == 1 /* compare with variable */) {
                        $oid = $var['value'];
                        if ($oid >= 10000) {
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
            'caption' => 'Notification event'
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
                            'caption'   => 'Given "severity"',
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
                    'caption'   => 'Given "subject"',
                ],
                [
                    'type'      => 'ValidationTextBox',
                    'multiline' => true,
                    'name'      => 'message',
                    'caption'   => 'Given "message text"',
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
                            'name'    => 'delay_timeunit',
                            'options' => $this->GetTimeunitAsOptions(),
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
                            'bold'    => true,
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
                            'name'    => 'pause_timeunit',
                            'options' => $this->GetTimeunitAsOptions(),
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
                            'bold'    => true,
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
                            'minimum' => -1,
                            'name'    => 'max_repetitions',
                            'caption' => 'Maximum repetitions (-1=infinite)'
                        ],
                    ],
                ],
                [
                    'type'     => 'ExpansionPanel',
                    'expanded' => false,
                    'caption'  => 'Recovery',
                    'items'    => [
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'recovery_notify',
                            'caption' => 'Recovery notification'
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'recovery_subject',
                            'caption' => 'Alternate "Subject" for recovery',
                            'width'   => '500px',
                        ],
                        [
                            'type'      => 'ValidationTextBox',
                            'multiline' => true,
                            'name'      => 'recovery_message',
                            'caption'   => 'Alternate "Message text" for recovery',
                            'width'     => '500px',
                        ],
                        [
                            'type'      => 'Select',
                            'options'   => $this->SeverityAsOptions(true),
                            'name'      => 'recovery_severity',
                            'caption'   => 'Alternate "severity" for recovery',
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

        $formActions[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'      => 'Button',
                    'caption'   => 'Trigger event',
                    'onClick'   => 'Notification_TriggerEvent($id, true);'
                ],
                [
                    'type'      => 'Button',
                    'caption'   => 'Stop event',
                    'onClick'   => 'Notification_StopEvent($id);'
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
                            'type'    => 'NumberSpinner',
                            'name'    => 'repetition',
                            'caption' => 'Current repetition',
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'recovery',
                            'caption' => 'Is the recovery',
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Show notification details',
                            'onClick' => 'Notification_ShowNotificationDetails($id, $repetition, 0, $recovery);',
                        ],
                    ],
                ],
            ]
        ];

        $formActions[] = $this->GetInformationForm();

        return $formActions;
    }

    public function TriggerEvent(bool $force = false)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

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
                $repetition = 0;
                $varID = $this->ReadPropertyInteger('delay_varID');
                if ($varID >= 10000) {
                    $tval = GetValueInteger($varID);
                } else {
                    $tval = $this->ReadPropertyInteger('delay_value');
                }
                if ($tval > 0) {
                    $unit = $this->ReadPropertyInteger('delay_timeunit');
                    $sec = $this->CalcByTimeunit($unit, $tval);
                    $tvS = $tval . $this->Timeunit2Suffix($unit);
                    $msg = $conditionsS . ', started with delay of ' . $tvS;
                } else {
                    $this->Notify($repetition++, $started, false);
                    $max_repetitions = $this->ReadPropertyInteger('max_repetitions');
                    if ($this->ReadPropertyInteger('max_repetitions') == 0) {
                        $started = 0;
                        $repetition = 0;
                        $sec = 0;
                        $tvS = '';
                        $msg = $conditionsS . ', started with notification without repetition';
                    } else {
                        $varID = $this->ReadPropertyInteger('pause_varID');
                        if ($varID >= 10000) {
                            $tval = GetValueInteger($varID);
                        } else {
                            $tval = $this->ReadPropertyInteger('pause_value');
                        }
                        $unit = $this->ReadPropertyInteger('pause_timeunit');
                        $sec = $this->CalcByTimeunit($unit, $tval);
                        $tvS = $tval . $this->Timeunit2Suffix($unit);
                        $msg = $conditionsS . ', started with notification and pausing ' . $tvS;
                    }
                }
                $this->SetValue('TimerStarted', $started);
                $this->WriteAttributeInteger('repetition', $repetition);
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                    $this->LogMessage($msg, KL_NOTIFY);
                }
                if ($sec > 0) {
                    $this->SendDebug(__FUNCTION__, 'timer=' . $sec . ' sec (' . $tvS . ')', 0);
                    $this->SetTimerInterval('LoopTimer', $sec * 1000);
                } else {
                    $this->SendDebug(__FUNCTION__, 'no timer (no repetition)', 0);
                    $this->SetTimerInterval('LoopTimer', 0);
                }
            }
        } else {
            if ($started) {
                $recovery_notify = $this->ReadPropertyBoolean('recovery_notify');
                $repetition = $this->ReadAttributeInteger('repetition');
                if ($recovery_notify && $repetition > 0) {
                    $this->Notify($repetition, $started, true);
                }
                $this->SetValue('TimerStarted', 0);
                $this->WriteAttributeInteger('repetition', 0);
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                    $this->LogMessage($conditionsS . ', stopped running timer', KL_NOTIFY);
                }
                $this->SendDebug(__FUNCTION__, 'timer stopped (conditions)', 0);
                $this->SetTimerInterval('LoopTimer', 0);
            } else {
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_MESSAGE) {
                    $this->LogMessage($conditionsS . ', continuing', KL_MESSAGE);
                }
            }
        }
    }

    public function StopEvent()
    {
        $started = $this->GetValue('TimerStarted');
        $startedS = $started ? date('d.m.Y H:i:s', $started) : '';
        $this->SendDebug(__FUNCTION__, 'started=' . $startedS, 0);

        if ($started) {
            $this->SetValue('TimerStarted', 0);
            $this->WriteAttributeInteger('repetition', 0);
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                $this->LogMessage('timer stopped manual', KL_NOTIFY);
            }
            $this->SendDebug(__FUNCTION__, 'timer stopped (manual)', 0);
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
            $this->Notify($repetition++, $started, false);
            $max_repetitions = $this->ReadPropertyInteger('max_repetitions');
            if ($max_repetitions == -1 || $repetition <= $max_repetitions) {
                $varID = $this->ReadPropertyInteger('pause_varID');
                if ($varID >= 10000) {
                    $tval = GetValueInteger($varID);
                } else {
                    $tval = $this->ReadPropertyInteger('pause_value');
                }
                $unit = $this->ReadPropertyInteger('pause_timeunit');
                $sec = $this->CalcByTimeunit($unit, $tval);
                $tvS = $tval . $this->Timeunit2Suffix($unit);
                $this->WriteAttributeInteger('repetition', $repetition);
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                    $this->LogMessage($conditionsS . ', notification #' . $repetition . ' and pausing ' . $tvS, KL_NOTIFY);
                }
                $this->SendDebug(__FUNCTION__, 'timer=' . $sec . ' sec (' . $tvS . ')', 0);
                $this->SetTimerInterval('LoopTimer', $sec * 1000);
            } else {
                $this->SetValue('TimerStarted', 0);
                $this->WriteAttributeInteger('repetition', 0);
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                    $this->LogMessage($conditionsS . ', notification #' . $repetition . ' and stopped timer (max repetitions)', KL_NOTIFY);
                }
                $this->SendDebug(__FUNCTION__, 'timer stopped (max repetitions=' . $max_repetitions . ')', 0);
                $this->SetTimerInterval('LoopTimer', 0);
            }
        } else {
            $recovery_notify = $this->ReadPropertyBoolean('recovery_notify');
            if ($recovery_notify && $repetition > 0) {
                $this->Notify($repetition, $started, true);
            }
            if ($started) {
                $this->SetValue('TimerStarted', 0);
                $this->WriteAttributeInteger('repetition', 0);
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                    $this->LogMessage($conditionsS . ', stopped timer', KL_NOTIFY);
                }
                $this->SendDebug(__FUNCTION__, 'timer stopped (conditions)', 0);
                $this->SetTimerInterval('LoopTimer', 0);
            }
        }
    }

    private function GetTimeunitAsOptions()
    {
        return [
            [
                'value'   => self::$TIMEUNIT_SECONDS,
                'caption' => $this->Translate('Seconds'),
            ],
            [
                'value'   => self::$TIMEUNIT_MINUTES,
                'caption' => $this->Translate('Minutes'),
            ],
            [
                'value'   => self::$TIMEUNIT_HOURS,
                'caption' => $this->Translate('Hours'),
            ],
            [
                'value'   => self::$TIMEUNIT_DAYS,
                'caption' => $this->Translate('Days'),
            ],
        ];
    }

    private function CalcByTimeunit(int $unit, int $val)
    {
        switch ($unit) {
            case self::$TIMEUNIT_SECONDS:
                $mul = 1;
                break;
            case self::$TIMEUNIT_MINUTES:
                $mul = 60;
                break;
            case self::$TIMEUNIT_HOURS:
                $mul = 60 * 60;
                break;
            case self::$TIMEUNIT_DAYS:
                $mul = 60 * 60 * 24;
                break;
            default:
                $mul = 0;
                break;
        }
        return $val * $mul;
    }

    private function Timeunit2Suffix(int $unit)
    {
        switch ($unit) {
            case self::$TIMEUNIT_SECONDS:
                $s = 's';
                break;
            case self::$TIMEUNIT_MINUTES:
                $s = 'm';
                break;
            case self::$TIMEUNIT_HOURS:
                $s = 'h';
                break;
            case self::$TIMEUNIT_DAYS:
                $s = 'd';
                break;
            default:
                $s = '';
                break;
        }
        return $s;
    }

    private function Notify(int $repetition, int $started, bool $recovery)
    {
        $startedS = $started ? date('d.m.Y H:i:s', $started) : '';
        $this->SendDebug(__FUNCTION__, 'repetition=' . $repetition . ', started=' . $startedS . ', recovery=' . $this->bool2str($recovery), 0);

        $ruleID = $this->ReadPropertyInteger('ruleID');
        $severity_increase = $this->ReadPropertyBoolean('severity_increase');
        if ($severity_increase) {
            $severity = min(self::$SEVERITY_ALERT, $severity + $repetition);
        }
        if ($recovery) {
            $subject = $this->ReadPropertyString('recovery_subject');
            if ($subject == false) {
                $subject = $this->ReadPropertyString('subject');
            }
            $message = $this->ReadPropertyString('recovery_message');
            if ($message == false) {
                $message = $this->ReadPropertyString('message');
            }
            $severity = $this->ReadPropertyInteger('recovery_severity');
            if ($severity == self::$SEVERITY_UNKNOWN) {
                $severity = $this->ReadPropertyInteger('severity');
            }
        } else {
            $subject = $this->ReadPropertyString('subject');
            $message = $this->ReadPropertyString('message');
            $severity = $this->ReadPropertyInteger('severity');
        }
        $script = $this->ReadPropertyString('script');
        if ($script != '') {
            $params = [
                'recovery'   => $recovery,
                'repetition' => $repetition,
                'ruleID'     => $ruleID,
                'severity'   => $severity,
                'started'    => $started,
            ];
            @$r = IPS_RunScriptTextWaitEx($script, $params);
            $this->SendDebug(__FUNCTION__, 'script("...", ' . print_r($params, true) . ' => ' . $r, 0);
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

        if ($ruleID < 10000) {
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

        $params = [
            'recovery'   => $recovery,
            'repetition' => $repetition,
            'started'    => $started,
            'eventID'    => $this->InstanceID,
        ];
        $this->SendDebug(__FUNCTION__, 'trigger rule "' . IPS_GetName($ruleID) . '" (#' . $repetition . ')', 0);
        $r = Notification_TriggerRule($ruleID, $message, $subject, $severity, $params);
        return $r;
    }

    public function ShowNotificationDetails(int $repetition, int $started, bool $recovery)
    {
        if ($started == 0) {
            $started = $this->GetValue('TimerStarted');
        }

        $ruleID = $this->ReadPropertyInteger('ruleID');
        $severity = $this->ReadPropertyInteger('severity');
        if ($recovery) {
            $subject = $this->ReadPropertyString('recovery_subject');
            $message = $this->ReadPropertyString('recovery_message');
        } else {
            $subject = $this->ReadPropertyString('subject');
            $message = $this->ReadPropertyString('message');
        }
        $script = $this->ReadPropertyString('script');
        if ($script != '') {
            $params = [
                'recovery'   => $recovery,
                'repetition' => $repetition,
                'ruleID'     => $ruleID,
                'severity'   => $severity,
                'started'    => $started,
            ];
            $this->SendDebug(__FUNCTION__, 'script=' . $script, 0);
            $this->SendDebug(__FUNCTION__, 'params=' . print_r($params, true), 0);
            $r = IPS_RunScriptTextWaitEx($script, $params);
            if ($r != false) {
                @$j = json_decode($r, true);
                if ($j != false) {
                    $this->SendDebug(__FUNCTION__, 'result=' . print_r($j, true), 0);
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
                    $this->SendDebug(__FUNCTION__, 'message=' . $r, 0);
                    $message = $r;
                }
            } else {
                $this->SendDebug(__FUNCTION__, 'no result', 0);
            }
        }
        $s = $this->Translate('Result of notification details') . PHP_EOL;
        $s .= PHP_EOL;
        $s .= $this->Translate('Severity') . ': ' . $this->SeverityEncode($severity, true) . PHP_EOL;
        $s .= $this->Translate('Notification rule') . ': ' . IPS_GetName($ruleID) . '(' . $ruleID . ')' . PHP_EOL;
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

    public function Log(string $message, string $severity, array $params)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $notificationBase = $this->GetNotificationBase();
        if ($notificationBase >= 10000) {
            return Notification_Log($notificationBase, $message, $severity, $params);
        }
        return false;
    }
}
