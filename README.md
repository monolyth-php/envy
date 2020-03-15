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
As of version 0.7, `Envy` works exclusively on `.env` files, since that seems to
be the industry standard. We do still, however, support some extensions.

To construct your environment (somewhere centrally), instantiate an object of
the `Monolyth\Envy\Environment` class. It takes two parameters: the path to your
environment configuration files, and a hash where the keys are environment names
and the values are either booleans or callables returning a boolean. Any
environment that is or resolves to `false` will be skipped. Hence, you could do
something like this:

```php
<?php

use Monolyth\Envy\Environment;

$environment = new Environment(__DIR__, [
    'prod' => $_SERVER['SERVER_NAME'] == 'example.com',
    'dev' => $_SERVER['SERVER_NAME'] == 'local.example.dev',
]);
```

After initial instantiation you can either use dependency injection to access
the environment, or you the static `Environment::instance()` method.

You can define as many environments as you want, since multiple can be valid at
any given time. E.g. both `prod` and `web` or `prod` and `cli`.

Keep in mind that existing keys will be overwritten by subsequent environments
defining the same key.

## Configuration file naming
We're using `.env` format, so configuration files should be called either `.env`
(for "generic" or "global" variables, the environment `''` in other words) or
alternatively `.env.ENVIRONMENT_NAME`. Note that these should _not_ be included
in your VCS! That's the whole idea of `.env` files. The only exception is a
`.env.example` file with dummy values, but which gives other users a
comprehensive list of stuff they would want to set.

## Checking for environments
You'll often want to check for environments. All defined environments will be
available on the `$environment` object as `true`, else `false`. So you can do
something like the following:

```php
<?php

if ($environment->prod) {
    if (!$environment->cli) {
        // ... logic ...
    }
} elseif ($environment->dev) {
    throw new Exception('something went wrong!');
}
```

## Using defined environment variables
Like the environments themselves, variables are available as properties on the
environment object, or will be `false` if undefined.

Usually (not sure if by convention or as an actual requirement...) environment
variables are in UPPERCASE. This is ugly in PHP. Hence, all variables are
lowercased for your convenience:

```
FOO=bar
```

```php
<?php

var_dump($environment->foo); // string "bar"
```

## Custom feature: JSON support
For any value that can be `json_decode`d, its actual decoded value is used
instead.

## Custom feature: underscore object expansion
Okay, that's a crappy name. What this means is that for any variable names
containing underscores, they are actually treated as "namespaces" and stored
under sub-environments. Consider the following:

```
DATABASE_NAME=foo
DATABASE_VENDOR=pgsql
DATABASE_USER=user
DATABASE_PASS=pass
```

This would work, but is annoying. Envy automagically turns this into:

```
<?php

var_dump($environment->database); // object: {name: foo, vendor: pgsql, user: user, pass: pass}
```

This works for as many levels of nesting as you'd need.

## Placeholders
Default `.env` placeholders are supported, e.g.:

```
NAME=marijn
PATH=/home/${marijn}/Documents
```

## Gotchas and caveats
- Do not give environments names that are also used as variables. Variables take
  precendence, so the results won't be what you expect.
- Similar for "underscore expanded" objects and JSON. Depending on the order it
  _might_ produce a sort-of working result, but it's probably not going to be
  what you want. Just take care here; either use JSON or expansion, but don't
  mix and match.

Note, however, the following is not a problem:

```
DATABASE_NAME=foo
DATABASE_CONFIG={"foo":"bar"}
```

...since the JSON parsing won't be done until after the underscores have been
expanded.

## Differences from other DOTENV loaders
- Envy supports JSON and "underscore expansion" for your convenience;
- Envy supports multiple environments based on conditions, e.g. you can have
  `.env.dev`, `.env.cli` and `.env.test` in the same folder and decide at run
  time which one(s) is/are applicable;
- Envy supports the `->$environment` check so your code can also do different
  things based on the environment(s) loaded. E.g. during tests you may want to
  load certains mocks instead of your regular dependencies, and during
  development you don't want to send mails to actual users, but just to the
  developer in question for review.

