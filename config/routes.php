<?php
/** @var array $params */
$langPattern = implode('|', array_keys($params['supportedLanguages']));
$roles = implode('|', array_keys($params['roles']));

$businessActions = 'update|user|resource|rule|service|delete';

return [

    '' => 'site/index',
    "<lang:$langPattern>/?" => 'site/index',
    "<lang:$langPattern>/site/?" => 'site/index',
    "<lang:$langPattern>/<action>" => 'site/<action>',

    'verify/<token>' => 'site/verify',

    "<lang:$langPattern>/site/login/<s>" => 'site/login',

    "<lang:$langPattern>/business/user/<slug>/<role:$roles>" => 'business/user',
    "<lang:$langPattern>/business/<action:$businessActions>/<slug>/<id>" => 'business/<action>',
    "<lang:$langPattern>/business/<action:$businessActions>/<slug>" => 'business/<action>',

    "<lang:$langPattern>/user/add/<slug>/<role:$roles>/<id>" => 'user/add',
    "<lang:$langPattern>/user/add/<slug>/<role:$roles>" => 'user/add',
    "<lang:$langPattern>/user/<action:\w+>/<id>" => 'user/<action>',

    "<lang:$langPattern>/appointment/<action:\w+>/<slug>" => 'appointment/<action>',

    "<lang:$langPattern>/<controller:\w+>/<action:\w+>" => '<controller>/<action>',

    '<path:.*>' => 'site/reroute',

];