<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit61ac37121bc927c3837fa0a0819ce1b8
{
    public static $files = array (
        'ebd5bf7eab28e7ce2c656939229f46d8' => __DIR__ . '/../..' . '/includes/helper.php',
        '9789a0858adbe468dcc1959d4b32214e' => __DIR__ . '/../..' . '/includes/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'ThriveDesk\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'ThriveDesk\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
            1 => __DIR__ . '/../..' . '/src/Abstracts',
            2 => __DIR__ . '/../..' . '/src/Conversations',
            3 => __DIR__ . '/../..' . '/Hooks',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'ThriveDesk\\Admin' => __DIR__ . '/../..' . '/src/Admin.php',
        'ThriveDesk\\Api' => __DIR__ . '/../..' . '/src/Api.php',
        'ThriveDesk\\Api\\ApiResponse' => __DIR__ . '/../..' . '/src/Api/ApiResponse.php',
        'ThriveDesk\\Conversations\\Conversation' => __DIR__ . '/../..' . '/src/Conversations/Conversation.php',
        'ThriveDesk\\FluentCrmHooks' => __DIR__ . '/../..' . '/Hooks/FluentCrmHooks.php',
        'ThriveDesk\\FormProviders\\FormProviderHelper' => __DIR__ . '/../..' . '/src/FormProviders/FormProviderHelper.php',
        'ThriveDesk\\Plugin' => __DIR__ . '/../..' . '/src/Abstracts/Plugin.php',
        'ThriveDesk\\Plugins\\Autonami' => __DIR__ . '/../..' . '/src/Plugins/Autonami.php',
        'ThriveDesk\\Plugins\\EDD' => __DIR__ . '/../..' . '/src/Plugins/EDD.php',
        'ThriveDesk\\Plugins\\FluentCRM' => __DIR__ . '/../..' . '/src/Plugins/FluentCRM.php',
        'ThriveDesk\\Plugins\\SmartPay' => __DIR__ . '/../..' . '/src/Plugins/SmartPay.php',
        'ThriveDesk\\Plugins\\WPPostSync' => __DIR__ . '/../..' . '/src/Plugins/WPPostSync.php',
        'ThriveDesk\\Plugins\\WooCommerce' => __DIR__ . '/../..' . '/src/Plugins/WooCommerce.php',
        'ThriveDesk\\RestRoute' => __DIR__ . '/../..' . '/src/RestRoute.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit61ac37121bc927c3837fa0a0819ce1b8::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit61ac37121bc927c3837fa0a0819ce1b8::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit61ac37121bc927c3837fa0a0819ce1b8::$classMap;

        }, null, ClassLoader::class);
    }
}
