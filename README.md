# Dependencies

This project requires the following dependencies to be met in order to operate:

* A GNU UNIX based operating system.
* The `curl`, `echo`, and `parallel` commands.
* The Composer Dependency Manager for PHP.

# Install

To prepare this project, run `composer install` from the project directory.

# Configure

All configuration for this project is located in `config.php` in the project's
root directory.

## `$path`

This configuration variable holds the base path in which IPSW files will be
synchronized.

## `$last_versions`

This configuration variable holds the number of most recent firmware versions
to download per device category (e.g. `AppleTV`, `iPad`, etc)

## `$parallel`

This configuration variable holds the number of concurrent downloads to run at
once.  This value is capped at `6` due to a limit imposed by Apple's download 
servers.

# Usage

Simply run `php main.php` to synchronize IPSW files.
