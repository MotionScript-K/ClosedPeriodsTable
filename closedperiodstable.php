<?php

namespace Idex\Core\ORM\Entities\Tables;

use Bitrix\Main\Entity\BooleanField;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\ORM\Fields\ArrayField;
use Bitrix\Main\Entity\EnumField;
use Idex\Core\ORM\Generated\Interfaces\Bitrix\DataManagerInterface;
use Bitrix\Main\Entity\StringField;


class ClosedPeriodsTable extends DataManager implements DataManagerInterface
{
    public static function getTableName(): string
    {
        return 'closed_periods_vacation'; // Имя вашего HL-блока
    }

    /**
     * Описание полей таблицы
     * @return array
     */
    public static function getMap(): array
    {
        return [
            'ID' => new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            'UF_KBK' => new StringField('UF_KBK', [
                'required' => true,
            ]),
            'UF_CLOSED_PERIODS' => (new ArrayField('UF_CLOSED_PERIODS', [
                'required' => true,
            ]))->configureSerializationPhp()
        ];
    }

    /**
     * Получение закрытых периодов
     * @return array
     */
    public static function getAllClosedPeriods(): array
    {
        $result = [];

        try {
            $res = self::getList([
                'select' => ['UF_KBK', 'UF_CLOSED_PERIODS'],
                'order' => ['UF_KBK' => 'ASC'],
            ]);

            while ($row = $res->fetch()) {
                $result[] = $row;
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Ошибка получения закрытых периодов: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Получение заблокированных периодов для указанного KBK
     * @param string $kbk
     * @return array
     */
    public static function getBlockedPeriodsByKbk(string $kbk): array
    {
        $result = [];
        $defaultRow = null;

        // Получаем все записи
        $res = self::getList([
            'filter' => [
                'UF_KBK' => [$kbk, '*'], // Ищем либо по конкретному КБК либо универсальную запись
            ],
            'order' => ['UF_KBK' => 'ASC'], // Сортируем * идёт позже
        ]);

        while ($row = $res->fetch()) {
            if ($row['UF_KBK'] === $kbk) { // Если нашли нужный КБК сразу выводим
                foreach ($row['UF_CLOSED_PERIODS'] as $period) {
                    [$from, $to] = explode('-', $period);
                    $result[] = [
                        'from' => $from,
                        'to' => $to,
                    ];
                }
                return $result;
            } elseif ($row['UF_KBK'] === '*') {
                $defaultRow = $row; // Сохраняем универсальную запись
            }
        }

        // Если не нашли запись с КБК то используем универсальную
        if ($defaultRow) {
            foreach ($defaultRow['UF_CLOSED_PERIODS'] as $period) {
                [$from, $to] = explode('-', $period);
                $result[] = [
                    'from' => $from,
                    'to' => $to,
                ];
            }
        }

        return $result;
    }
}
