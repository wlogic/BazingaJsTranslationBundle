JsTranslationBundle
===================

A pretty nice way to expose your translated messages to your JavaScript.

**Important:** This documentation has been written for version `2.0.0` and above
of this bundle. For version `1.x`, please read:
[https://github.com/willdurand/BazingaJsTranslationBundle/blob/1.2.0/Resources/doc/index.md](https://github.com/willdurand/BazingaJsTranslationBundle/blob/1.2.0/Resources/doc/index.md).
Also, you might be interested in this [UPGRADE
guide](https://github.com/willdurand/BazingaJsTranslationBundle/blob/master/UPGRADE.md).


Installation
------------

Install the bundle:

    composer require "willdurand/expose-translation-bundle:@stable"

**Protip:** you should browse the
[`willdurand/expose-translation-bundle`](https://packagist.org/packages/willdurand/expose-translation-bundle)
page to choose a stable version to use, avoid the `@stable` meta constraint.

Register the bundle in `app/AppKernel.php`:

``` php
<?php
// app/AppKernel.php
public function registerBundles()
{
    return array(
        // ...
        new Bazinga\Bundle\JsTranslationBundle\BazingaJsTranslationBundle(),
    );
}
```

Register the routing in `app/config/routing.yml`:

``` yaml
# app/config/routing.yml
_bazinga_jstranslation:
    resource: "@BazingaJsTranslationBundle/Resources/config/routing/routing.yml"
```

Publish assets:

    php app/console assets:install --symlink web


Usage
-----

Add this line in your layout:

``` html
<script type="text/javascript" src="{{ asset('bundles/bazingajstranslation/js/translator.min.js') }}"></script>
```

Now, you have to specify which [translation
files](http://symfony.com/doc/current/book/translation.html#translation-locations-and-naming-conventions)
you want to load.

### Load Translations

``` html
<script type="text/javascript" src="{{ url('bazinga_jstranslation_js') }}"></script>
```

This will use the current `locale` and will return the translated messages found
in each `messages.CURRENT_LOCALE.*` files of your project.

#### Domains

``` html
<script type="text/javascript" src="{{ url('bazinga_jstranslation_js', { 'domain': 'DOMAIN_NAME' }) }}"></script>
```

This will use the current `locale` and will return the translated messages found
in each `DOMAIN_NAME.CURRENT_LOCALE.*` files of your project.

#### Locales

You can specify a `locales` **query parameter** to get translations in another
language but also to load translations for different languages at once:

``` html
<script type="text/javascript" src="{{ url('bazinga_jstranslation_js', { 'domain_name': 'DOMAIN_NAME') }}?locales=MY_LOCALE"></script>
```

This will return the translated messages found in each `DOMAIN_NAME.MY_LOCALE.*`
files of your project.

``` html
<script type="text/javascript" src="{{ url('bazinga_jstranslation_js', { 'domain_name': 'DOMAIN_NAME') }}?locales=fr,en"></script>
```

This will return the translated messages found in each `DOMAIN_NAME.(fr|en).*`
files of your project.

#### Loading via JSON

Alternatively, you can load your translated messages via JSON (e.g. using
jQuery's `ajax()` or RequireJS's text plugin). Just amend the above mentioned
URLs to also contain the `'_format': 'json'` parameter like so:

``` html
{{ url('bazinga_jstranslation_js', { '_format': 'json' }) }}
```

Then, feed the translator via `Translator.fromJSON(myRetrievedJSONString)`.

### The `dump` Command

This bundle provides a command to dump the translation files:

    php app/console bazinga:js-translation:dump [target]

The optional `target` argument allows you to override the target directory to
dump JS translation files in.

**Important:** as soon as you use dumped translation files, you must include a
`meta` tag containing the current application's locale:

```html
<meta name="bazinga-js-translation" content="{{ app.request.locale }}">
```

#### Assetic

The command above is useful if you use
[Assetic](http://symfony.com/doc/current/cookbook/assetic/asset_management.html):

```twig
{% javascripts
    'bundles/bazingajstranslation/js/translator.min.js'
    'translations/config.js'
    'translations/*/*.js' %}
    <script type="text/javascript" src="{{ asset_url }}"></script>
{% endjavascripts %}
```

You have to load the `config.js` file, which contains the configuration for the
JS Translator, then you can load all translation files that have been dumped.
Note that dumped files don't contain any configuration, they only add messages
to the JS Translator.

In the example above, all translation files from your entire project will be
loaded. Of course you can load specific domains: `translations/admin/*.js`.

### The JS Translator

The `Translator` object implements the Symfony2
[`TranslatorInterface`](https://github.com/symfony/symfony/blob/master/src/Symfony/Component/Translation/TranslatorInterface.php)
and provides the same `trans()` and `transChoice()` methods:

``` javascript
Translator.trans('key', {}, 'DOMAIN_NAME');
// the translated message or undefined

Translator.transChoice('key', 1, {}, 'DOMAIN_NAME');
// the translated message or undefined
```

> **Note:** The JavaScript is AMD ready.

#### Message Placeholders / Parameters

The `trans()` method accepts a second argument that takes an array of parameters:

``` javascript
Translator.trans('key', { "foo" : "bar" }, 'DOMAIN_NAME');
// will replace each "%foo%" in the message by "bar".
```

You can override the placeholder delimiters by setting the `placeHolderSuffix`
and `placeHolderPrefix` attributes.

The `transChoice()` method accepts this array of parameters as third argument:

``` javascript
Translator.transChoice('key', 123, { "foo" : "bar" }, 'DOMAIN_NAME');
// will replace each "%foo%" in the message by "bar".
```

> Read the official documentation about Symfony2 [message
placeholders](http://symfony.com/doc/current/book/translation.html#message-placeholders).

#### Pluralization

Probably the best feature provided by this bundle! It allows you to use
pluralization exactly like you would do using the Symfony Translator
component.

``` yaml
# app/Resources/messages.en.yml
apples: "{0} There is no apples|{1} There is one apple|]1,19] There are %count% apples|[20,Inf] There are many apples"
```

``` javascript
Translator.locale = 'en';

Translator.transChoice('apples', 0, {"count" : 0});
// will return "There is no apples"

Translator.transChoice('apples', 1, {"count" : 1});
// will return "There is one apple"

Translator.transChoice('apples', 2, {"count" : 2});
// will return "There are 2 apples"

Translator.transChoice('apples', 10, {"count" : 10});
// will return "There are 10 apples"

Translator.transChoice('apples', 19, {"count" : 19});
// will return "There are 19 apples"

Translator.transChoice('apples', 20, {"count" : 20});
// will return "There are many apples"

Translator.transChoice('apples', 100, {"count" : 100});
// will return "There are many apples"
```

> Read the official doc about
[pluralization](http://symfony.com/doc/current/book/translation.html#pluralization).

#### Get The Locale

You can get the current locale by accessing the `locale` attribute:

``` javascript
Translator.locale;
// will return the current locale.
```

Examples
--------

Consider the following translation files:

``` yaml
# app/Resources/translations/Hello.fr.yml
foo: "Bar"
ba:
    bar: "Hello world"

placeholder: "Hello %username% !"
```

``` yaml
# app/Resources/translations/messages.fr.yml
placeholder: "Hello %username%, how are you ?"
```

You can do:

``` javascript
Translator.trans('foo');
// will return 'Bar' if the current locale is set to 'fr',
// undefined otherwise.

Translator.trans('ba.bar');
// will return 'Hello world' if the current locale is set to 'fr',
// undefined otherwise.

Translator.trans('placeholder');
// will return 'Hello %username% !' if the current locale is set to 'fr',
// undefined otherwise.

Translator.trans('placeholder', { "username" : "will" });
// will return 'Hello will !' if the current locale is set to 'fr',
// undefined otherwise.

Translator.trans('placeholder', { "username" : "will" });
// will return 'Hello will, how are you ?' if the current locale is set to 'fr',
// undefined otherwise.

Translator.trans('placeholder');
// will return 'Hello %username%, how are you ?' if the current locale is set to
// 'fr', undefined otherwise.
```


More configuration
------------------

#### Locale Fallback

If some of your translations are not complete you can enable a fallback for
untranslated messages:

``` yaml
bazinga_expose_translation:
    locale_fallback: en  # It is recommended to set the same value used for the
                         # translator fallback.
```

#### Default Domain

You can define the default domain used when translation messages are added
without any given translation domain:

``` yaml
bazinga_expose_translation:
    default_domain:       messages
```


Reference Configuration
-----------------------

``` yaml
# app/config/config*.yml
bazinga_js_translation:
    locale_fallback:      en
    default_domain:       messages
```


Testing
-------

### PHP

Setup the test suite using [Composer](http://getcomposer.org/):

    $ composer install --dev

Run it using PHPUnit:

    $ phpunit

### JavaScript

You can run the JavaScript test suite using [PhantomJS](http://phantomjs.org/):

    $ phantomjs Resources/js/run-qunit.js file://`pwd`/Resources/js/index.html