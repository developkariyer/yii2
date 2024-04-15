<?php

use app\components\MyUrl;

/* @var yii\web\View $this */
/* @var yii\bootstrap5\ActiveForm $form */
/* @var app\models\LoginForm $model */

$this->title = $model->scenariodesc;
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? $this->title]);

$this->registerCss("
    .nav-tabs .nav-link {
        padding: 3px 8px;
        font-size: 1rem;
    }
");

?>

<div class="site-login">
    <div class="row justify-content-md-center mt-5">
        <div class="col-md-8 col-lg-6">
            <nav>
                <div class="nav nav-tabs nav-fill" id="nav-tab" role="tablist">
                    <a href="<?php echo MyUrl::to(['site/login/'.app\models\LoginForm::SCENARIO_PASSWORD]); ?>" class="nav-link <?= ($model->scenario === app\models\LoginForm::SCENARIO_PASSWORD) ? 'active':'' ?>" role="tab"><?= Yii::t('app', 'Password') ?></a>
                    <a href="<?php echo MyUrl::to(['site/login/'.app\models\LoginForm::SCENARIO_SMS_REQUEST]); ?>" class="nav-link <?= ($model->scenario === app\models\LoginForm::SCENARIO_SMS_REQUEST || $model->scenario === app\models\LoginForm::SCENARIO_SMS_VALIDATE) ? 'active':'' ?>" role="tab"><?= Yii::t('app', 'SMS') ?></a>
                    <a href="<?php echo MyUrl::to(['site/login/'.app\models\LoginForm::SCENARIO_EMAIL_LINK]); ?>" class="nav-link <?= ($model->scenario === app\models\LoginForm::SCENARIO_EMAIL_LINK) ? 'active':'' ?>" role="tab"><?= Yii::t('app', 'Link') ?></a>
                    <a href="<?php echo MyUrl::to(['site/login/'.app\models\LoginForm::SCENARIO_OTHER]); ?>" class="nav-link <?= ($model->scenario === app\models\LoginForm::SCENARIO_OTHER) ? 'active':'' ?>" role="tab"><?= Yii::t('app', 'Other') ?></a>
                    <a href="<?php echo MyUrl::to(['user/register/']); ?>" class="nav-link" role="tab"><?= Yii::t('app', 'New User') ?></a>
                </div>
            </nav>
            <?php switch ($model->scenario) {
                case app\models\LoginForm::SCENARIO_PASSWORD:
                default:
                    echo \app\widgets\Card::widget([
                        'title' => Yii::t('app', 'Login with Password'),
                        'content' => $this->render('_password', ['model' => $model]),
                    ]);
                    break;
                case app\models\LoginForm::SCENARIO_SMS_REQUEST:
                    echo \app\widgets\Card::widget([
                        'title' => Yii::t('app', 'Login with SMS'),
                        'content' => $this->render('_sms_request', ['model' => $model]),
                    ]);
                    break;
                case app\models\LoginForm::SCENARIO_SMS_VALIDATE:
                    echo \app\widgets\Card::widget([
                        'title' => Yii::t('app', 'Login with SMS'),
                        'content' => $this->render('_sms_validate', ['model' => $model]),
                    ]);
                    break;
                case app\models\LoginForm::SCENARIO_EMAIL_LINK:
                    echo \app\widgets\Card::widget([
                        'title' => Yii::t('app', 'Login with Link'),
                        'content' => $this->render('_email_link', ['model' => $model]),
                    ]);
                    break;
                case app\models\LoginForm::SCENARIO_OTHER:
                    echo \app\widgets\Card::widget([
                        'title' => Yii::t('app', 'Other'),
                        'content' => $this->render('_other', ['model' => $model]),
                    ]);
                    break;                        
            } ?>
        </div>
    </div>
</div>

