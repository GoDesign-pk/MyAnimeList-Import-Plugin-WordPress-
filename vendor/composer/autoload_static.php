<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitd4bbd996fe0d0bf180a1e8a406a0ee0a
{
    public static $prefixLengthsPsr4 = array (
        'a' => 
        array (
            'aalfiann\\' => 9,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'aalfiann\\' => 
        array (
            0 => __DIR__ . '/..' . '/aalfiann/myanimelist-api-php/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitd4bbd996fe0d0bf180a1e8a406a0ee0a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitd4bbd996fe0d0bf180a1e8a406a0ee0a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitd4bbd996fe0d0bf180a1e8a406a0ee0a::$classMap;

        }, null, ClassLoader::class);
    }
}
