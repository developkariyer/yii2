<?php

namespace app\models;

use Random\RandomException;
use Yii;
use yii\base\Exception;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\web\IdentityInterface;
use app\models\queries\UserQuery;
use app\components\LogBehavior;


/**
 * @property int         $id
 * @property int         $user_id
 * @property string      $type         // enum: email, gsm, token, authkey
 * @property string      $secret       // password for email, smspin for gsm, token for token and authkey (all hashed)
 * @property string|null $expires      // expiration time in DATETIME format
 * @property string|null $extra
 * @property int         $force_reset
 * @property string|null $last_used_at
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $authKey
 * @property User        $user
 */
class Authidentity extends ActiveRecord implements IdentityInterface
{
    const AUTHTYPE_PASSWORD = 'password';
    const AUTHTYPE_EMAIL_TOKEN = 'email_token';
    const AUTHTYPE_SMS_OTP = 'sms_otp';
    const AUTHTYPE_GOOGLE = 'google';
    const AUTHTYPE_FACEBOOK = 'facebook';
    const AUTHTYPE_TWITTER = 'twitter';
    const AUTHTYPE_REMEMBERME = 'rememberme';

    public static function tableName(): string
    {
        return 'authidentities';
    }

    public function behaviors(): array
    {
        return [
            'logBehavior' => [
                'class' => LogBehavior::class,
                'eventTypeCreate' => LogBase::EVENT_USER_AUTH_ADDED,
                'eventTypeUpdate' => null,
                'eventTypeDelete' => null,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['user_id', 'type', 'secret'], 'required'],
            [['user_id', 'force_reset'], 'integer'],
            [['type', 'extra'], 'string'],
            [['expires', 'last_used_at', 'created_at', 'updated_at'], 'safe'],
            [['secret'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
        ];
    }

    public function getUser(): ActiveQuery|UserQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id'])->inverseOf('authidentities');
    }

    public static function findIdentityByAccessToken($token, $type = null): Authidentity|null
    {   // Function required by Yii2 User interface
        $candidates = Authidentity::find()
            ->where(['type' => self::AUTHTYPE_REMEMBERME])
            ->andWhere(['>', 'expires', new Expression('NOW()')])
            ->all();
        foreach ($candidates as $candidate) {
            if (Yii::$app->security->validatePassword($token, $candidate->secret)) {
                return $candidate;
            }
        }
        return null;
    }

    public static function findIdentityByEmailToken($token): Authidentity|null
    {
        $candidates = Authidentity::find()
            ->where(['type' => self::AUTHTYPE_EMAIL_TOKEN])
            ->andWhere(['>', 'expires', new Expression('NOW()')])
            ->all();
        foreach ($candidates as $candidate) {
            if (Yii::$app->security->validatePassword($token, $candidate->secret)) {
                return $candidate;
            }
        }
        return null;
    }

    public static function findIdentityByEmail($email): Authidentity|null
    {
        if ($user = User::find()->where(['email' => $email])->one()) {
            return $user->getAuthIdentities()
                ->where(['type' => self::AUTHTYPE_PASSWORD])
//                ->andWhere(['>', 'expires', new \yii\db\Expression('NOW()')])
                ->one();
        }
        return null;
    }

    public static function findIdentityByGsm($gsm): Authidentity|null
    {
        $user = User::find()->where(['gsm' => $gsm])->one();
        return $user?->getAuthIdentities()
            ->where(['type' => self::AUTHTYPE_SMS_OTP])
            ->andWhere(['>', 'expires', new Expression('NOW()')])
            ->one();
    }

    public static function findIdentity($id): Authidentity|null
    {   // Function required by Yii2 User interface
        return static::find()
            ->where(['id' => $id])
            ->one();
    }

    public function getId(): int|null
    {   // Function required by Yii2 User interface
        return $this->getPrimaryKey();
    }

    public function getAuthKey(): string|null
    {   // Function required by Yii2 User interface
        return $this->authKey;
    }

    public function validateAuthKey($authKey): bool
    {   // Function required by Yii2 User interface
        return $this->getAuthKey() === $authKey;
    }

    public function validatePassword($password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->secret);
    }

    /**
     * @throws Exception
     */
    public static function generateEmailToken($email): string|null
    {
        if ($user = User::find()->where(['email' => $email])->active()->one()) {
            if (self::getActiveTokenCount($user->id, self::AUTHTYPE_EMAIL_TOKEN) > 2) {
                return true;
            }
            $token = Yii::$app->security->generateRandomString();
            $hash = Yii::$app->security->generatePasswordHash($token);
            $authIdentity = new self([
                'user_id' => $user->id,
                'type' => self::AUTHTYPE_EMAIL_TOKEN,
                'secret' => $hash,
                'authKey' => Yii::$app->security->generateRandomString(),
                'expires' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            ]);
            if ($authIdentity->save(false)) {
                return $token;
            }
        }
        return 'X';
    }

    /**
     * @throws Exception
     * @throws RandomException
     */
    public static function generateSmsPin($gsm): string|bool
    {
        if ($user = User::find()->where(['gsm' => $gsm])->active()->one()) {
            if (self::getActiveTokenCount($user->id, self::AUTHTYPE_SMS_OTP)) {
                return true;
            }
            $code = sprintf('%06d', random_int(0, 999999));
            $hash = Yii::$app->security->generatePasswordHash($code);
            $authIdentity = new self([
                'user_id' => $user->id,
                'type' => self::AUTHTYPE_SMS_OTP,
                'secret' => $hash,
                'authKey' => Yii::$app->security->generateRandomString(),
                'expires' => date('Y-m-d H:i:s', strtotime('+3 minutes')),
            ]);
            if ($authIdentity->save()) {
                return $code;
            }
        }
        return false;
    }

    public static function getActiveTokenCount($user_id, $type): int
    {
        $count = static::find()
            ->where(['type' => $type])
            ->andWhere(['>', 'expires', new Expression('NOW()')])
            ->count();
        return (int) $count;
    }

    public function getUsername()
    {
        return $this->user ? $this->user->fullname : null;
    }
}
