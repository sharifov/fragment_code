<?php

declare(strict_types=1);

namespace Calculations\Recalculation\LowQuality;

use App\Db\Pure\Models\HousesCards;
use App\Db\Pure\Models\RecalculationsLowQuality\RecalculationsLowQuality;
use App\Db\Pure\Models\RecalculationsLowQuality\RecalculationsLowQualityAccounts;
use App\Db\Pure\Models\RecalculationsLowQuality\RecalculationsLowQualityAccountsStructure;
use App\Db\Pure\Models\RecalculationsLowQuality\RecalculationsLowQualityHousesCards;
use App\Db\Pure\Models\RecalculationsLowQuality\RecalculationsLowQualityHousesCardsStructure;
use App\Db\Pure\Models\RecalculationsLowQuality\RecalculationsLowQualityPeriods;
use App\Db\Pure\Models\RecalculationsLowQuality\RecalculationsLowQualityPeriodsStructure;
use App\Db\Pure\Models\RecalculationsLowQuality\RecalculationsLowQualityStructure;
use CDbCriteria;
use CDbExpression;
use CException;
use CHttpException;
use DateTimeImmutable;
use DbHelper;
use InvalidArgumentException;
use Properties;
use PropertiesPresets;
use Utilities;
use Yii;

use function array_map;

class RecalculationsLowQualityRepository
{
    /**
     * @var string
     */
    private const LINE_BREAK = '<br/>';
    /**
     * @var array
     */
    private $utilities = [];

    /**
     * @var array
     */
    private $propertiesPresets;

    public function getLineBreak(): string
    {
        return self::LINE_BREAK;
    }

    /**
     * @return PropertiesPresets[]
     */
    public function getOwnershipTypes(): array
    {
        $criteria = new CDbCriteria();
        $criteria->compare('properties_id', Properties::PROPERTY_TYPE_ID);
        $criteria->order = 'sort DESC';
        return PropertiesPresets::model()->findAll($criteria);
    }

    public function getFormData(int $formId): array
    {
        $data = [];
        $lowQuality = RecalculationsLowQuality::model()->findByPk($formId);
        if (!$lowQuality) {
            throw new InvalidArgumentException('Нет такой записи в перерасчетах!');
        }
        $data['id'] = $formId;
        $data['type_id'] = $lowQuality->type_id;
        $data['accounts_type'] = $lowQuality->accounts_usage_type_id;
        $data['period'] = $lowQuality->calculation_period;
        $data['utilities_id'] = $lowQuality->utility_id;
        $data['accounts'] = $lowQuality->accounts->account_id ?? null;
        $data['houses_cards_id'] = $lowQuality->houseCards->id ?? null;
        $data['reason'] = $lowQuality->reason;
        $data['comment'] = $lowQuality->comment;
        $data['lowQualityPeriods'] = $this->getPeriodsAsArray($lowQuality);

        return $data;
    }

