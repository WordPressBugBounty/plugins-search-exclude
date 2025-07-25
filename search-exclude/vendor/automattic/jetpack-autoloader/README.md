A custom autoloader for Composer
=====================================

This is a custom autoloader generator that uses a classmap to always load the latest version of a class.

The problem this autoloader is trying to solve is conflicts that arise when two or more plugins use the same package, but one of the plugins uses an older version of said package.

This is solved by keeping an in memory map of all the different classes that can be loaded, and updating the map with the path to the latest version of the package for the autoloader to find when we instantiate the class.

It diverges from the default Composer autoloader setup in the following ways:

* It creates `jetpack_autoload_classmap.php` and `jetpack_autoload_filemap.php` files in the `vendor/composer` directory.
* This file includes the version numbers from each package that is used. 
* The autoloader will only load the latest version of the package no matter what plugin loads the package. This behavior is guaranteed only when every plugin that uses the package uses this autoloader. If any plugin that requires the package uses a different autoloader, this autoloader may not be able to control which version of the package is loaded.

Usage
-----

In your project's `composer.json`, add the following lines:

```json
{
    "require": {
        "automattic/jetpack-autoloader": "^2"
    }
}
```

Your project must use the default composer vendor directory, `vendor`.

After the next update/install, you will have a `vendor/autoload_packages.php` file.
Load the file in your plugin via main plugin file.

In the main plugin you will also need to include the files like this.
```php
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload_packages.php';
```

Working with Development Versions of Packages
-----

The autoloader will attempt to use the package with the latest semantic version.

During development, you can force the autoloader to use development package versions by setting the `JETPACK_AUTOLOAD_DEV` constant to true. When `JETPACK_AUTOLOAD_DEV` is true, the autoloader will prefer the following versions over semantic versions:
  - `9999999-dev`
  - Versions with a `dev-` prefix.


Autoloader Limitations and Caveats
-----

### Plugin Updates

When moving a package class file, renaming a package class file, or changing a package class namespace, make sure that the class will not be loaded after a plugin update. 

The autoloader builds the in memory classmap as soon as the autoloader is loaded. The package class file paths in the map are not updated after a plugin update. If a plugins's package class files are moved during a plugin update and a moved file is autoloaded after the update, an error will occur.

### Moving classes to a different package

Jetpack Autoloader determines the hierarchy of class versions by package version numbers. It can cause problems if a class is moved to a newer package with a lower version number, it will get overshadowed by the old package.

For instance, if your newer version of a class comes from a new package versioned 0.1.0, and the older version comes from a different package with a greater version number 2.0.1, the newer class will not get loaded.

### Jetpack Autoloader uses transient cache

This is a caveat to be aware of when dealing with issues. The JP Autoloader uses transients to cache a list of available plugins to speed up the lookup process. This can sometimes mask problems that arise when loading code too early. See the [Debugging](#debugging) section for more information on how to detect situations like this.

Debugging
-----

A common cause of confusing errors is when a plugin autoloads classes during the plugin load, before the 'plugins_loaded' hook. If that plugin has an older version of the class, that older version may be loaded before a plugin providing the newer version of the class has a chance to register. Even more confusingly, this will likely be intermittent, only showing up when the autoloader's plugin cache is invalidated. To debug this situation, you can set the `JETPACK_AUTOLOAD_DEBUG_EARLY_LOADS` constant to true.

Another common cause of confusing errors is when a plugin registers its own autoloader at a higher priority than the Jetpack Autoloader, and that autoloader would load packages that should be handled by the Jetpack Autoloader. Setting the `JETPACK_AUTOLOAD_DEBUG_CONFLICTING_LOADERS` constant to true will check for standard Composer autoloaders with such a conflict.


Autoloading Standards
----

All new Jetpack package development should use classmap autoloading, which allows the class and file names to comply with the WordPress Coding Standards.

### Optimized Autoloader

An optimized autoloader is generated when:
 * `composer install` or `composer update` is called with `-o` or `--optimize-autoloader`
 * `composer dump-autoload` is called with `-o` or `--optimize`

PSR-4 and PSR-0 namespaces are converted to classmaps.

### Unoptimized Autoloader

Supports PSR-4 autoloading. PSR-0 namespaces are converted to classmaps.

