# AsgardCMS installer

This is a helper package to install AsgardCMS quickly using a simple command.

```bash
asgardcms new Blog
```

## Installation

Require using composer:

```bash
composer global require asgardcms/asgardcms-installer
```

Make sure to place the `$HOME/.composer/vendor/bin` directory (or the equivalent directory for your OS) in your `$PATH` so the asgardcms` executable can be located by your system.

Once installed, the `asgardcms new` command will create a fresh AsgardCMS installation in the directory you specify. For instance, `asgardcms new blog` will create a directory named `blog` containing a fresh AsgardCMS installation with all of AsgardCMS's dependencies already installed:

```bash
asgardcms new Blog
```


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

