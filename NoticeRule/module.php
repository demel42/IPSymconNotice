<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class NoticeRule extends IPSModule
{
    use Notice\StubsCommonLib;
    use NoticeLocalLib;

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

        $this->RegisterPropertyInteger('noticeBase', 0);

        $this->RegisterPropertyString('default_subject', '');
        $this->RegisterPropertyString('default_message', '');
        $this->RegisterPropertyInteger('default_severity', self::$SEVERITY_UNKNOWN);

        $this->RegisterPropertyString('default_webfront_sound_info', '');
        $this->RegisterPropertyString('default_webfront_sound_notice', '');
        $this->RegisterPropertyString('default_webfront_sound_warn', '');
        $this->RegisterPropertyString('default_webfront_sound_alert', '');

        $this->RegisterPropertyString('default_script_signal_info', '');
        $this->RegisterPropertyString('default_script_signal_notice', '');
        $this->RegisterPropertyString('default_script_signal_warn', '');
        $this->RegisterPropertyString('default_script_signal_alert', '');

        $this->RegisterPropertyBoolean('log_additionally', false);

        $this->RegisterPropertyString('recipients', json_encode([]));

        $this->RegisterPropertyInteger('activity_loglevel', self::$LOGLEVEL_NOTIFY);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    private function GetNoticeBase()
    {
        $noticeBase = $this->ReadPropertyInteger('noticeBase');
        $ids = IPS_GetInstanceListByModuleID('{4CF21C1E-B0F8-5535-8B5D-01ADDDB5DFD7}');
        foreach ($ids as $id) {
            if ($this->IsValidID($noticeBase) == false || $noticeBase == $id) {
                return $id;
            }
        }
        return 0;
    }

    private function GetTargetList()
    {
        $targets = false;
        $noticeBase = $this->GetNoticeBase();
        if (IPS_InstanceExists($noticeBase)) {
            $targets = Notice_GetTargetList($noticeBase);
        }
        $this->SendDebug(__FUNCTION__, 'targets=' . print_r($targets, true), 0);
        return $targets;
    }

    private function GetPresence()
    {
        $presence = false;
        $noticeBase = $this->GetNoticeBase();
        if (IPS_InstanceExists($noticeBase)) {
            $presence = Notice_GetPresence($noticeBase);
        }
        $this->SendDebug(__FUNCTION__, 'presence=' . print_r($presence, true), 0);
        return $presence;
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        if (IPS_InstanceExists($this->GetNoticeBase()) == false) {
            $this->SendDebug(__FUNCTION__, '"noticeBase" is empty and no global NoticeBase-instance', 0);
            $field = $this->Translate('Notice base');
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
                        $this->SendDebug(__FUNCTION__, '"recipients.params" of target ' . $target . ' has no json-coded content "' . $s . '"', 0);
                        $field = $this->Translate('Params');
                        $r[] = $this->TranslateFormat('Target "{$target}": field "{$field}" must be json-coded', ['{$target}' => $target, '{$field}' => $field]);
                    }
                }
            }
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();
        $oid = $this->GetNoticeBase();
        if (IPS_InstanceExists($oid)) {
            $this->RegisterReference($oid);
        }

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 0;

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
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
        $formElements = $this->GetCommonFormElements('Notice rule');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'         => 'SelectInstance',
            'validModules' => ['{4CF21C1E-B0F8-5535-8B5D-01ADDDB5DFD7}'],
            'name'         => 'noticeBase',
            'caption'      => 'Notice base'
        ];

        $targets = $this->GetTargetList();
        $target_opts = [];
        if (is_array($targets)) {
            $modeMapping = $this->ModeMapping();
            foreach ($targets as $target) {
                if (isset($modeMapping[$target['mode']]) == false) {
                    continue;
                }
                $c = $modeMapping[$target['mode']]['caption'];
                $v = $modeMapping[$target['mode']]['tag'];
                $target_opts[] = [
                    'caption' => $target['user']['name'] . '/' . $this->Translate($c),
                    'value'   => $this->TargetEncode($target['user']['id'], $v),
                ];
            }
        }

        $items = [];
        $items[] = [
            'type'      => 'Select',
            'options'   => $this->SeverityAsOptions(true),
            'name'      => 'default_severity',
            'caption'   => 'Default value for "severity"',
        ];
        $items[] = [
            'type'      => 'ValidationTextBox',
            'name'      => 'default_subject',
            'caption'   => 'Default value for "subject"',
            'width'     => '60%',
        ];
        $items[] = [
            'type'      => 'ValidationTextBox',
            'name'      => 'default_message',
            'caption'   => 'Default value for "message text"',
            'multiline' => true,
            'width'     => '80%',
        ];
        $items[] = [
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
                    ],
                ],
            ],
        ];

        $noticeBase = $this->GetNoticeBase();
        if (IPS_InstanceExists($noticeBase)) {
            $scriptID = (int) IPS_GetProperty($noticeBase, 'scriptID');
            if (IPS_ScriptExists($scriptID)) {
                $items[] = [
                    'type'      => 'ExpansionPanel',
                    'caption'   => 'Script',
                    'expanded'  => false,
                    'items'     => [
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
                            ],
                        ],
                    ],
                ];
            }
        }

        $formElements[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Default values',
            'expanded'  => false,
            'items'     => $items,
        ];

        $formElements[] = [
            'type'      => 'CheckBox',
            'caption'   => 'Log notice additionally',
            'name'      => 'log_additionally',
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
                    'add'     => self::$USAGE_ALWAYS,
                    'width'   => '250px',
                    'edit'    => [
                        'type'     => 'Select',
                        'options'  => $this->UsageAsOptions(),
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
                [
                    'name'    => 'params',
                    'caption' => 'Params',
                    'width'   => 'auto',
                    'add'     => '',
                    'edit'    => [
                        'type'    => 'ValidationTextBox',
                    ],
                ],
                [
                    'caption' => 'inactive',
                    'name'    => 'inactive',
                    'add'     => false,
                    'width'   => '100px',
                    'edit'    => [
                        'type' => 'CheckBox',
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
            'type'  => 'RowLayout',
            'items' => [
                [
                    'type'    => 'Button',
                    'caption' => 'Check rule validity',
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "CheckRuleValidity", "");',
                ],
                [
                    'type'    => 'PopupButton',
                    'caption' => 'Trigger rule',
                    'popup'   => [
                        'caption'   => 'Trigger rule',
                        'items'     => [
                            [
                                'type'    => 'ValidationTextBox',
                                'name'    => 'message',
                                'caption' => 'Message text',
                                'width'   => '600px',
                            ],
                            [
                                'type'    => 'ValidationTextBox',
                                'name'    => 'subject',
                                'caption' => 'Subject',
                                'width'   => '300px',
                            ],
                            [
                                'type'    => 'Select',
                                'name'    => 'severity',
                                'options' => $this->SeverityAsOptions(true),
                                'caption' => 'Severity',
                            ],
                        ],
                        'buttons' => [
                            [
                                'type'    => 'Button',
                                'caption' => 'Trigger',
                                'onClick' => $this->GetModulePrefix() . '_TriggerRule($id, $message, $subject, $severity, []);'
                            ],
                        ],
                        'closeCaption' => 'Cancel',
                    ],
                ],
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
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
            case 'CheckRuleValidity':
                $this->CheckRuleValidity();
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }

    private function CheckRuleValidity()
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
        $this->PopupMessage($s);
    }

    public function TriggerRule(string $message, string $subject, string $severity, array $params)
    {
        $this->PushCallChain(__FUNCTION__);

        $this->SendDebug(__FUNCTION__, 'message=' . $message . ', severity=' . $severity . ', params=' . print_r($params, true), 0);

        $default_subject = $this->ReadPropertyString('default_subject');
        $default_message = $this->ReadPropertyString('default_message');
        $default_severity = $this->ReadPropertyInteger('default_severity');

        $default_webfront_sound_info = $this->ReadPropertyString('default_webfront_sound_info');
        $default_webfront_sound_notice = $this->ReadPropertyString('default_webfront_sound_notice');
        $default_webfront_sound_warn = $this->ReadPropertyString('default_webfront_sound_warn');
        $default_webfront_sound_alert = $this->ReadPropertyString('default_webfront_sound_alert');

        $default_script_signal_info = $this->ReadPropertyString('default_script_signal_info');
        $default_script_signal_notice = $this->ReadPropertyString('default_script_signal_notice');
        $default_script_signal_warn = $this->ReadPropertyString('default_script_signal_warn');
        $default_script_signal_alert = $this->ReadPropertyString('default_script_signal_alert');

        $recipients = json_decode($this->ReadPropertyString('recipients'), true);

        if ($message == '') {
            $message = $default_message;
            $this->SendDebug(__FUNCTION__, 'message(default)=' . $message, 0);
        }
        if ($subject == '') {
            $subject = $default_subject;
            $this->SendDebug(__FUNCTION__, 'subject(default)=' . $subject, 0);
        }
        if (preg_match('/^[0-9]+$/', $severity) == false) {
            $severity = $this->SeverityDecode($severity);
            $this->SendDebug(__FUNCTION__, 'severity=' . $severity, 0);
        }
        if ($severity == self::$SEVERITY_UNKNOWN) {
            $severity = $default_severity;
            $this->SendDebug(__FUNCTION__, 'severity(default)=' . $severity, 0);
        }

        $result = false;

        $chainS = $this->PrintCallChain(false);

        $targetV = $this->EvaluateRule();
        if ($targetV != false) {
            $msg = 'targets=' . implode(',', $targetV) . ' (' . $chainS . ')';
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_MESSAGE) {
                $this->LogMessage($msg, KL_MESSAGE);
            }
            $this->AddModuleActivity($msg);
            $noticeBase = $this->GetNoticeBase();
            if (IPS_InstanceExists($noticeBase)) {
                foreach ($targetV as $target) {
                    $r = $this->TargetDecode($target);
                    $mode = $this->ModeDecode($r['mode']);

                    $e_params = false;
                    if ($recipients != false) {
                        foreach ($recipients as $recipient) {
                            @$e_params = json_decode($recipient['params'], true);
                            break;
                        }
                    }
                    if ($e_params == false) {
                        $e_params = [];
                    }

                    $l_params = array_merge($params, $e_params);

                    $l_params['severity'] = $severity;

                    if ($subject != '') {
                        $l_params['subject'] = $subject;
                    }

                    switch ($mode) {
                        case self::$MODE_WEBFRONT:
                            $sound = '';
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
                            if ($sound != '') {
                                $l_params['sound'] = $sound;
                            }
                            break;
                        case self::$MODE_MAIL:
                            break;
                        case self::$MODE_SMS:
                            break;
                        case self::$MODE_SCRIPT:
                            $signal = '';
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
                            if ($signal != '') {
                                $l_params['signal'] = $signal;
                            }
                            break;
                    }

                    $l_params['ruleID'] = $this->InstanceID;

                    $r = Notice_Deliver($noticeBase, $target, $message, $l_params);
                }

                if (isset($params['log_additionally'])) {
                    $log_additionally = $params['log_additionally'];
                } else {
                    $log_additionally = $this->ReadPropertyBoolean('log_additionally');
                }
                if ($log_additionally) {
                    $l_params['targets'] = $targetV;
                    if ($message == '') {
                        $message = $subject;
                    }
                    Notice_Log($noticeBase, $message, $severity, $l_params);
                }
            }
        } else {
            $msg = 'no matching targets (' . $chainS . ')';
            if ($this->ReadPropertyInteger('activity_loglevel') >= self::$LOGLEVEL_NOTIFY) {
                $this->LogMessage($msg, KL_NOTIFY);
            }
            $this->AddModuleActivity($msg);
        }

        $this->PopCallChain(__FUNCTION__);

        return $result;
    }

    private function EvaluateRule()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

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

        $alwaysV = [];
        $if_presentV = [];
        $if_awayV = [];
        $first_of_presentV = [];
        $last_goneV = [];
        $first_comeV = [];
        $if_no_oneV = [];

        foreach ($recipients as $recipient) {
            $usage = $recipient['usage'];
            if ($recipient['inactive']) {
                continue;
            }
            $target = $recipient['target'];
            $r = $this->TargetDecode($target);
            $user_id = strtoupper($r['user_id']);
            switch ($usage) {
                case self::$USAGE_ALWAYS:
                    $alwaysV[] = $target;
                    break;
                case self::$USAGE_IF_PRESENT:
                    if (in_array($user_id, $presence_at_home)) {
                        $if_presentV[] = $target;
                    }
                    break;
                case self::$USAGE_IF_AWAY:
                    if (in_array($user_id, $presence_be_away)) {
                        $if_awayV[] = $target;
                    }
                    break;
                case self::$USAGE_FIRST_OF_PERSENT:
                    if ($first_of_presentV == [] && $if_presentV == [] && in_array($user_id, $presence_at_home)) {
                        $first_of_presentV[] = $target;
                    }
                    break;
                case self::$USAGE_LAST_GONE:
                    if ($last_goneV == [] && $presence_last_gone == $user_id) {
                        $last_goneV[] = $target;
                    }
                    break;
                case self::$USAGE_FIRST_COME:
                    if ($first_comeV == [] && $presence_first_come == $user_id) {
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

        $targetV = array_merge($alwaysV, $if_presentV, $if_awayV, $first_of_presentV, $last_goneV, $first_comeV);
        if ($targetV == []) {
            $targetV = $if_no_oneV;
        }
        $this->SendDebug(__FUNCTION__, 'targets=' . print_r($targetV, true), 0);

        return $targetV;
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
