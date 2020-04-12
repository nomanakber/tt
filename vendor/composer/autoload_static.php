<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit2c6305ca342dcede2bac9e563894c5dc
{
    public static $files = array (
        '6b5b87bda6fedcf6ef8605219c8b67f2' => __DIR__ . '/..' . '/mageplaza/module-core/registration.php',
        'd19ea7b6e4fc5e1d872bc1164eb16486' => __DIR__ . '/../..' . '/registration.php',
    );

    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'Mageplaza\\Core\\' => 15,
        ),
        'B' => 
        array (
            'TcsCourier\\Shipping\\' => 16,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Mageplaza\\Core\\' => 
        array (
            0 => __DIR__ . '/..' . '/mageplaza/module-core',
        ),
        'TcsCourier\\Shipping\\' => 
        array (
            0 => __DIR__ . '/../..' . '/',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit2c6305ca342dcede2bac9e563894c5dc::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit2c6305ca342dcede2bac9e563894c5dc::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}