    public function getHousesCards(): array
    {
        $criteria = new CDbCriteria();
        $criteria->with = [
            'organizationsHouses' => [
                'joinType' => 'INNER JOIN',
                'alias' => 'oh',
            ]
        ];
        $criteria->select = [
            't.id',
            new CDbExpression('TRIM(CONCAT(t.address, " ", t.note)) as address')
        ];
        $criteria->compare('t.deleted', 0);
        $criteria->compare('oh.organizations_id', Yii::app()->user->orgId);
        $criteria->addCondition(DbHelper::dateCondition('oh', 'current'));
        $criteria->order = 't.address ASC';
        return array_filter(
            array_column(
                HousesCards::model()->findAll($criteria),
                'address',
                'id'
            ),
            function (int $key) {
                return Yii::app()->user->checkOrgAccess('recalculations.lk_create', [], $key);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    public function getLowQualityList(): array
    {
        $criteria = new CDbCriteria();
        $criteria->with = [
            RecalculationsLowQualityStructure::REL_UTILITY => [
                'alias' => 'u',
                'select' => 'id, name',
            ],
            RecalculationsLowQualityHousesCardsStructure::REL_HOUSES_CARD => [
                'select' => 'id, address, info',
                'join' => 'LEFT JOIN calculations_log_houses_cards clhc 
                    ON clhc.houses_cards_id = hc.id 
                    LEFT JOIN calculations_log cl 
                    ON cl.id = clhc.calculations_log_id AND cl.period = t.calculation_period AND 
                    cl.show_in_lk = :show_in_lk AND cl.deleted = :deleted',
            ],
        ];
        $criteria->select = [
            new CDbExpression("hc.id as houses_cards_id"),
            new CDbExpression("hc.address as address"),
            new CDbExpression("t.created as date"),
            new CDbExpression('"Ненадлежащее качество (ГВС)" AS recalculation_name'),
            new CDbExpression('"low_quality" AS recalculation_code'),
            new CDbExpression("GROUP_CONCAT(u.name SEPARATOR ', ') AS utilities"),
            new CDbExpression('t.calculation_period AS period'),
            new CDbExpression("IFNULL(GROUP_CONCAT(DISTINCT t.reason SEPARATOR ', '), t.comment) AS comment"),
            new CDbExpression("IF(GROUP_CONCAT(cl.id  SEPARATOR ','), 1, 0) AS deny"),
            new CDbExpression("'0' AS one_value_to_all")
        ];
        $criteria->compare('t.deleted', 0);
        $criteria->compare('t.organization_id', Yii::app()->user->orgId);
        $criteria->params[':deleted'] = 0;
        $criteria->params[':show_in_lk'] = 1;
        $criteria->group = 't.id';
        return RecalculationsLowQuality::model()->findAll($criteria);
    }

    /**
     * @return PropertiesPresets[][]
     */
    public function getPropertiesPresets(): array
    {
        if ($this->propertiesPresets !== null) {
            return $this->propertiesPresets;
        }
        $criteria = new CDbCriteria();
        $criteria->compare(
            'properties_id',
            [
                Properties::COOKER_TYPE_ID,
                Properties::LIFT_ID,
                Properties::RADIO_ID,
                Properties::INTERNET_ID,
                Properties::INTERCOM_ID,
            ]
        );
        $criteria->order = 'sort DESC';
        $presets = [];
        foreach (PropertiesPresets::model()->findAll($criteria) as $preset) {
            $presets[$preset->properties_id][] = $preset;
        }
        return $this->propertiesPresets = $presets;
    }

    public function getUtilities(int $housesCardsId, array $utilitiesTypes): array
    {
        if (array_key_exists($housesCardsId, $this->utilities)) {
            return $this->utilities[$housesCardsId];
        }
        $criteria = new CDbCriteria();
        $criteria->with = [
            'utilitiesTypes',
            'organizationsUtilities.housesUtilities'
        ];
        $criteria->compare('utilitiesTypes.id', $utilitiesTypes);
        $criteria->compare('housesUtilities.houses_cards_id', $housesCardsId);
        $criteria->compare('organizationsUtilities.organizations_id', Yii::app()->user->orgId);
        $criteria->compare('t.deleted', 0);
        Utilities::$withoutProperties = true;
        $utilities = Utilities::model()->findAll($criteria);
        Utilities::$withoutProperties = false;
        return $utilities;
    }

    public function getAlreadyLowQualityPeriods(
        int $houseCardId,
        int $typeId,
        int $utilityId,
        int $accountUsageId,
        int $organizationId,
        int $recalculationId = 0
    ): array {
        $criteria = new CDbCriteria();
        $criteria->with = [
            RecalculationsLowQualityPeriodsStructure::REL_RECALCULATIONS_LOW_QUALITY,
            RecalculationsLowQualityPeriodsStructure::REL_HOUSES_CARDS
        ];
        $criteria->compare(
            RecalculationsLowQualityPeriodsStructure::REL_HOUSES_CARDS
            . '.'
            . RecalculationsLowQualityHousesCardsStructure::COL_HOUSE_CARD_ID,
            $houseCardId
        );
        $criteria->compare(
            RecalculationsLowQualityStructure::COL_TYPE_ID,
            $typeId
        );
        $criteria->compare(
            RecalculationsLowQualityStructure::COL_UTILITY_ID,
            $utilityId
        );
        $criteria->compare(
            RecalculationsLowQualityStructure::COL_ACCOUNTS_USAGE_TYPE_ID,
            $accountUsageId
        );
        $criteria->compare(
            RecalculationsLowQualityStructure::COL_ORGANIZATION_ID,
            $organizationId
        );
        if ($recalculationId) {
            $criteria->addCondition(
                't.' . RecalculationsLowQualityPeriodsStructure::COL_RECALCULATION_ID . '<> :recalculationId'
            );
            $criteria->params[':recalculationId'] = $recalculationId;
        }
        return RecalculationsLowQualityPeriods::model()->findAll($criteria);
    }

    /** @throws CException */
    public function saveRecalculation(array $formData): void
    {
        $recalculationId = !empty($formData['id']) ? $formData['id'] : 0;
        if ($recalculationId) {
            $this->updateRecalculation($formData);
        } else {
            $this->createRecalculation($formData);
        }
    }

    /** @throws CException */
    public function delete(int $id): void
    {
        $recalculationsLowQuality = RecalculationsLowQuality::model()->findByPk($id);
        if (null === $recalculationsLowQuality) {
            throw new CHttpException(404, 'Нет такого перерасчета!');
        }
        $recalculationsLowQuality->deleted = 1;
        $recalculationsLowQuality->update('deleted');
    }

    /** @return array[] */
    private function getPeriodsAsArray(RecalculationsLowQuality $recalculation): array
    {
        $result = [];
        if (is_array($recalculation->periods) && $recalculation->periods != []) {
            $dateTime = new DateTimeImmutable();
            foreach ($recalculation->periods as $period) {
                $attrs = $period->getAttributes();
                $begin = $dateTime->modify($period->begin);
                $end = $dateTime->modify($period->end);
                $attrs['dateFrom'] = $begin->format('d.m.Y');
                $attrs['dateTo'] = $end->format('d.m.Y');
                $attrs['timeFrom'] = $begin->format('H:i');
                $attrs['timeTo'] = $end->format('H:i');
                $attrs['recalculationCoefficient'] = $period->temperature_in_act == null;
                $result[] = $attrs;
            }
        }

        return $result;
    }

    /** @throws CException */
    private function createRecalculation(array $formData): void
    {
        $recalculationsLowQuality = new RecalculationsLowQuality();
        $recalculationsLowQuality->setAttributes([
            RecalculationsLowQualityStructure::COL_UTILITY_ID => $formData['utilities_id'],
            RecalculationsLowQualityStructure::COL_ACCOUNTS_USAGE_TYPE_ID => $formData['accounts_type'],
            RecalculationsLowQualityStructure::COL_TYPE_ID => $formData['type_id'],
            RecalculationsLowQualityStructure::COL_ORGANIZATION_ID => Yii::app()->user->orgId,
            RecalculationsLowQualityStructure::COL_CALCULATION_PERIOD => $formData['period'],
            RecalculationsLowQualityStructure::COL_REASON => $formData['reason'],
            RecalculationsLowQualityStructure::COL_COMMENT => $formData['comment'],
            RecalculationsLowQualityStructure::COL_DO_ODN => $formData['doODN'],
            RecalculationsLowQualityStructure::COL_CREATED => date('Y-m-d H:i:s')
        ]);
        if ($recalculationsLowQuality->save()) {
            $this->createLowQualityHouseCards((int)$recalculationsLowQuality->id, $formData);
            //$this->createLowQualityAccounts((int) $recalculationsLowQuality->id, $formData);
            $this->createLowQualityPeriods((int)$recalculationsLowQuality->id, $formData);
        } else {
            throw new CException(
                'Таблица RecalculationsLowQuality:' . self::LINE_BREAK . $this->error2Text(
                    $recalculationsLowQuality->getErrors()
                )
            );
        }
    }

    /**
     * @throws CException
     */
    private function createLowQualityHouseCards(int $recalculationNewRecordId, array $formData): void
    {
        $recalculationsLowQualityHousesCard = new RecalculationsLowQualityHousesCards();
        $recalculationsLowQualityHousesCard->setAttributes([
            RecalculationsLowQualityHousesCardsStructure::COL_RECALCULATION_ID => $recalculationNewRecordId,
            RecalculationsLowQualityHousesCardsStructure::COL_HOUSE_CARD_ID => $formData['houses_cards_id']
        ]);
        if (!$recalculationsLowQualityHousesCard->save()) {
            throw new CException(
                'Таблица RecalculationsLowQualityHousesCard:' . self::LINE_BREAK . $this->error2Text(
                    $recalculationsLowQualityHousesCard->getErrors()
                )
            );
        }
    }

    /**
     * @throws CException
     */
    private function createLowQualityAccounts(int $recalculationNewRecordId, array $formData): void
    {
        $recalculationsLowQualityAccounts = new RecalculationsLowQualityAccounts();
        $recalculationsLowQualityAccounts->setAttributes([
            RecalculationsLowQualityAccountsStructure::COL_RECALCULATION_ID => $recalculationNewRecordId,
            RecalculationsLowQualityAccountsStructure::COL_ACCOUNT_ID => $formData['accounts_type']
        ]);
        if (!$recalculationsLowQualityAccounts->save()) {
            throw new CException(
                'Таблица RecalculationsLowQualityAccounts:' . self::LINE_BREAK . $this->error2Text(
                    $recalculationsLowQualityAccounts->getErrors()
                )
            );
        }
    }

    /**
     * @throws CException
     */
    private function createLowQualityPeriods(int $recalculationNewRecordId, array $formData): void
    {
        if (!empty($formData['periods']) && is_array($formData['periods'])) {
            foreach ($formData['periods'] as $period) {
                $recalculationsLowQualityPeriods = new RecalculationsLowQualityPeriods();
                $recalculationsLowQualityPeriods->setAttributes([
                    RecalculationsLowQualityPeriodsStructure::COL_RECALCULATION_ID => $recalculationNewRecordId,
                    RecalculationsLowQualityPeriodsStructure::COL_BEGIN => $period['startDateTimeText'],
                    RecalculationsLowQualityPeriodsStructure::COL_END => $period['endDateTimeText'],
                    RecalculationsLowQualityPeriodsStructure::COL_TEMPERATURE_IN_ACT => $period['temperature_in_act'] ?? null,
                    RecalculationsLowQualityPeriodsStructure::COL_TEMPERATURE_ALLOWED => $period['temperature_allowed'] ?? null,
                    RecalculationsLowQualityPeriodsStructure::COL_DAY_COEFFICIENT => $period['day_coefficient'] ?? null,
                    RecalculationsLowQualityPeriodsStructure::COL_NIGHT_COEFFICIENT => $period['night_coefficient'] ?? null,
                ]);
                if (!$recalculationsLowQualityPeriods->save()) {
                    throw new CException(
                        'Таблица RecalculationsLowQualityPeriods:' . self::LINE_BREAK . $this->error2Text(
                            $recalculationsLowQualityPeriods->getErrors()
                        )
                    );
                }
            }
        }
    }

    /** @throws CException */
    private function updateRecalculation(array $formData): void
    {
        $recalculationRecord = RecalculationsLowQuality::model()->findByPk($formData['id']);
        $recalculationRecord->setAttributes([
            RecalculationsLowQualityStructure::COL_UTILITY_ID => $formData['utilities_id'],
            RecalculationsLowQualityStructure::COL_ACCOUNTS_USAGE_TYPE_ID => $formData['accounts_type'],
            RecalculationsLowQualityStructure::COL_TYPE_ID => $formData['type_id'],
            RecalculationsLowQualityStructure::COL_ORGANIZATION_ID => Yii::app()->user->orgId,
            RecalculationsLowQualityStructure::COL_CALCULATION_PERIOD => $formData['period'],
            RecalculationsLowQualityStructure::COL_REASON => $formData['reason'],
            RecalculationsLowQualityStructure::COL_COMMENT => $formData['comment'],
            RecalculationsLowQualityStructure::COL_DO_ODN => $formData['doODN']
        ]);
        if ($recalculationRecord->save()) {
            $this->updateLowQualityHouseCards($formData);
            //$this->updateLowQualityAccounts($formData);
            $this->updateLowQualityPeriods($formData);
        } else {
            throw new CException(
                'Таблица RecalculationsLowQuality:' . self::LINE_BREAK . $this->error2Text(
                    $recalculationRecord->getErrors()
                )
            );
        }
    }

    /** @throws CException */
    private function updateLowQualityHouseCards(array $formData): void
    {
        $recalculationsLowQualityHousesCard = RecalculationsLowQualityHousesCards::model()->findByAttributes([
            RecalculationsLowQualityHousesCardsStructure::COL_RECALCULATION_ID => $formData['id']
        ]);
        if ($recalculationsLowQualityHousesCard) {
            $recalculationsLowQualityHousesCard->setAttributes([
                RecalculationsLowQualityHousesCardsStructure::COL_HOUSE_CARD_ID => $formData['houses_cards_id']
            ]);
            if (!$recalculationsLowQualityHousesCard->save()) {
                throw new CException(
                    'Таблица RecalculationsLowQualityHousesCard:' . self::LINE_BREAK . $this->error2Text(
                        $recalculationsLowQualityHousesCard->getErrors()
                    )
                );
            }
        } else {
            $this->createLowQualityHouseCards($formData['id'], $formData);
        }
    }

    /** @throws CException */
    private function updateLowQualityAccounts(array $formData): void
    {
        $recalculationsLowQualityAccounts = RecalculationsLowQualityAccounts::model()->findByAttributes([
            RecalculationsLowQualityAccountsStructure::COL_RECALCULATION_ID => $formData['id']
        ]);
        if ($recalculationsLowQualityAccounts) {
            $recalculationsLowQualityAccounts->setAttributes([
                RecalculationsLowQualityAccountsStructure::COL_ACCOUNT_ID => $formData['accounts_type']
            ]);
            if (!$recalculationsLowQualityAccounts->save()) {
                throw new CException(
                    'Таблица RecalculationsLowQualityAccounts:' . self::LINE_BREAK . $this->error2Text(
                        $recalculationsLowQualityAccounts->getErrors()
                    )
                );
            }
        } else {
            $this->createLowQualityAccounts($formData['id'], $formData);
        }
    }

    /** @throws CException */
    private function updateLowQualityPeriods(array $formData): void
    {
        RecalculationsLowQualityPeriods::model()->deleteAllByAttributes([
            RecalculationsLowQualityPeriodsStructure::COL_RECALCULATION_ID => $formData['id']
        ]);
        $this->createLowQualityPeriods($formData['id'], $formData);
    }

    private function error2Text(array $errorData): string
    {
        if ($errorData) {
            return implode(
                self::LINE_BREAK,
                array_map(function (array $val): string {
                    return implode(self::LINE_BREAK, $val);
                }, $errorData)
            );
        }
        return '';
    }
}
