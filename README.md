# WP-Bot: The WordPress IRC helper bot

This is the WordPress IRC helper bot for the #WordPress chat room on FreeNode.

## Usage

1. Install `composer` from [getcomposer.org](https://getcomposer.org/).
1. Install and set up a WordPress instance to be your bot's backend.
1. Install and activate the [WP-Bot/wp-wpbot-commands](https://github.com/WP-Bot/wp-wpbot-commands) plugin in your bot's WordPress instance.
1. Checkout this repo.
1. Run `composer install`.
1. Copy the file `config-example.php` to `config.php`.
1. Add the database details for your bot's backend WordPress database to `config.php`.
1. Configure the IRC connection in `config.php`.
1. Start the bot with `php contributor-bot.php`.

## Local Testing Environment

You need `docker` and `docker-compose` installed:

1) Run `docker-compose up`.
2) Connect to `http://localhost:9000`.
3) Click `Connect`.
4) Type a message of `!help` to see the bot react.