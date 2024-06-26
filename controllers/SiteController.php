<?php

namespace app\controllers;

use app\components\ACL;
use Random\RandomException;
use Yii;
use yii\base\InvalidConfigException;
use yii\bootstrap5\ActiveForm;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\Response;
use app\components\MyUrl;
use app\components\LanguageBehavior;
use app\models\Authidentity;
use app\models\form\LoginForm;
use app\models\Login;
use yii\httpclient\Client;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
            'languageBehavior' => [
                'class' => LanguageBehavior::class,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Fallback route function, tries to guess what user intents
     * @param mixed $path
     * @return Yii\web\Response
     * @throws InvalidConfigException
     */
    public function actionReroute(mixed $path): Response
    {
        $pathInfo = Yii::$app->request->getPathInfo();
        $segments = explode('/', $pathInfo);
        $controllerList = ['business', 'user', 'site']; //manually fill this array with controllers
        if (isset($segments[0]) && in_array($segments[0], $controllerList)) {
            return $this->redirect(MyUrl::to([implode('/', $segments)]));
        }
        if (isset($segments[1]) && in_array($segments[1], $controllerList)) {
            unset($segments[0]);
            return $this->redirect(MyUrl::to([implode('/', $segments)]));
        }
        return $this->redirect(MyUrl::to(['site/index']));
    }

    /**
     * Generate token for e-mail and send it to user
     * @return Yii\web\Response
     * @throws \yii\base\Exception
     */
    public function actionVerifymyemail(): Response
    {
        if (!ACL::isGuest()) {
            $email_token = Authidentity::generateEmailToken(Yii::$app->user->identity->user->email);
            if ($email_token !== false) {
                Yii::$app->session->setFlash('info', "***********".Html::a($email_token, ['verify/'.$email_token], ['class' => 'alert-link'])."************");
                // EXTERNAL send e-mail via external api call, to be implemented
                Yii::$app->session->setFlash('error', Yii::t('app','Check your e-mail for a login link.'));
            } else {
                Yii::$app->session->setFlash('error', Yii::t('app','Unable to e-mail a login link.'));
            }
            return $this->goBack();
        } else {
            return $this->redirect(['login', 's' => 8]);
        }
    }

    /**
     * Do the actual login with e-mail token
     * @param mixed $token
     * @return Yii\web\Response
     */
    public function actionVerify(mixed $token): Response
    {
        // TODO: log will be implemented
        $authidentity = Authidentity::findIdentityByEmailToken($token);
        if ($authidentity) {
            if (!ACL::isUserLoggedIn($authidentity->user->id)) {
                Yii::$app->session->setFlash('error', Yii::t('app', 'You are already logged in with a different user. Please logout first.'));
                return $this->goHome();
            }
            if (!$authidentity->user->emailverified) {
                $authidentity->user->emailverified = 1;
                $authidentity->user->save(false, ['emailverified']);
            }
            if (!ACL::isGuest()) {
                Yii::$app->session->setFlash('info', Yii::t('app','E-mail verification successful.'));
                return $this->goHome();
            }
            if (Yii::$app->user->login($authidentity, 3600 * 24 * 30)) {
                $authidentity->expires = date('Y-m-d H:i:s', strtotime('+10 seconds'));
                $authidentity->save(false, ['expires']);
                Yii::$app->session->setFlash('info', Yii::t('app','Login and verification successful.'));
                return $this->goHome();
            }
        }
        Yii::$app->session->setFlash('error', Yii::t('app', 'Token invalid or expired.'));
        return $this->redirect(MyURl::to(['site/login/link']));
    }

    /**
     * Verify TC identity number from official government service
     * @return Yii\web\Response
     */
    public function actionVerifytcno(): Response
    {
        if (ACL::isGuest()) {
            return $this->goBack();
        }
        $user = Yii::$app->user->identity->user;
        if (!$user) {
            Yii::$app->session->setFlash('error', Yii::t('app', 'User not found.'));
            return $this->goBack();
        }
        $soapRequest = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
  <soap12:Body>
    <TCKimlikNoDogrula xmlns="http://tckimlik.nvi.gov.tr/WS">
      <TCKimlikNo>$user->tcno</TCKimlikNo>
      <Ad>$user->first_name</Ad>
      <Soyad>$user->last_name</Soyad>
      <DogumYili>$user->dogum_yili</DogumYili>
    </TCKimlikNoDogrula>
  </soap12:Body>
</soap12:Envelope>
XML;

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://tckimlik.nvi.gov.tr/Service/KPSPublic.asmx",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $soapRequest,
            CURLOPT_HTTPHEADER => [
                "content-type: application/soap+xml; charset=utf-8",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if (!$err) {
            if (trim(strip_tags($response)) === 'true') {
                $user->tcnoverified = 1;
                $user->save(false);
                Yii::$app->session->setFlash('info', Yii::t('app', 'T.C. Identity Number verified.'));
                return $this->goBack();
            }
        }
        Yii::$app->session->setFlash('error', Yii::t('app', 'Unknown error:').$err);
        return $this->goBack();
    }

    /**
     * @throws \yii\base\Exception
     * @throws RandomException
     */
    public function actionVerifygsm(): Response|array|string
    {
        if (ACL::isGuest()) {
            return $this->goBack();
        }

        $model = new LoginForm();
        $model->scenario = LoginForm::SCENARIO_SMS_VALIDATE;
        $model->gsm = Yii::$app->user->identity->user->gsm;

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate() && $model->login()) {
                Yii::$app->session->setFlash('info', Yii::t('app','Login/verification successful.'));
                return $this->goHome();
            }
        } else {
            $sms_otp = Authidentity::generateSmsPin($model->gsm);
            // EXTERNAL send sms_otp via SMS service API call
            Yii::$app->session->setFlash('info', "*********** $sms_otp ************");
        }

        return $this->render('@app/views/authidentity/sms_validate', ['model' => $model]);
    }

    /**
     * Default homepage, which will redirect or render user-specific homepage after setting common messages
     * @return string|Yii\web\Response
     */
    public function actionIndex(): Response|string
    {
        if (Yii::$app->session->has('slug')) {
            Yii::$app->session->set('slug', null);
            Yii::$app->session->remove('slug');
        }
        if (!ACL::isGuest()) {
            $authidentity = Yii::$app->user->identity; 

            $messages = [];
            if (!$authidentity->user->tcnoverified) {   
                $url = Html::a(Yii::t('app', 'Please update your profile.'), MyUrl::to(['user/update']), ['class' => 'alert-link']);
                $url2 = Html::a(Yii::t('app', 'Click here to verify.'), MyUrl::to(['site/verifytcno']), ['class' => 'alert-link']);
                $messages[] = Yii::t('app', 'Your T.C. No is not verified.')." $url - $url2";
            }
            if (!$authidentity->user->gsmverified) {
                $url = Html::a(Yii::t('app', 'Please verify your GSM number.'), MyUrl::to(['site/verifygsm']), ['class' => 'alert-link']);
                $messages[] = Yii::t('app', "Your GSM number is not verified.")." $url";
            }
            if (!$authidentity->user->emailverified) {
                $url = Html::a(Yii::t('app', 'Please verify your e-mail.'), MyUrl::to(['site/verifymyemail']), ['class' => 'alert-link']);
                $messages[] = Yii::t('app', "Your e-mail address is not verified.")." $url";
            }
            if (count($messages)) Yii::$app->session->setFlash('warning', $messages);
        } else {
            return $this->redirect(MyUrl::to(['site/login']));
        }
            
        if (ACL::isSuperAdmin()) {
            return $this->redirect(MyUrl::to(['site/superadmin']));
        } else {
            return $this->render('index');
        }
    }

    /**
     * @throws \yii\base\Exception
     * @throws RandomException
     */
    public function actionLogin($s = null): Response|array|string
    {
        if (!ACL::isGuest()) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if (Yii::$app->request->isPost) {
            $model->scenario = Yii::$app->request->post('action');
        } else {
            $model->scenario = $s ?? LoginForm::SCENARIO_PASSWORD;
        }

        $allowed_scenarios = array_keys($model->scenarios());
        if (!in_array($model->scenario, $allowed_scenarios)) {
            $model->scenario = LoginForm::SCENARIO_PASSWORD;
        }

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {
                switch ($model->scenario) {
                    case LoginForm::SCENARIO_PASSWORD:
                    case LoginForm::SCENARIO_SMS_VALIDATE:
                        if ($model->login()) {
                            Yii::$app->session->setFlash('info', Yii::t('app','Login successful.'));
                            return $this->goHome();
                        } else {
                            if ($model->scenario === LoginForm::SCENARIO_SMS_VALIDATE) {
                                $model->addError('smsotp', Yii::t('app', 'Unable to authenticate'));
                            } else {
                                $model->addError('password', Yii::t('app', 'Unable to authenticate'));
                            }
                        }
                        break;
                    case LoginForm::SCENARIO_SMS_REQUEST:
                        $sms_otp = Authidentity::generateSmsPin($model->gsm);
                        Yii::$app->session->setFlash('info', "*********** $sms_otp ************");
                        if ($sms_otp !== false) {
                            if ($sms_otp !==true ) { sleep(0); /* send sms via external api call, to be implemented */ }
                            $model->scenario=LoginForm::SCENARIO_SMS_VALIDATE;
                        } else {
                            $model->addError('gsm', Yii::t('app', 'Unable to send an SMS'));
                        }
                        break;
                    case LoginForm::SCENARIO_LINK:
                        $email_token = Authidentity::generateEmailToken($model->emaillink);
                        Yii::$app->session->setFlash('info', "***********".Html::a($email_token, ['verify/'.$email_token], ['class' => 'alert-link'])."************");
                        if ($email_token !== false) {
                            Yii::$app->session->setFlash('warning', Yii::t('app','Check your e-mail for a login link.'));
                            return $this->goHome();
                        } else {
                            $model->addError('emaillink', Yii::t('app', 'Unable to e-mail a login link.'));
                        }
                        break;
                    case LoginForm::SCENARIO_OTHER:
                        break;
                }
            }
        } else {
            if ($model->scenario === LoginForm::SCENARIO_SMS_VALIDATE) {
                $model->scenario = LoginForm::SCENARIO_SMS_REQUEST;
            }
        }
        return $this->render('@app/views/authidentity/login', ['model' => $model]);
    }

    /**
     * Logout action.
     * @return Response
     */
    public function actionLogout(): Response
    {
        if (!ACL::isGuest()) {
            Login::log('Logout', '', 1);
            Yii::$app->user->logout();
            Yii::$app->session->setFlash('warning', Yii::t('app','Log out successful. See you soon.'));
        }
        return $this->goHome();
    }

    private function gitHubCommits(): array
    {
        $commitCheck = Yii::$app->cache->get('github_commit_check');
        if ($commitCheck) {
            $commits = Yii::$app->cache->get('github_commits');
            if ($commits) {
                return $commits;
            }
        }

        $client = new Client();
        try {
            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl('https://api.github.com/repos/developkariyer/yii2/commits')
                ->addHeaders(['user-agent' => 'Yii2-GitHub-API'])
                ->send();
    
            if ($response->isOk) {
                $commits = $response->data;
                Yii::$app->cache->set('github_commits', $commits, 86400);
                Yii::$app->cache->set('github_commit_check', true, 300);
            } else {
                $commits = Yii::$app->cache->get('github_commits');
            }
        } catch (\Exception $e) {
            $commits = Yii::$app->cache->get('github_commits');
        }
        return $commits;
    }

    /**
     * Displays contact page.
     */
    public function actionSuperadmin(): Response|string
    {
        if (!ACL::isSuperAdmin()) {
            return $this->goHome();
        }

        return $this->render('superadmin', ['commits' => $this->gitHubCommits()]);
    }

}
