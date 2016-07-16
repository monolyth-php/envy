# Monolyth/Envy
Flexible environment handler for PHP projects (including unit tests!)

When writing PHP projects that are more complicated than a two-page site, you're
going to run into some real life problems:

- Am I in development or production?

    E.g., during development a mailer should not actually send out mails to
    users, but instead proxy to the developer.

- What are the correct database credentials for this environment?

    Ideally, the same as production, but alas 'tis not an ideal world we live
    in.

- What are safe fallbacks for e.q. `$_SERVER` values when running from the
  command line?

    Since things like `$_SERVER['SERVER_NAME']` will also be different in
    development than they are in production.

- During testing, what should we use now?

    Obviously we don't want to test against a production database.

- How can I test a multi-project setup using one set of unit tests?

    PHPUnit and DBUnit are great, but they kinda assume a single database. For
    complex projects, this is often simply not the case. Also, multiple sites
    might be related and thus share 99% percent of unit tests. Since copy/paste
    is evil, it would be nice to have a way to automatically decide which
    database(s) a set of tests needs to run against.

## Installation

### Composer (recommended)
```bash
$ composer require monolyth/envy
```

### Manual
1. Download or clone the repository;
2. Add `/path/to/envy/src` for the namespace `Monolyth\\Envy\\` to your
   autoloader.

## Usage
In a central place in your application (e.g. a bootstrapper that's always
included) instantiate a "global" `Environment` object:

```php
<?php

use Monolyth\Envy\Environment;

$env = new Environment('/path/to/my/configurations', function () {
    return 'production';
});
```

The first parameter is the location of the configuration file that defines your
environments and their differences. Envy supports the following formats out of
the box:

- JSON (`.json`)
- YAML (`.yml`)
- PHP .ini-style (`.ini`)
- XML (`.xml`)
- PHP array or simple object (`.php`)

The top-level key is simply the name of the environment (define as many as you
need), every second level are the settings. E.g., for JSON:

```json
{
    "production": {
        "root-path": "/var/www"
    },
    "development": {
        "root-path": "/home/monomelodies/my-project"
    }
}
```

The second argument is a callable that should return the name of the environment
you are currently operating in. How you decide that is up to you...

Optionally the `Environment` instance gets passed as an argument there, exposing
some utility functions.

Once you're setup, simply request the values you need:

```php
<?php

// $env configuration as described above...

include $env->root_path.'/some-file.php';
```

You can also set values after creation, e.g. the current language:

```php
<?php

// Assuming $user is a logged in user...
$env->language = $user->language;
```

Note that this can also be done depending on the environment you determined in
the constructor callable:

```php
<?php

use Monolyth\Envy\Environment;

$env = new Environment('/path/to/config', function ($env) {
    if (check_for_cli()) {
        $env->someParameter = 'some value';
        return 'cli';
    } else {
        $env->someParameter = 'something else';
        return 'web';
    }
});
```

The callable used on construction may return multiple environments in an array.
The found settings are then merged together (with the last defined taking
precedence). This is extremely useful for keeping your config DRY and grouping
settings depending on environment, e.g.:

```json
{
    "web": {
        "serverName": "example.com"
    },
    "cli": {
        "serverName": "localhost"
    },
    "production": {
        "root-path": "/var/www"
    },
    "development": {
        "root-path": "/home/monomelodies/my-project"
    }
}
```

If the constructor callable returns `['web', 'production']` the Environment
object will have the properties `serverName` and `root_path` set properly.

## As a singleton
Global objects are evil, and some people don't use dependency injection. That's
why Envy can also be called like a singleton:

```php
<?php

use Envy\Environment;

Environment::setConfig('/path/to/config');
Environment::setEnvironment(function ($env) {
    // ...
});
$env = Environment::instance();
```

This is equivalent to the examples above.

## Placeholders
Envy supports simple placeholders in environment variables:

```json
{
    "test": {
        "somevar": "my name is <% user %>"
    }
}
```

```php
<?php

$env = new Environment('/path/to/config', function ($env) {
    $env->user = get_current_user();
});

```

These replacements must be defined at the root level of your environment
configuration to work.

## Environment-conditional variables
You can specify variables that are only valid for combinations of environments,
e.g. `dev+programmer_bob` versus `dev+programmer-alice`. Just use the plus sign
in the `environment` key. These combined keys take precendence of single keys.

> For XML-based config files (where `+` would be an illegal character), use the
> `-AND-` (in caps, this is important!) delimiter instead, e.g.
> `<dev-AND-programmer-bob>...</dev-AND-programmer-bob>`.

