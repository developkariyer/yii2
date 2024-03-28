<?php

namespace app\models;

use Yii;

/**
 * @property int $id
 * @property int $appointment_id
 * @property int $user_id
 * @property string $role
 * @property string $created_at
 * @property string|null $deleted_at
 *
 * @property Appointment $appointment
 * @property User $user
 */
class AppointmentUser extends \yii\db\ActiveRecord
{

    use traits\SoftDeleteTrait;


    public static function tableName(): string
    {
        return 'appointments_users';
    }


    public function rules(): array
    {
        return [
            [['appointment_id', 'user_id'], 'required'],
            [['appointment_id', 'user_id'], 'integer'],
            [['role'], 'string'],
            [['created_at', 'deleted_at'], 'safe'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['appointment_id'], 'exist', 'skipOnError' => true, 'targetClass' => Appointment::class, 'targetAttribute' => ['appointment_id' => 'id']],
        ];
    }


    public function attributeLabels(): array
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'appointment_id' => Yii::t('app', 'Appointment ID'),
            'user_id' => Yii::t('app', 'User ID'),
            'role' => Yii::t('app', 'Role'),
            'created_at' => Yii::t('app', 'Created At'),
            'deleted_at' => Yii::t('app', 'Deleted At'),
        ];
    }


    public function getAppointment(): \yii\db\ActiveQuery|AppointmentQuery
    {
        return $this->hasOne(Appointment::class, ['id' => 'appointment_id'])->inverseOf('appointmentUsers');
    }


    public function getUser(): \yii\db\ActiveQuery|UserQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id'])->inverseOf('appointmentUsers');
    }


    public static function find(): AppointmentUserQuery
    {
        return new AppointmentUserQuery(get_called_class());
    }
}
