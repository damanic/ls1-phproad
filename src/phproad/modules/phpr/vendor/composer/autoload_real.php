<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInitb3937806b80ed206f0589df890bb3cb4
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            include __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        include __DIR__ . '/platform_check.php';

        spl_autoload_register(
            array('ComposerAutoloaderInitb3937806b80ed206f0589df890bb3cb4', 'loadClassLoader'), true,
            true
        );
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(\dirname(__FILE__)));
        spl_autoload_unregister(array('ComposerAutoloaderInitb3937806b80ed206f0589df890bb3cb4', 'loadClassLoader'));

        $useStaticLoader = PHP_VERSION_ID >= 50600 && !defined('HHVM_VERSION') && (!function_exists('zend_loader_file_encoded') || !zend_loader_file_encoded());
        if ($useStaticLoader) {
            include __DIR__ . '/autoload_static.php';

            call_user_func(\Composer\Autoload\ComposerStaticInitb3937806b80ed206f0589df890bb3cb4::getInitializer($loader));
        } else {
            $map = include __DIR__ . '/autoload_namespaces.php';
            foreach ($map as $namespace => $path) {
                $loader->set($namespace, $path);
            }

            $map = include __DIR__ . '/autoload_psr4.php';
            foreach ($map as $namespace => $path) {
                $loader->setPsr4($namespace, $path);
            }

            $classMap = include __DIR__ . '/autoload_classmap.php';
            if ($classMap) {
                $loader->addClassMap($classMap);
            }
        }

        $loader->register(true);

        if ($useStaticLoader) {
            $includeFiles = Composer\Autoload\ComposerStaticInitb3937806b80ed206f0589df890bb3cb4::$files;
        } else {
            $includeFiles = include __DIR__ . '/autoload_files.php';
        }
        foreach ($includeFiles as $fileIdentifier => $file) {
            composerRequireb3937806b80ed206f0589df890bb3cb4($fileIdentifier, $file);
        }

        return $loader;
    }
}

function composerRequireb3937806b80ed206f0589df890bb3cb4($fileIdentifier, $file)
{
    if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
        include $file;

        $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
    }
}