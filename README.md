wp-cli/add_source_path
======================

Provides a function that adds the path information of the calling file of lazy loading to the translation template file (POT file) corresponding to the translation function.



Quick links: [Using](#using) | [Installing](#installing) | [Support](#support)

## When to use
When using a module that is not always used, delaying loading it until it is time to use it is called lazy loading, and React provides a function called `React.lazy`.
When you use this, the transpiled js file is divided into 1) a file that is loaded with a delay, and 2) a file that calls it. When the part using the translation function is stored in ①, `wp i18n make-pot`, the WP-CLI command for creating a POT file, outputs only the path information of ① to the POT file, and the path of ② No information is output.
② If you create a Javascript translation file based on a POT file that does not have file path information, you will not be able to render the translated result. Therefore, the path information in ② must be recorded in the POT file.
This WP-CLI command provides the ability to add ② file path information to the file generated with `wp i18n make-pot`.

## Using
~~~
wp add_source_path [text domain]
~~~

Among the plugins installed in WordPress, select the js file that uses the `React.lazy` function from the build folder of the plugin that has the text domain information [text domain] as the plugin information, and select the js file that uses the `React.lazy` function. Based on the lazy loading information read from , write the path information of the selected js file to the POT file and overwrite it.

## Installing
This package is provided as a Composer project, so install it using the Composer installation procedure.

1. Clone GitHub repository:
Navigate to the folder where WordPress is installed, open a command prompt or terminal, and clone the repository using Git commands as follows:
```
git clone https://github.com/itmaroon/add_lazy_pot_file.git
```

2. Installing Composer dependencies
Change to the directory of the cloned repository.
```
cd ./add_lazy_pot_file
```
Install dependencies.
```
composer install
```

## Notes
1. This WP-CLI command assumes support for the translation system of the plugin that provides the Gutenberg block component.
2. `Text Domain:` must be set in the plugin information (comment at the beginning of the PHP file that is the entry point of the plugin).
3. A POT file must be created in advance using `wp i18n make-pot`. This file name is [text domain].pot and must be located in one of the folders within the plugin's root folder.

## Support
If you have any questions, please contact us at the following email address.
master@itmaroon.net
