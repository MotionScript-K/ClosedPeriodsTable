<?php

namespace Idex\Core\Vacation;

use Artw\HlLogger\LogLevel;
use Artw\Zolotoy\Helper\LoggerHelper;
use Artw\Zolotoy\Helper\ZupHelper;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use Idex\Core\Container;
use Idex\Core\Helpers\DatetimeHelper;
use Idex\Core\Notification\Notification;
use Idex\Core\ORM\Generated\Base\BaseResult;
use Idex\Core\ORM\Generated\Interfaces\ResultInterface;
use Idex\Core\ORM\Models\Iblock\IdexVacancy\VacationDepartmentPlan\IblockIdexVacancyVacationDepartmentPlanModel;
use Idex\Core\ORM\Models\Iblock\IdexVacancy\VacationPlan\IblockIdexVacancyVacationPlanModel;
use Idex\Core\ORM\Models\Iblock\IdexVacancy\VacationRests\IblockIdexVacancyVacationRestsModel;
use Idex\Core\ORM\Models\Iblock\IdexVacancy\VacationRestsCorrect\IblockIdexVacancyVacationRestsCorrectModel;
use Idex\Core\ORM\Models\Iblock\IdexVacancy\VacationUserPlan\IblockIdexVacancyVacationUserPlanModel;
use Idex\Core\ORM\Models\Iblock\Structure\FunctionalDepartments\IblockStructureFunctionalDepartmentsSectionModel;
use Idex\Core\ORM\Models\UserModel;
use Idex\Core\ORM\Repositories\Iblock\IdexVacancy\VacationDepartmentPlan\IblockIdexVacancyVacationDepartmentPlanRepository;
use Idex\Core\ORM\Repositories\Iblock\IdexVacancy\VacationPlan\IblockIdexVacancyVacationPlanRepository;
use Idex\Core\ORM\Repositories\Iblock\IdexVacancy\VacationRests\IblockIdexVacancyVacationRestsRepository;
use Idex\Core\ORM\Repositories\Iblock\IdexVacancy\VacationRestsCorrect\IblockIdexVacancyVacationRestsCorrectRepository;
use Idex\Core\ORM\Repositories\Iblock\IdexVacancy\VacationUserPlan\IblockIdexVacancyVacationUserPlanRepository;
use Idex\Core\RabbitMQ\Connection;
use Idex\Core\Struct\DepartmentService;
use Idex\Core\Utils;
use Idex\Core\Vacation\Chain\Chain;
use Idex\Core\Vacation\Chain\Step;
use Idex\Core\Vacation\Import\VacationFileReader;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Сервис по работе с планами отпусками
 */
class VacationPlanService
{
  //*****

  
/** @var Chain[] */
    public $chains = [];

    /** @var IblockIdexVacancyVacationDepartmentPlanRepository */
    public $vacationDepartmentPlanRepo;

    /** @var IblockIdexVacancyVacationPlanRepository */
    public $vacationPlanRepo;

    /** @var IblockIdexVacancyVacationUserPlanRepository */
    public $vacationUserPlanRepo;

    /** @var IblockIdexVacancyVacationRestsRepository */
    public $vacationRestsRepo;

    /** @var IblockIdexVacancyVacationRestsCorrectRepository */
    public $vacationRestsCorrectionRepo;

    private array $departmentCache = [];

    /**
     * VacationPlanService constructor.
     * @param IblockIdexVacancyVacationDepartmentPlanRepository $vacationDepartmentPlanRepo
     * @param IblockIdexVacancyVacationPlanRepository $vacationPlanRepo
     * @param IblockIdexVacancyVacationUserPlanRepository $vacationUserPlanRepo
     * @param IblockIdexVacancyVacationRestsRepository $vacationRestsRepo
     * @param IblockIdexVacancyVacationRestsCorrectRepository $vacationRestsCorrectionRepo
     */
    public function __construct(IblockIdexVacancyVacationDepartmentPlanRepository $vacationDepartmentPlanRepo, IblockIdexVacancyVacationPlanRepository $vacationPlanRepo, IblockIdexVacancyVacationUserPlanRepository $vacationUserPlanRepo, IblockIdexVacancyVacationRestsRepository $vacationRestsRepo, IblockIdexVacancyVacationRestsCorrectRepository $vacationRestsCorrectionRepo)
    {
        $this->vacationPlanRepo = $vacationPlanRepo;
        $this->vacationUserPlanRepo = $vacationUserPlanRepo;
        $this->vacationRestsRepo = $vacationRestsRepo;
        $this->vacationDepartmentPlanRepo = $vacationDepartmentPlanRepo;
        $this->vacationRestsCorrectionRepo = $vacationRestsCorrectionRepo;
    }

  //*****
  
  /**
     * Рознице нельзя бронировать в эти дни
     * @param DateTime $dateFrom
     * @param DateTime|null $dateTo
     * @return bool
     * @throws \Bitrix\Main\ObjectException
     */
    public function isRetailBlockedDay(DateTime $dateFrom, ?DateTime $dateTo = null, $departmentPlan = null)
    {
        static $intervals;
        $kbk = null;
        if ($departmentPlan) {
            $depId = $departmentPlan->getPropertyDepartment();
        }

        if ($depId) {
            $service = Container::getDepartmentService();
            $department = $service->getFunctionalRepo()->getById($depId);

            if ($department) {
                $parents = $department->getParents();
                foreach ($parents as $parent) {
                    if (preg_match($service::FILIAL_KBK, $parent->getXmlId())) {
                        $kbk = $parent->getXmlId();
                        break;
                    }
                }
            }
        }
        $period = $this->getPeriod();

        // Если KBK найден загружаем периоды из HL-блока
        if ($kbk) {
            $intervals = \Idex\Core\ORM\Entities\Tables\ClosedPeriodsTable::getBlockedPeriodsByKbk($kbk);
            $newIntervals = [];
            foreach ($intervals as &$interval) {
                $from = $interval['from'];
                $to = $interval['to'];
                $newIntervals[] = [
                    'from' => new DateTime("{$from}.{$period}"),
                    'to' => new DateTime("{$to}.{$period}"),
                ];
            }
        }

        if($newIntervals) {
            foreach ($newIntervals as $interval) {
                if (!$dateTo) {
                    if (($interval['from'] <= $dateFrom) && ($dateFrom <= $interval['to'])) {
                        return true;
                    }
                } elseif (($interval['from'] <= $dateTo) && ($dateFrom <= $interval['to'])) {
                    return true;
                }
            }
        }
        return false;
    }
}
