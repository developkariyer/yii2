<?php

namespace app\models;

use Yii;
use yii\base\Model;

class LoginForm extends Model
{
    const SCENARIO_PASSWORD = '1';
    const SCENARIO_SMS_REQUEST = '2';
    const SCENARIO_SMS_VALIDATE = '4';
    const SCENARIO_EMAIL_LINK = '8';
    const SCENARIO_OTHER = '16';

    public $password;
    public $gsm;
    public $email;
    public $emaillink;
    public $rememberMe = true;
    public $smsform = false;
    public $smsotp;

    private $_user = null;

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_PASSWORD] = ['email', 'password'];
        $scenarios[self::SCENARIO_SMS_REQUEST] = ['gsm'];
        $scenarios[self::SCENARIO_SMS_VALIDATE] = ['gsm', 'smsotp'];
        $scenarios[self::SCENARIO_EMAIL_LINK] = ['emaillink'];
        $scenarios[self::SCENARIO_OTHER] = [];
        return $scenarios;
    }

    public function attributeLabels(): array
    {
        return [
            'email' => Yii::t('app', 'E-mail'),
            'gsm' => Yii::t('app', 'GSM Number'),
            'emaillink' => Yii::t('app', 'E-mail'),
            'sms' => Yii::t('app', 'SMS'),
            'password' => Yii::t('app', 'Password'),
            'smsotp' => Yii::t('app', 'OTP'),
        ];
    }

    public function rules(): array
    {
        return [
            [['email', 'password'], 'required', 'on' => self::SCENARIO_PASSWORD],
            [['gsm'], 'required', 'on' => [self::SCENARIO_SMS_REQUEST, self::SCENARIO_SMS_VALIDATE]],
            [['smsotp'], 'required', 'on' => self::SCENARIO_SMS_VALIDATE],
            [['emaillink'], 'required', 'on' => self::SCENARIO_EMAIL_LINK],
            [['email', 'emaillink'], 'email'],
        ];
    }

    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();

            if (!$user || !$user->validatePassword($this->password)) {
                $this->addError($attribute, Yii::t('app', 'E-mail not registered or invalid password.'));
            }
        }
    }

    // Define your login logic for each scenario in the login() method or create separate methods for each.
    public function login(): bool
    {
        if ($this->validate()) {
            switch ($this->scenario) {
                case self::SCENARIO_PASSWORD:
                    $authidentity = Authidentity::findIdentityByEmail($this->email);
                    if ($authidentity && $authidentity->validatePassword($this->password)) {
                        return Yii::$app->user->login($authidentity, 3600 * 24 * 30);
                    }
                    $this->addError('password', 'Invalid e-mail or password.');
                    break;
                case self::SCENARIO_SMS_VALIDATE:
                    $authidentity = Authidentity::findIdentityByGsm($this->gsm);
                    if ($authidentity && $authidentity->validatePassword($this->smsotp)) {
                        $authidentity->expires = date('Y-m-d H:i:s', strtotime('+3 seconds'));
                        $authidentity->save(false);
                        $user = $authidentity->user;
                        $user->gsmverified = true;
                        $user->save(false);
                        return Yii::$app->user->login($authidentity, 3600 * 24 * 30);
                    }
                    $this->addError('smsotp', 'Invalid OTP or GSM number.');
                    break;
                case self::SCENARIO_EMAIL_LINK:
                case self::SCENARIO_SMS_REQUEST:
                    return false;
            }
        }
        return false;
    }

    public function getUser()
    {
        if ($this->_user === null) {
            // Assuming findIdentityByEmail can handle null email gracefully
            $this->_user = AuthIdentity::findIdentityByEmail($this->email);
        }
        return $this->_user;
    }
}
