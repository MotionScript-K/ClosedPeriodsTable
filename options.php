<?php
defined('B_PROLOG_INCLUDED') || die;

/**
 * @var string $mid module id from GET
 */

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Highloadblock\HighloadBlockTable;
use Idex\Core\ORM\Entities\Tables\ClosedPeriodsTable;

global $APPLICATION, $USER;

if (!$USER->IsAdmin()) {
    ShowError(Loc::getMessage('NO_ACCESS_ERROR'));
    return;
}

$moduleId = 'artw.zolotoy';

$isRequiredModulesInstalled =
    Loader::includeModule('lists') &&
    Loader::includeModule('iblock') &&
    Loader::includeModule($moduleId);

if (!$isRequiredModulesInstalled) {
    ShowError(Loc::getMessage('NO_REQUIRED_MODULES'));
    return;
}

// Отрисовка таблицы закрытых периодов 
function renderClosedPeriodsData()
{
    if (!class_exists(ClosedPeriodsTable::class)) {
        return 'хайлоад "Закрытые периоды" не найден.';
    }

    try {
        $rows = ClosedPeriodsTable::getAllClosedPeriods();

        if (empty($rows)) {
            return '<p>Нет данных о закрытых периодах.</p>';
        }

        ob_start();
        ?>
        <table class="full-width-table" border="1" cellpadding="4" cellspacing="0">
            <thead>
            <tr>
                <th class="left-align">КБК</th>
                <th class="left-align">Периоды</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="nowrap"><?= htmlspecialchars($row['UF_KBK']) ?></td>
                    <td><?= htmlspecialchars(implode(', ', $row['UF_CLOSED_PERIODS'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    } catch (\Exception $e) {
        return 'Ошибка при работе ClosedPeriodsTable: ' . $e->getMessage();
    }
}

$tabs = [
  //*****
    [
        'DIV' => 'log_utility',
        'TAB' => Loc::getMessage('UTILITY_LOG_TAB_NAME'),
        'TITLE' => Loc::getMessage('UTILITY_LOG_TAB_NAME')
    ],
];
$options = [
  //*****
  'vacation' => [
  Loc::getMessage('VACATION_CLOSED_PERIOD'),
        [
            'VACATION_CLOSED_PERIOD',
            renderButtonClosedPeriod(),
            renderClosedPeriodsData(),
            ['statichtml'],
        ],
  ],
];

$fileOptions = ['LOG_UTILITY_INSTRUCTION_UO', 'LOG_UTILITY_INSTRUCTION_GI'];

if (check_bitrix_sessid() && strlen($_POST['save']) > 0) {
    Option::set($moduleId, 'ITIL_TASK_IMPORT_TIME', $_POST['ITIL_TASK_IMPORT_TIME']);
    Option::set($moduleId, 'ITIL_TASK_IMPORT_PERIOD', $_POST['ITIL_TASK_IMPORT_PERIOD']);
    foreach ($options as $option) {
        __AdmSettingsSaveOptions($moduleId, $option);
    }
    $bxRequest = Context::getCurrent()->getRequest();
    foreach ($fileOptions as $optName) {
        if (
            ($newFile = $bxRequest->getFile($optName . '_file'))
            && !empty($newFile['name'])
            && $newFile['size'] > 0
        ) {
            if ($fileId = CFile::SaveFile($newFile, 'uf')) {
                $oldVal = Option::get($moduleId, $optName . '_FILE');
                if ($oldVal) {
                    CFile::Delete($oldVal);
                }
                Option::set($moduleId, $optName . '_FILE', $fileId);
            }
        }
    }
    LocalRedirect($APPLICATION->GetCurPageParam());
}

$tabControl = new CAdminTabControl('tabControl', $tabs);
$tabControl->Begin();
?>
<form method="POST" enctype="multipart/form-data"
      action="<? echo $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($mid) ?>&lang=<?= LANGUAGE_ID ?>">
    <? $tabControl->BeginNextTab(); ?>
    <? __AdmSettingsDrawList($moduleId, $options['general']); ?>
    <? $tabControl->BeginNextTab(); ?>
    <? __AdmSettingsDrawList($moduleId, $options['itil_integration']); ?>
    <? $tabControl->BeginNextTab(); ?>
    <? __AdmSettingsDrawList($moduleId, $options['zup_integration']); ?>
    <? $tabControl->BeginNextTab(); ?>
    <? __AdmSettingsDrawList($moduleId, $options['messengers_list']); ?>
    <? $tabControl->BeginNextTab(); ?>
    <? __AdmSettingsDrawList($moduleId, $options['applications']); ?>
    <? $tabControl->BeginNextTab(); ?>
    <? __AdmSettingsDrawList($moduleId, $options['vacation']); ?>
    <? $tabControl->BeginNextTab(); ?>
    <? __AdmSettingsDrawList($moduleId, $options['work_schedule']); ?>
    <? $tabControl->BeginNextTab(); ?>
    <? __AdmSettingsDrawList($moduleId, $options['log_utility']); ?>
    <? $tabControl->Buttons(array('btnApply' => false, 'btnCancel' => false, 'btnSaveAndAdd' => false)); ?>
    <?= bitrix_sessid_post(); ?>
    <? $tabControl->End(); ?>
</form>

<script type="text/javascript">
    $('input[name="WS_EDIT_CUR_MONTH_KBK"]').attr('maxlength', 5000).prop('maxlength', 5000);
</script>

<style type="text/css">
    .change-file .adm-input-file > span {
        text-indent: -99999px;
        overflow: hidden;
        position: relative;
        width: 100px;
        display: inline-block;
    }

    .change-file .adm-input-file > span:after {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: block;
        content: "Заменить файл";
        text-indent: 0;
    }

    .full-width-table {
        width: 100%;
        border-collapse: collapse;
    }
    .nowrap {
        white-space: nowrap;
    }
    .left-align {
        text-align: left;
    }
</style>
