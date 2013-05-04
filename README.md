# Generator Bundle

## Installation

First, install the bundle package with composer:

```bash
$ php composer.phar require ano/generator-bundle
```

Next, activate the bundle (and bundle it depends on) into `app/AppKernel.php`:

```PHP
<?php

// ...
    public function registerBundles()
    {
        $bundles = array(
            //...
            new Ano\GeneratorBundle\AnoGeneratorBundle(),
        );

        // ...
    }
```

## Demo

![Workflow](https://raw.github.com/benjamindulau/AnoGeneratorBundle/master/ano_generator.gif)

