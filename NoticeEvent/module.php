<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class NoticeEvent extends IPSModule
{
    use Notice\StubsCommonLib;
    use NoticeLocalLib;

    public static $TIMEUNIT_SECONDS = 0;
    public static $TIMEUNIT_MINUTES = 1;
    public static $TIMEUNIT_HOURS = 2;
    public static $TIMEUNIT_DAYS = 3;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

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

        $this->RegisterPropertyInteger('max_repetitions', 0);

        $this->RegisterPropertyBoolean('recovery_notify', 0);
        $this->RegisterPropertyString('recovery_subject', '');
        $this->RegisterPropertyString('recovery_message', '');
        $this->RegisterPropertyInteger('recovery_severity', self::$SEVERITY_UNKNOWN);

        $this->RegisterPropertyInteger('activity_loglevel', self::$LOGLEVEL_NOTIFY);

        $this->RegisterAttributeInteger('repetition', 0);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('LoopTimer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "CheckTimer", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $ruleID = $this->ReadPropertyInteger('ruleID');
        if (IPS_InstanceExists($ruleID) == false) {
            $field = $this->Translate('Notice rule');
            $r[] = $this->TranslateFormat('Field "{$field}" is not configured', ['{$field}' => $field]);
        }

        $max_repetitions = $this->ReadPropertyInteger('max_repetitions');
        if ($max_repetitions != 0) {
            $pause_value = $this->ReadPropertyInteger('pause_value');
            $pause_varID = $this->ReadPropertyInteger('pause_varID');
            if ($pause_value == 0 && IPS_VariableExists($pause_varID) == false) {
                $r[] = $this->Translate('Number of repetitions is configured, but neither a fixed value nor the variable');
            }
        }

        return $r;
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        if ($this->version2num($oldInfo) < $this->version2num('1.13')) {
            @$varID = $this->GetIDForIdent('TimerStarted');
            if (@$varID != false) {
                $r[] = $this->Translate('Change ident of variable \'TimerStarted\' to \'EventStarted\'');
            }
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        if ($this->version2num($oldInfo) < $this->version2num('1.13')) {
            @$varID = $this->GetIDForIdent('TimerStarted');
            if (@$varID != false) {
                IPS_SetIdent($varID, 'EventStarted');
            }
        }

        return '';
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = ['ruleID', 'delay_varID', 'pause_varID'];
        $this->MaintainReferences($propertyNames);

        $propertyNames = ['script'];
        foreach ($propertyNames as $name) {
            $text = $this->ReadPropertyString($name);
            $this->MaintainReferences4Script($text);
        }

        $conditions = json_decode($this->ReadPropertyString('conditions'), true);
        if ($conditions != false) {
            foreach ($conditions as $condition) {
                $vars = $condition['rules']['variable'];
                foreach ($vars as $var) {
                    $variableID = $var['variableID'];
                    if (IPS_VariableExists($variableID)) {
                        $this->RegisterReference($variableID);
                    }
                    if ($this->GetArrayElem($var, 'type', 0) == 1 /* compare with variable */) {
                        $oid = $var['value'];
                        if (IPS_VariableExists($oid)) {
                            $this->RegisterReference($oid);
                        }
                    }
                }
            }
        }

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('LoopTimer', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('LoopTimer', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('LoopTimer', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 0;
        $this->MaintainVariable('EventStarted', $this->Translate('Start of event'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('LoopTimer', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
        }
    }

    public function MessageSink($tstamp, $senderID, $message, $data)
    {
        parent::MessageSink($tstamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Notice event');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
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
                    'caption'  => 'Notice rule',
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
                    'width'     => '500px',
                    'caption'   => 'Given "subject"',
                ],
                [
                    'type'      => 'ValidationTextBox',
                    'multiline' => true,
                    'width'     => '1000px',
                    'name'      => 'message',
                    'caption'   => 'Given "message text"',
                ],
                [
                    'type'      => 'Label',
                    'caption'   => 'Script for generating individual notice texts etc. For information on transfer and return value see documentation',
                ],
                [
                    'type'      => 'ScriptEditor',
                    'rowCount'  => 10,
                    'name'      => 'script',
                ],
            ],
            'caption' => 'Notice details',
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
                            'caption' => 'Recovery notice'
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'recovery_subject',
                            'width'   => '500px',
                            'caption' => 'Alternate "Subject" for recovery',
                        ],
                        [
                            'type'      => 'ValidationTextBox',
                            'multiline' => true,
                            'width'     => '1000px',
                            'name'      => 'recovery_message',
                            'caption'   => 'Alternate "Message text" for recovery',
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

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'      => 'Button',
                    'caption'   => 'Trigger event',
                    'onClick'   => $this->GetModulePrefix() . '_TriggerEvent($id, true);'
                ],
                [
                    'type'      => 'Button',
                    'caption'   => 'Stop event',
                    'onClick'   => $this->GetModulePrefix() . '_StopEvent($id);'
                ],
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
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
                            'caption' => 'Show notice details',
                            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ShowNoticeDetails", json_encode(["repetition" => $repetition, "recovery" => $recovery]));',
                        ],
                    ],
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();
        $formActions[] = $this->GetModuleActivityFormAction();

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

        switch ($ident) {
            case 'CheckTimer':
                $this->CheckTimer();
                break;
            case 'ShowNoticeDetails':
                $this->ShowNoticeDetails($value);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }

    public function TriggerEvent(bool $force = false)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->PushCallChain(__FUNCTION__);

        $conditions = $this->ReadPropertyString('conditions');
        if (json_decode($conditions, true)) {
            $passed = IPS_IsConditionPassing($conditions);
            $conditionsS = 'conditions ' . ($passed ? 'passed' : 'blocked');
        } else {
            $passed = true;
            $conditionsS = 'no conditions';
        }

        $started = $this->GetValue('EventStarted');
        $startedS = $started ? date('d.m.Y H:i:s', $started) : '-';
        $this->SendDebug(__FUNCTION__, $conditionsS . ', started=' . $startedS, 0);

        $chainS = $this->PrintCallChain(false);

        if ($passed) {
            if ($started == 0 || $force) {
                $started = time();
                $repetition = 0;
                $varID = $this->ReadPropertyInteger('delay_varID');
                if (IPS_VariableExists($varID)) {
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
                        $repetition = 0;
                        $sec = 0;
                        $tvS = '';
                        $msg = $conditionsS . ', started with notice without repetition';
                    } else {
                        $varID = $this->ReadPropertyInteger('pause_varID');
                        if (IPS_VariableExists($varID)) {
                            $tval = GetValueInteger($varID);
                        } else {
                            $tval = $this->ReadPropertyInteger('pause_value');
                        }
                        $unit = $this->ReadPropertyInteger('pause_timeunit');
                        $sec = $this->CalcByTimeunit($unit, $tval);
                        $tvS = $tval . $this->Timeunit2Suffix($unit);
                        $msg = $conditionsS . ', started with notice and pausing ' . $tvS;
                    }
                }
                $this->SetValue('EventStarted', $started);
                $this->WriteAttributeInteger('repetition', $repetition);
                $this->SendDebug(__FUNCTION__, 'set EventStarted=' . $started . ' (' . ($started ? date('d.m.Y H:i:s', $started) : '-') . '), repetition=' . $repetition, 0);
                $msg .= ' (' . $chainS . ')';
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                    $this->LogMessage($msg, KL_NOTIFY);
                }
                $this->AddModuleActivity($msg);
                if ($sec > 0) {
                    $this->SendDebug(__FUNCTION__, 'timer=' . $sec . ' sec (' . $tvS . ')', 0);
                    $this->MaintainTimer('LoopTimer', $sec * 1000);
                } else {
                    $this->SendDebug(__FUNCTION__, 'no timer (no repetition)', 0);
                    $this->MaintainTimer('LoopTimer', 0);
                }
            }
        } else {
            if ($started) {
                $recovery_notify = $this->ReadPropertyBoolean('recovery_notify');
                if ($recovery_notify) {
                    $repetition = $this->ReadAttributeInteger('repetition');
                    $this->Notify($repetition, $started, true);
                }
                $this->SetValue('EventStarted', 0);
                $this->WriteAttributeInteger('repetition', 0);
                $this->SendDebug(__FUNCTION__, 'clear EventStarted, clear repetition', 0);
                $msg = $conditionsS . ', stopped running timer ' . ($recovery_notify ? ' with notice ' : '') . '(' . $chainS . ')';
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                    $this->LogMessage($msg, KL_NOTIFY);
                }
                $this->AddModuleActivity($msg);
                $this->SendDebug(__FUNCTION__, 'timer stopped (conditions)', 0);
            } else {
                $msg = $conditionsS . ', no activity (' . $chainS . ')';
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_MESSAGE) {
                    $this->LogMessage($msg, KL_MESSAGE);
                }
                $this->AddModuleActivity($msg);
            }
            $this->MaintainTimer('LoopTimer', 0);
        }

        $this->PopCallChain(__FUNCTION__);
    }

    public function StopEvent()
    {
        $this->PushCallChain(__FUNCTION__);

        $started = $this->GetValue('EventStarted');
        $startedS = $started ? date('d.m.Y H:i:s', $started) : '';
        $this->SendDebug(__FUNCTION__, 'started=' . $startedS, 0);

        $chainS = $this->PrintCallChain(false);

        if ($started) {
            $this->SetValue('EventStarted', 0);
            $this->WriteAttributeInteger('repetition', 0);
            $this->SendDebug(__FUNCTION__, 'clear EventStarted, clear repetition', 0);
            $msg = 'timer stopped manual (' . $chainS . ')';
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                $this->LogMessage($msg, KL_NOTIFY);
            }
            $this->AddModuleActivity($msg);
            $this->SendDebug(__FUNCTION__, 'timer stopped (manual)', 0);
        }
        $this->MaintainTimer('LoopTimer', 0);

        $this->PopCallChain(__FUNCTION__);
    }

    private function CheckTimer()
    {
        $this->PushCallChain(__FUNCTION__);

        $conditions = $this->ReadPropertyString('conditions');
        if (json_decode($conditions, true)) {
            $passed = IPS_IsConditionPassing($conditions);
            $conditionsS = 'conditions ' . ($passed ? 'passed' : 'blocked');
        } else {
            $passed = true;
            $conditionsS = 'no conditions';
        }

        $started = $this->GetValue('EventStarted');
        $startedS = $started ? date('d.m.Y H:i:s', $started) : '';
        $repetition = $this->ReadAttributeInteger('repetition');
        $this->SendDebug(__FUNCTION__, $conditionsS . ', started=' . $startedS . ', repetition=' . $repetition, 0);

        $chainS = $this->PrintCallChain(false);

        if ($passed) {
            $this->Notify($repetition++, $started, false);
            $max_repetitions = $this->ReadPropertyInteger('max_repetitions');
            if ($max_repetitions == -1 || ($max_repetitions > 0 && $repetition <= $max_repetitions)) {
                $varID = $this->ReadPropertyInteger('pause_varID');
                if (IPS_VariableExists($varID)) {
                    $tval = GetValueInteger($varID);
                } else {
                    $tval = $this->ReadPropertyInteger('pause_value');
                }
                $unit = $this->ReadPropertyInteger('pause_timeunit');
                $sec = $this->CalcByTimeunit($unit, $tval);
                $tvS = $tval . $this->Timeunit2Suffix($unit);
                $this->WriteAttributeInteger('repetition', $repetition);
                $this->SendDebug(__FUNCTION__, 'set repetition=' . $repetition, 0);
                $msg = $conditionsS . ', notice #' . $repetition . ' and pausing ' . $tvS . ' (' . $chainS . ')';
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                    $this->LogMessage($msg, KL_NOTIFY);
                }
                $this->AddModuleActivity($msg);
                $this->SendDebug(__FUNCTION__, 'timer=' . $sec . ' sec (' . $tvS . ')', 0);
                $this->MaintainTimer('LoopTimer', $sec * 1000);
            } else {
                $this->SetValue('EventStarted', 0);
                $this->WriteAttributeInteger('repetition', 0);
                $this->SendDebug(__FUNCTION__, 'clear EventStarted, clear repetition', 0);
                $msg = $conditionsS . ', notice #' . $repetition . ' and stopped timer (max repetitions) (' . $chainS . ')';
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                    $this->LogMessage($msg, KL_NOTIFY);
                }
                $this->AddModuleActivity($msg);
                $this->SendDebug(__FUNCTION__, 'timer stopped (max repetitions=' . $max_repetitions . ')', 0);
                $this->MaintainTimer('LoopTimer', 0);
            }
        } else {
            $recovery_notify = $this->ReadPropertyBoolean('recovery_notify');
            if ($recovery_notify && $repetition > 0) {
                $this->Notify($repetition, $started, true);
            }
            if ($started) {
                $this->SetValue('EventStarted', 0);
                $this->WriteAttributeInteger('repetition', 0);
                $this->SendDebug(__FUNCTION__, 'clear EventStarted, clear repetition', 0);
                $msg = $conditionsS . ', stopped timer (' . $chainS . ')';
                if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                    $this->LogMessage($msg, KL_NOTIFY);
                }
                $this->AddModuleActivity($msg);
                $this->SendDebug(__FUNCTION__, 'timer stopped (conditions)', 0);
                $this->MaintainTimer('LoopTimer', 0);
            }
        }

        $this->PopCallChain(__FUNCTION__);
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
        $log_additionally = null;
        $script = $this->ReadPropertyString('script');
        if ($script != '') {
            $params = [
                'recovery'   => $recovery,
                'repetition' => $repetition,
                'ruleID'     => $ruleID,
                'severity'   => $severity,
                'started'    => $started,
                'instanceID' => $this->InstanceID,
            ];
            foreach (['VARIABLE', 'VALUE', 'OLDVALUE'] as $elem) {
                if (isset($_IPS[$elem])) {
                    $params[$elem] = $_IPS[$elem];
                }
            }
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
                    if (isset($j['log_additionally'])) {
                        $log_additionally = $j['log_additionally'];
                    }
                } else {
                    $message = $r;
                }
            }
        }

        if (IPS_InstanceExists($ruleID) == false) {
            $this->SendDebug(__FUNCTION__, 'no notice rule', 0);
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
        if (is_null($log_additionally) == false) {
            $params['log_additionally'] = $log_additionally;
        }
        $this->SendDebug(__FUNCTION__, 'trigger rule "' . IPS_GetName($ruleID) . '" (#' . $repetition . ')', 0);
        $r = Notice_TriggerRule($ruleID, $message, $subject, $severity, $params);
        return $r;
    }

    private function ShowNoticeDetails($params)
    {
        $jparams = json_decode($params, true);
        $repetition = isset($jparams['repetition']) ? $jparams['repetition'] : 0;
        $started = isset($jparams['started']) ? $jparams['started'] : 0;
        if ($started == 0) {
            $started = $this->GetValue('EventStarted');
        }
        $recovery = isset($jparams['recovery']) ? $jparams['recovery'] : false;

        $ruleID = $this->ReadPropertyInteger('ruleID');
        $severity = $this->ReadPropertyInteger('severity');
        if ($recovery) {
            $subject = $this->ReadPropertyString('recovery_subject');
            $message = $this->ReadPropertyString('recovery_message');
        } else {
            $subject = $this->ReadPropertyString('subject');
            $message = $this->ReadPropertyString('message');
        }
        $log_additionally = null;
        $script = $this->ReadPropertyString('script');
        if ($script != '') {
            $params = [
                'recovery'   => $recovery,
                'repetition' => $repetition,
                'ruleID'     => $ruleID,
                'severity'   => $severity,
                'started'    => $started,
                'instanceID' => $this->InstanceID,
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
                    if (isset($j['log_additionally'])) {
                        $log_additionally = $j['log_additionally'];
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, 'message=' . $r, 0);
                    $message = $r;
                }
            } else {
                $this->SendDebug(__FUNCTION__, 'no result', 0);
            }
        }
        $s = $this->Translate('Result of notice details') . PHP_EOL;
        $s .= PHP_EOL;
        $s .= $this->Translate('Severity') . ': ' . $this->SeverityEncode($severity, true) . PHP_EOL;
        $s .= $this->Translate('Notice rule') . ': ' . IPS_GetName($ruleID) . '(' . $ruleID . ')' . PHP_EOL;
        if (is_null($log_additionally) == false) {
            $s .= $this->Translate('Log additionally') . ': ' . $this->Translate($log_additionally ? 'yes' : 'no') . PHP_EOL;
        }
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
        $this->PopupMessage($s);
    }

    public function Log(string $message, string $severity, array $params)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $noticeBase = $this->GetNoticeBase();
        if (IPS_InstanceExists($noticeBase)) {
            return Notice_Log($noticeBase, $message, $severity, $params);
        }
        return false;
    }
}
