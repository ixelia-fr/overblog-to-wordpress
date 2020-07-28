OverBlog to WordPress
=====================

## Run a local instance of WordPress

Copy `.env` file to `.env.dist`.

Run:

    make wordpress_start

Access website on http://localhost:8100

## Configure WordPress for the import

* Install the *miniOrange WordPress REST API Authentication* plugin and activate Basic Authentication.
* Allow users to add a comment with no username or email address.

## Run import

    ./app.php wp:import-overblog <file> <wordpress_base_uri> <username> <password>

Options:

* `--ignore-images`: do not import images

## Find errors in XML file

    xmllint --stream data/file.xml
