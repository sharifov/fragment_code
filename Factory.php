<?php

namespace Calculations\Recalculation\LowQuality\RecalculationServices;

use Accounts;
use CalculationAccrual;
use DateTimeImmutable;
use Exception;
use HouseCalculationService;
use BCMathHelper\BCMathHelper;
use Calculations\Components\Calculators\EntityDaysInPeriodInterface;
use Calculations\Components\Calculators\IDayNightMinutesCalculator;
use Calculations\Components\Scale\IScaleSettings;
use Calculations\DTO\RecalculationPeriodFactory;
use Calculations\DTO\RecalculationPeriodInterface;
use Calculations\Recalculation\Common\DataProviders\IAccrualsDP;
use Calculations\Recalculation\LowQuality\DTO\FRecalculationDetailsDTO;
use Calculations\Recalculation\LowQuality\DTO\IRecalculationResultDTO;

use function bcadd;
use function bccomp;
use function bcdiv;
use function bcsub;

class AbstractRecalculation
{
    protected const ZERO_SCALE = 0;

    /** @var IDayNightMinutesCalculator */
    protected $minutesCalculator;

    /** @var IAccrualsDP */
    protected $accrualsDP;

    /** @var HouseCalculationService */
    protected $houseCalculationService;

    /** @var FRecalculationDetailsDTO */
    protected $detailsFactory;

    /** @var IScaleSettings */
    protected $scale;

    /** @var EntityDaysInPeriodInterface */
    protected $accountDaysInPeriodCalculator;

    /** @var RecalculationPeriodFactory */
    protected $recalculationPeriodFactory;

    public function __construct(
        IDayNightMinutesCalculator $minutesCalculator,
        EntityDaysInPeriodInterface $accountDaysInPeriodCalculator,
        IAccrualsDP $accrualsDP,
        HouseCalculationService $houseCalculationService,
        FRecalculationDetailsDTO $detailsFactory,
        IScaleSettings $scale,
        RecalculationPeriodFactory $recalculationPeriodFactory
    ) {
        $this->minutesCalculator = $minutesCalculator;
        $this->accountDaysInPeriodCalculator = $accountDaysInPeriodCalculator;
        $this->accrualsDP = $accrualsDP;
        $this->houseCalculationService = $houseCalculationService;
        $this->detailsFactory = $detailsFactory;
        $this->scale = $scale;
        $this->recalculationPeriodFactory = $recalculationPeriodFactory;
    }

    protected function normalizeResult(IRecalculationResultDTO $dto, array $recalcPeriods): IRecalculationResultDTO
    {
        if ($recalcPeriods === []) {
            return $dto;
        }

        $details = $dto->getAllDetails();

        foreach ($recalcPeriods as $period => $val) {
            $accrual = $dto->getAccrual($period);
            if ($accrual === null) {
                continue;
            }

            $accrualSum = bcadd($accrual->getValue(), $accrual->getRecalcExcessDays(), $this->scale->money());

            foreach ($details[$period] as $detail) {
                if (bccomp($accrualSum, $detail->estimateValue(), $this->scale->money()) >= 0) {
                    $detail->setValue($detail->estimateValue());
                    $detail->setVolume($detail->estimateVolume());
                } else {
                    $detail->setValue($accrualSum);
                    $volume = bcdiv($accrualSum, $detail->rate(), $this->scale->calculation());
                    $volume = BCMathHelper::round($volume, $this->scale->volume());
                    $detail->setVolume($volume);
                }

                $accrualSum = bcsub($accrualSum, $detail->estimateValue(), $this->scale->money());
                if (bccomp($accrualSum, '0.00', $this->scale->money()) < 0) {
                    $accrualSum = '0.00';
                }
            }
        }
        return $dto;
    }

    /** @throws Exception */
    protected function getRecalculationPeriodWithAccountDatesLimit(
        string $accountBegin,
        ?string $accountEnd,
        string $recalculationPeriodBegin,
        string $recalculationPeriodEnd
    ): ?RecalculationPeriodInterface {
        $accBegNormalized = "{$accountBegin} 00:00:00";

        if ($accountBegin > $recalculationPeriodEnd) {
            /** ЛС не попадает в период перерасчета */
            return null;
        }

        if ($accountEnd === null || $accountEnd === '') {
            $accEndNormalized = $recalculationPeriodEnd;
        } else {
            $accEndNormalized = "{$accountEnd} 23:59:59";
            if ($accEndNormalized < $recalculationPeriodBegin) {
                /** ЛС не попадает в период перерасчета */
                return null;
            }
        }
        $begin = ($recalculationPeriodBegin < $accBegNormalized)
            ? new DateTimeImmutable($accBegNormalized) : new DateTimeImmutable($recalculationPeriodBegin);
        $end = ($recalculationPeriodEnd > $accEndNormalized)
            ? new DateTimeImmutable($accEndNormalized) : new DateTimeImmutable($recalculationPeriodEnd);

        return $this->recalculationPeriodFactory->create($begin, $end);
    }

    protected function getAccrual(
        Accounts $account,
        IRecalculationResultDTO $recalculationAccount,
        string $recalcMonth
    ): ?CalculationAccrual {
        return $this->accrualsDP->getAccrual(
            (int)$account->id,
            (int)$recalculationAccount->recalculation()->utility_id,
            $recalcMonth
        );
    }

    protected function getVolume(string $value, string $rate): string
    {
        $result = bcdiv($value, $rate, $this->scale->calculation());
        return BCMathHelper::round($result, $this->scale->volume());
    }

    protected function checkRate(?string $rate): bool
    {
        if ($rate === null || $rate === '' || bccomp($rate, '0', $this->scale->calculation()) === 0) {
            return false;
        }
        return true;
    }
}
