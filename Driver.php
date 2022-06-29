<?php
namespace common\models\base;

use Yii;
use yii\behaviors\{BlameableBehavior, TimestampBehavior};
use yii\db\ActiveRecord;
use common\models\{Account, Carrier, State, User, Driver, Report, Unit, Office, Vendor};

/**
 * This is the base-model class for table "driver"
 *
 */
abstract class Driver extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'driver';
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            [
                'class' => BlameableBehavior::class(),
            ],
            [
                'class' => TimestampBehavior::class(),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['last_name', 'first_name', 'state_id', 'mail_list', 'maintenance'], 'required'],
            [['state_id', 'office_id', 'pay_to_vendor_id', 'pay_to_driver_id', 'pay_to_carrier_id', 'co_driver_id', 'user_id'], 'default', 'value' => null],
            [['state_id', 'office_id', 'pay_to_vendor_id', 'pay_to_driver_id', 'pay_to_carrier_id', 'co_driver_id', 'user_id'], 'integer'],
            [['passport_exp', 'date_of_birth', 'hire_date', 'marked_as_down'], 'safe'],
            [['mail_list', 'maintenance'], 'boolean'],
            [['notes'], 'string'],
            [['period_salary', 'hourly_rate', 'addl_ot_pay', 'addl_ot_pay_2', 'base_hours', 'loaded_per_mile', 'empty_per_mile', 'percentage', 'co_driver_earning_percent'], 'number'],
            [['last_name', 'first_name', 'middle_name', 'address_1', 'address_2', 'city', 'zip', 'telephone', 'cell_phone', 'other_phone', 'web_id', 'email_address', 'user_defined_1', 'user_defined_2', 'user_defined_3', 'social_sec_no', 'passport_no', 'type', 'expense_acct', 'bank_acct', 'pay_standard', 'pay_source', 'loaded_miles', 'empty_miles', 'loaded_pay_type', 'pay_frequency'], 'string', 'max' => 255],
            [['status'], 'string', 'max' => 25],
            [['user_id'], 'unique'],
            [['expense_acct'], 'exist', 'skipOnError' => true, 'targetClass' => Account::class, 'targetAttribute' => ['expense_acct' => 'account']],
            [['bank_acct'], 'exist', 'skipOnError' => true, 'targetClass' => Account::class, 'targetAttribute' => ['bank_acct' => 'account']],
            [['pay_to_carrier_id'], 'exist', 'skipOnError' => true, 'targetClass' => Carrier::class, 'targetAttribute' => ['pay_to_carrier_id' => 'id']],
            [['pay_to_driver_id'], 'exist', 'skipOnError' => true, 'targetClass' => Driver::class, 'targetAttribute' => ['pay_to_driver_id' => 'id']],
            [['co_driver_id'], 'exist', 'skipOnError' => true, 'targetClass' => Driver::class, 'targetAttribute' => ['co_driver_id' => 'id']],
            [['office_id'], 'exist', 'skipOnError' => true, 'targetClass' => Office::class, 'targetAttribute' => ['office_id' => 'id']],
            [['state_id'], 'exist', 'skipOnError' => true, 'targetClass' => State::class, 'targetAttribute' => ['state_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['pay_to_vendor_id'], 'exist', 'skipOnError' => true, 'targetClass' => Vendor::class, 'targetAttribute' => ['pay_to_vendor_id' => 'id']]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'last_name' => Yii::t('app', 'Last Name'),
            'first_name' => Yii::t('app', 'First Name'),
            'middle_name' => Yii::t('app', 'Middle Name'),
            'address_1' => Yii::t('app', 'Address 1'),
            'address_2' => Yii::t('app', 'Address 2'),
            'city' => Yii::t('app', 'City'),
            'state_id' => Yii::t('app', 'State ID'),
            'zip' => Yii::t('app', 'Zip'),
            'telephone' => Yii::t('app', 'Telephone'),
            'cell_phone' => Yii::t('app', 'Cell Phone'),
            'other_phone' => Yii::t('app', 'Other Phone'),
            'office_id' => Yii::t('app', 'Office ID'),
            'web_id' => Yii::t('app', 'Web ID'),
            'email_address' => Yii::t('app', 'Email Address'),
            'user_defined_1' => Yii::t('app', 'User Defined 1'),
            'user_defined_2' => Yii::t('app', 'User Defined 2'),
            'user_defined_3' => Yii::t('app', 'User Defined 3'),
            'social_sec_no' => Yii::t('app', 'Social Sec No'),
            'passport_no' => Yii::t('app', 'Passport No'),
            'passport_exp' => Yii::t('app', 'Passport Exp'),
            'date_of_birth' => Yii::t('app', 'Date Of Birth'),
            'hire_date' => Yii::t('app', 'Hire Date'),
            'mail_list' => Yii::t('app', 'Mail List'),
            'maintenance' => Yii::t('app', 'Maintenance'),
            'notes' => Yii::t('app', 'Notes'),
            'updated_by' => Yii::t('app', 'Updated By'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'created_by' => Yii::t('app', 'Created By'),
            'created_at' => Yii::t('app', 'Created At'),
            'marked_as_down' => Yii::t('app', 'Marked As Down'),
            'type' => Yii::t('app', 'Type'),
            'pay_to_vendor_id' => Yii::t('app', 'Pay To Vendor ID'),
            'pay_to_driver_id' => Yii::t('app', 'Pay To Driver ID'),
            'pay_to_carrier_id' => Yii::t('app', 'Pay To Carrier ID'),
            'expense_acct' => Yii::t('app', 'Expense Acct'),
            'bank_acct' => Yii::t('app', 'Bank Acct'),
            'co_driver_id' => Yii::t('app', 'Co Driver ID'),
            'pay_standard' => Yii::t('app', 'Pay Standard'),
            'period_salary' => Yii::t('app', 'Period Salary'),
            'hourly_rate' => Yii::t('app', 'Hourly Rate'),
            'addl_ot_pay' => Yii::t('app', 'Addl Ot Pay'),
            'addl_ot_pay_2' => Yii::t('app', 'Addl Ot Pay 2'),
            'base_hours' => Yii::t('app', 'Base Hours'),
            'pay_source' => Yii::t('app', 'Pay Source'),
            'loaded_miles' => Yii::t('app', 'Loaded Miles'),
            'empty_miles' => Yii::t('app', 'Empty Miles'),
            'loaded_pay_type' => Yii::t('app', 'Loaded Pay Type'),
            'loaded_per_mile' => Yii::t('app', 'Loaded Per Mile'),
            'empty_per_mile' => Yii::t('app', 'Empty Per Mile'),
            'percentage' => Yii::t('app', 'Percentage'),
            'status' => Yii::t('app', 'Status'),
            'user_id' => Yii::t('app', 'User ID'),
            'pay_frequency' => Yii::t('app', 'Pay Frequency'),
            'co_driver_earning_percent' => Yii::t('app', 'Co Driver Earning Percent'),
        ];
    }

    public function getReports(): ActiveQuery
    {
        return $this->hasMany(Report::class, ['driver_id' => 'id']);
    }

    public function getUnits(): ActiveQuery
    {
        return $this->hasMany(Unit::class, ['driver_id' => 'id']);
    }
}
