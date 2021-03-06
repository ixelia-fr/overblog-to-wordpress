OverBlog to WordPress
=====================

## Features

* Import posts.
* Import pages.
* Import comments and replies to comments.
* Create the Featured image in WordPress for each article.
* Import images and updates posts to make use of them.
* Import attachments and updates posts to make use of them.
* Compatibility with old OverBlog articles.
* URL cleanup (cool for SEO).
* Automatic URL redirection from old URLs to new ones.
* HTML code cleanup (for easier styling in a WordPress theme).
    * Remove some inline styles.
    * Remove links on single images.

## Installing and running

### Run a local instance of WordPress

Copy `.env` file to `.env.dist`.

Run:

    make wordpress_start

Access website on http://localhost:8100

### Configure WordPress for the import

* Allow users to add a comment with no username or email address.
* Add [Redirection](https://redirection.me/) plugin

### Run import

Install dependencies:

    docker run --rm --interactive --tty \
  --volume $PWD:/app \
  composer install

With PHP WordPress functions:

    docker-compose run --rm php ./app.php wp:import-overblog data/export3-fix.xml

Options:

* `--ignore-images`: do not import images
* `--limit=123`: Max number of posts to import
* `--slug=my_reg_ex`: Filter slugs to import by regex

### Find errors in provided XML file

    xmllint --stream data/file.xml

### Run tests

    ./vendor/bin/phpunit

## Technical information

### Redirections

Automatic configuration is made during the import (using the Redirection plugin) to redirect
pages according to the following rules:

* `/article-SOMETHING.html` to `/YEAR/MONTH/SOMETHING`
* `/SOMETHING` to `/YEAR/MONTH/SOMETHING`
* `/*.html` to `/*`
