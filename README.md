# Static PHP Contact form

__Static PHP Contact form__ provides a self-contained, stand-alone mini-framework allowing to put HTML contact forms on static websites (generated with CMS like [Hugo](https://gohugo.io/), [Jekyll](https://jekyllrb.com/), [Gatsby](https://www.gatsbyjs.com/), etc.). The front-end part (HTML form) will need to be added in a page on the static website, while the back-end part (PHP scripts and libs) will need to be installed on any old-school [LAMP](https://en.wikipedia.org/wiki/LAMP_(software_bundle)) server you control, and is designed for basic shared hostings where limited admin options are offered (no access to `sudo`, `apt install`, Docker containers, etc.).

> **Deploying this on your own site: see [DEPLOY.md](DEPLOY.md).**

## One back-end

`send-email.php` is the only endpoint: it takes the POST, filters, and sends.
**All credentials live in a `.env` file** at the project root (see `.env.example`) —
never in the source tree, never in Git. `src/config.php` keeps only the structure:
templates, recipients, and which domains may post.

A second, cut-down handler (`send.php`, `.env`-configured but without templates,
GeoIP or the user-agent checks) existed briefly and was folded back in here on
2026-09-29: two code paths doing the same job is how one of them silently rots.

## Features

- User-agent detection (OS, browser, public IP, local/private IP),
- GeoIP sender detection through [Maxmind](https://www.maxmind.com/en/solutions/ip-geolocation-databases-api-services) (API key needed),
- Bots blocking, (honeypot and mandatory JS support),
- ~~Spam detection~~ (needed ?)
- Send emails through SMTPS (mandatory) using [PHPMailer](https://github.com/PHPMailer/PHPMailer), for proper email authentication through [SPF](https://en.wikipedia.org/wiki/Sender_Policy_Framework) and [DKIM](https://en.wikipedia.org/wiki/DomainKeys_Identified_Mail) (improve deliverability, limit spam detection),
- Self-hosted with reduced overhead (KISS):
    - The PHP framework can be installed on any PHP server (separate from email server and website server),
    - The contact form can be displayed on any web page where you can add `<iframes>`,
    - Update code through Git,
    - Auto-update GeoIP databases (with Cron job).
- You can use several email templates (extendable), plain-text and HTML.


## Why Static PHP Contact form ?

Many companies provide the same kind of back-end service (POST endpoint for the HTML form, with SMTP servers). The great thing is they handle everything for you. The not-so-great thing is emails privacy and costs. Truth be told, if you have any kind of (cheap) LAMP-based hosting, you don't need more. Remains that you will need to configure and maintain everything yourself. We are here to make that as painless as possible.


## Prerequisites

- a dedicated sending email address (like `mailer@your-domain.com`) accessible through SMTPS,[^1]
- PHP 8.1 minimum, and : 
    - [Phar](https://www.php.net/manual/en/book.phar.php) extension,
    - [cUrl](https://www.php.net/manual/en/book.curl.php) extension,
    - PHP Composer,
- A MaxMind GeoIP2 Lite license key (free) ([get yours](https://support.maxmind.com/hc/en-us/articles/4407111582235-Generate-a-License-Key)),

It is recommended to have SPF and DKIM configured on your domain, to maximize email deliverability ([how to do it on CPanel hostings](https://docs.cpanel.net/whm/dns-functions/enable-dkim-spf-globally/)).


## Getting started

### Basic concept

An HTML contact form is actually much simpler than what people used to CMS imagine. You need an HTML form on some static HTML page:

```html
<form action="https://some-domain.com/send-email.php" method="post">
    <input name="email">
    <textarea name="message"></textarea>
    <button type="submit">Send</button>
</form>
```

When you click the "Send" button, an HTTP POST request is sent to `https://some-domain.com/send-email.php` __by your browser__, with `email` and `message` as parameters. You don't need a CMS or even AJAX calls on your actual website to handle anything dynamically, actually the tech needed to achieve that existed already in 2002.

All we need is the actual `send-email.php` end-point to catch that POST request and do something with its content (namely, post it to some mailbox). This end-point doesn't need to be part of our website. It doesn't need to be hosted on the same server, or even domain, as our website.

The scope of this project is to provide this end-point, self-hostable on any PHP server, so static websites only have to display the form fields.

### Install

You will need Git installed on your computer. In a terminal, do:

```bash
$ git clone --recurse-submodules --shallow-submodules https://github.com/aurelienpierre/Static-PHP-contact-form.git
$ cd Static-PHP-contact-form
```

### Configure

Copy and rename (or rename) `src/config_example.php` to `src/config.php`. Then start updating its content, following the instructions in comments. In the following, `src/config.php` will be called `CONFIG`

The first thing to configure is your [MaxMind license key](https://support.maxmind.com/hc/en-us/articles/4407111582235-Generate-a-License-Key) and the admin secret key:

```php
    'admin-secret' => 'abcdefghijkl',
    'maxmind' => array(
        'account' => "123456",                     // Account ID number : 6 digits
        'password' => "xxxxxxxxxxxxxxxxxxxxxxxxx", // secret key
    ),
```

Once this is done, we will need to download the MaxMind GeoIP databases…

### Local testing

You need to install PHP locally on your computer. Then, to start a local testing server, from the root of the sourcecode, do:

```bash
php -S localhost:8000
```

The root of the sourcecode folder will then be available in your browser at <http://localhost:8000>. Change the port number if it is already used.

#### Updating the GeoIP databases

The first thing we will need to do is to download MaxMind GeoIP databases. Once the test server is launched (on directly on the production server), and you set your account ID/license key in `CONFIG`, do:

```bash
wget localhost:8000/update-geoip.php?key=abcdefghijkl
```

where `abcdefghijkl` is your `admin-secret` `CONFIG` value. A new download is triggered everytime you hit `update-geoip.php` through the network with the proper `admin-secret` key, so the obvious purpose of the authentification key is to avoid blasting I/O everytime a bot hits that page (and possibly getting throttled by MaxMind).

#### Sending emails

After you started your local server, hit <http://localhost:8000/demo/contact.html> in your browser to get the HTML form. This file is a fully-working example that you can re-use as-is, or adapt. In this setup, you will need to set your `CONFIG['mailer']['host']` key to the distant address of your email server (that is, __not__ `localhost`). Try sending an email to yourself (don't forget to properly set the `CONFIG` file first) and check that everything works.

#### Deploy in production

The simplest way is to create an archive with your whole local sourcecode folder (including GeoIP databases and the PHPMailer submodule), then send it to your server, and decompress it there, for example in `your-domain.com/backend`. Otherwise, you can redo the above steps (`git clone` then `wget your-domain.com/backend//update-geoip.php?key=abcdefghijkl`) on your server.

If the server hosting your PHP scripts also hosts your email account, you can set `CONFIG['mailer']['host']` to `localhost`, to access the SMTP server from within (and possibly faster).

You may want to delete or deny access to the `./demo` folder if you don't use the HTML files in `iframe`.

## Displaying contact forms on your website

We are going to assume here that you deployed Static PHP Contact form to `your-domain.com/backend` and this server is accessible through `https`.

### The lazy way: iframe

You can simply add to your HTML/Markdown page:

```html
<iframe src="https://your-domain.com/backend/demo/contact.html"
        width="100%" height="900px" />
```

This will display the demo contact form within the target page. However, the height should be set fixed, which is not responsive to display dimensions. A minimal example of `iframe` embedding is given in `./demo/iframe-demo.html`.

### The pretty way: inline HTML

You can copy and paste the content of `./demo/contact.html` to your static website generator templates, and then modify it further. All the `<input>` fields found in the demo need to be in the HTML form because they are required by the PHP POST endpoint `./send-email.php`, but you can set most of them to `type=hidden`.

If you go this way, don't forget to load the Javascript validation script. Here is a minimal example:

```html
<form action="https://your-domain.com/backend/send-email.php"
      method="post" id="contact-form">

  <!-- all inputs go there -->

  <!-- only our JS validation will enable this button: -->
  <button type="submit" disabled>Send</button>
</form>

<!-- Load the validation script -->
<script src="https://your-domain.com/backend/js/user-agent.min.js"></script>

<!-- Call the server-side user-agent identification -->
<script>
  window.addEventListener('DOMContentLoaded', () => {
      // need to wait for the script above to be loaded before calling the function:
      validate_contact("https://your-domain.com/backend/user-agent.php");
  });
</script>
```

### The clever way: Hugo shortcode

Import `./demo/hugo_contact.html` into your Hugo theme `layouts/shortcodes` directory. Then, from within your Markdown pages, add the shortcode:

```
{{< hugo_contact "https://your-domain.com/backend" template="html" >}}
```

The first parameter is the URL of your Static PHP Contact form installation (without trailing /). The template parameter is optional and will default to `default` (aka `./templates/default.php`).

### Final thoughts

When hitting the `Submit` button of the HTML `<form>`, all the input fields are sent as parameters of the POST HTTPS request to the  `./send-email.php` endpoint. To prevent abuses, the endpoint accepts form submissions only from pre-allowed domains, defined in the `origins` key in the `CONFIG` file. Any page where you display a contact form should have its domain in the `origins` list, even if it is on the same server/domain as the `./send-email.php` endpoint.


## Creating custom templates

Each email template gets:

- its own list of receipients,
- its own body formatting,
- its own language,
- its own confirmation message.

All email templates share the same sending SMTP server.

Templates can be used to send different kind of customized emails, for example you could have one template per language, or one per receipient service (so some contact forms would be directed to customer support, some other to pre-sales, etc.).

To create a new template, for example named `custom`:

1. in `CONFIG` file, in the `templates` array, add a new `custom` entry and fill its mandatory fields (see the example in `./src/config_example.php`), like `receipients`, `lang`
2. in `./templates` directory, add a new `custom.php` file and write its `email_body()` function. See `./templates/default.php` for a plain-text example, and `./templates/html.php` for an HTML body example.
3. in your HTML contact form, set the `template` input field to `custom`, like:
```html
<input name="template" value="custom" type="hidden" readonly="readonly">
```

Any additional `<input name="xxx">` tag you add in your HTML `<form>` will have its value transfered in the global variable `$_POST['xxx']` that can be used directly from within the template `email_body()` function. You can also define custom keys in `CONFIG` file and access them from `email_body()`.

You could let visitors chose the template themselves in the form, for example if each template is mapped to a different receipient service. In that case, replace `<input name="template">` with:

```html
<label for="template">Service</label>
<select name="template" required>
    <option value="">--Please choose an option--</option>
    <option value="default">General inquiries</option>
    <option value="custom">User support</option>
</select>
```

WARNING: do __NOT__ change the content of the factory-provided email templates (`default.php` and `html.php`), as they would be overwritten by later updates. Instead, copy and rename them to anything but `default` and `html`.

## Updating

### Source code and PHPMailer

From within the source code directory, use Git to update everything:

```bash
git pull --recurse-submodules
```

Your `./src/config.php` file and custom templates will not be overwritten (provided you did not change the factory-provided templates).

Because PHPMailer is a mild security concern (logging into servers through SSL layers), it is a good idea to keep it reasonably up-to-date.

### GeoIP databases

The databases mapping IP to geographic location have an expiration date because the IP of residential ISP accounts changes every month or so. It might be a good idea to update those databases at least once a month.

To do so, you only have to hit `https://your-domain.com/backend/update-geoip.php?key=abcdefghijkl` with `key` set as your `CONFIG` `admin-secret` value. You can do so from a web browser, manually, or you can simply set up a weekly Cron job with:

```bash
wget --delete-after https://your-domain.com/backend/update-geoip.php?key=abcdefghijkl > /dev/null
```

## Deeper Hugo integration

In your `config.toml` or `hugo.toml` file, define:

```toml
[Params]
  contact = "/contact"
```

Then you can create links to your contact page using the `?utm_source` parameter to keep track of the source page, using the Hugo template tag `{{ .Permalink }}` to define the URL to contact page, like so:

```html
<a title="Contact" href="{{ with .Site.GetPage .Site.Params.contact }}{{ .Permalink }}{{ end }}?utm_source={{ (replaceRE "https?://" "" .Permalink) }}">
```

If you click on the contact link from the page `your-domain.com/portfolio`, you would then produce the URL:

```
https://your-domain.com/portfolio.com/contact/?utm_source=your-domain.com/portfolio
```

The `utm_source` parameter is read by our `./js/user-agent.js` script and added to the hidden form field `utm`. In your email PHP templates, you can reuse it as `$_POST['utm']`. Aside from tracking purposes, it can give some context to understand the content of some cryptic emails if you know where the person comes from.


## Security, validation and spam

### Client-side

The form fields validation is done by the browser native features (`required` fields and `email` type format).

Our mandatory `./js/user-agent.js` script hits the `./user-agent.php` endpoint, which returns a JSON response used by the script to fill the user-agent related HTML form fields. The local IP address is resolved client-side through the WebRTC API — and because modern browsers hide it behind mDNS names, the promise resolves to `0.0.0.0` after 3 s so a browser that will not reveal it cannot block the form.

> **The trap.** This whole scheme has one failure mode, and it is silent: if that
> script throws for any reason, the hidden fields stay empty, the submit button is
> never enabled, and **no human can send you anything** — while bots posting
> straight to the endpoint carry on. You conclude that you only ever get spam.
>
> It happened here: `var DNSResolver = browser.dns || chrome.dns` threw a
> `ReferenceError` in every browser outside a WebExtension. Fixed in
> `./js/user-agent.js`. Two rules follow: never mark a user-agent field
> `required` in the HTML (let the back-end judge), and open the browser console
> on your live contact page after every deployment.

Those user-agent fields are sent back in the POST request to the `./send-email.php` endpoint, which will compare them to the internal ones from `./user-agent.php` and refuse the connection if they don't match. This effectively rejects all user-agents not supporting Javascript, which should keep away most spamming bots.

The `<input name="address">` is a honeypot field. It should be in the form but should stay empty, which means it should be hidden from the form GUI. Bots will usually try to stuff it with random content.

### Server-side

`./send-email.php` refuses form submissions :

- from empty user agents,
- from domains not in the `CONFIG` `origins` key (`HTTP_REFER` or `HTTP_ORIGIN`),
- if the target email domain :
    - does not exist (DNS can't reach it),
    - has no MX entry (no email server advertised),
- if the `address` POST parameter is not empty (honeypot caught a bot),
- if any other POST parameter is empty (see `./demo/contact.html` for mandatory fields)

Note that `localip` is deliberately **not** in that list: some browsers legitimately
refuse to expose it, and refusing them would lock out humans rather than bots.


All internal code subdirectories are protected with a `.htaccess` file defining the rules:

```
deny from all

<Files subdirectory/*>
    deny from all
</Files>
```

On the server, you should set your permissions as follow:

Files:
- `./src/*.php`: 644,
- `./templates/*.php`: 644,
- `.htaccess`: 444

Folders
- `./src/`: 555
- `./templates`: 555,
- `./libs/`: 755
