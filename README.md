Settings.php
============

This package provides a simple way to load and parse a settings file. 

Suppose you have a file that looks something like this:

    # A foo entry.
    foo = bar

        # These are sub-properties of foo.bar:
        biz = baz
        boo = beep

    # Another foo entry.
    foo = bang
 
        # These are subproperties of foo.bang
        biz = bizzaz
        boo = beepaz

    # Comments start with the hash character (#).
    fi = fum

This file has a series of `key = value` settings, with nesting being accomplished by indentation (4 spaces per level). 

The `Settings.php` package can take that file, and convert it into an array that looks like this:

    array(
        'foo' => array(
            'bar' => array(
                'biz' => 'baz',
                'boo' => 'beep',
            ),
            'bang' => array(
                'biz' => 'bizzaz',
                'boo' => 'beepaz',
            ),
        ),
        'fi' => 'fum',
    );


Usage
-----

Instantiate the class:

    $settings = new Settings();

Try to load a file called 'settings.local', or if that doesn't work, try to load another file called 'settings.default':

    $settings->load('settings.local', 'settings.default');

Get all settings as one big array:

    $all_settings = $settings->all();

Get a specific setting, e.g., foo.bang.biz from above:

    $foo = $settings->get('foo', 'bang', 'biz');

File format
-----------

This `Settings.php` module only knows how to load/parse files that look like the above example. That is to say:

* Comments begin with a hash character (#).
* Any non-commented line must be in the format `key = value`.
* Nesting is accomplished by indentation.
* Indentation comes in blocks of 4-spaces.

If any lines in the file aren't indented by some factor of 4 spaces, it will throw a `RuntimeException`.


Loading files
-------------

To load a file, use the `Settings::load()` method. You can pass any number of files as parameters to this method: 

    $settings->load('settings.local', 'settings.default', 'file3.txt');

It will try to load the first one, and if that fails, it will then try the second one, and if that fails, it will then try the third. When it successfully loads a file, it will stop and just parse that one..

If none of the files can be loaded, it will throw a `RuntimeException`. 


Getting settings data
---------------------

To retrieve a setting's value, use the `Settings::get()` method. You can pass any number of keys as parameters to this method:

    $settings->get('foo', 'bang', 'biz');

It will try to look for a key named 'foo', and then it will try to find a child of that called 'bang', and then it will try to find a child of that called 'biz' (in PHP, it would try to find this: `$settings['foo']['bang']['biz']`). 

If any key does not exist, it will throw a `RuntimeException`.