# Dependencies

This project requires the following dependencies to be met in order to operate:

* A UNIX-based operating system.
* The `curl` command.
* The Composer Dependency Manager for PHP.

# Install

To prepare this project, run `composer install` from the project directory.

# Configure

This project has only one major configuration variable: `$path`.  Set `$path`
(in `config.php`) to the desired output directory for the IPSW files.

# Usage

Simply run `php main.php` to synchronize IPSW files.
