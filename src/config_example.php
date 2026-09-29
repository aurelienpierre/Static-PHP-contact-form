<?php
/**
 * Structure and non-secret settings. Every credential comes from the .env file at
 * the project root (see .env.example) — never from this file, which is safe to commit.
 */

require_once(__DIR__ . '/env.php');

return array(
    // PHPMailer front-end debug option. Set to 0 to disable, 1 for debug output, 2 for verbose debug output.
    // This exposes sensitive information on the front-end PHP page.
    // DO NOT LEAVE IT ENABLED (> 0) ON A PRODUCTION SERVER
    'debug' => 0,

    // A password-like random string to validate queries between user-agent.php and send.php.
    // Generate one with https://www.avast.com/random-password-generator#pc
    'user-secret' => env('USER_SECRET'),

    // Used when calling update-geoip.php to authentify requests and throttle service,
    // Generate one with https://www.avast.com/random-password-generator#pc
    'admin-secret' => env('ADMIN_SECRET'),

    // Public IP of localhost, for cases where the API is called from within the same local network.
    // Used for GeoIP testing/debugging purposes, when running a test PHP server locally. Any valid IP will do.
    // Not used when processing actual HTTP requests from the web.
    'localhost_ip' => '8.8.8.8', // default to Google DNS

    // MaxMind GeoIP2Lite licence key.
    // Lite is the free service.
    // Create a license key : https://support.maxmind.com/hc/en-us/articles/4407111582235-Generate-a-License-Key
    'maxmind' => array(
        'account'  => env('MAXMIND_ACCOUNT'),
        'password' => env('MAXMIND_KEY'),
    ),

    // Domains authorized to send requests to our APIs
    // Domains allowed to POST to these endpoints. Comma-separated in .env:
    //   ORIGINS=localhost,your-domain.com
    'origins' => array_map('trim', explode(',', env('ORIGINS', 'localhost'))),

    // Email server settings
    'mailer' => array(

        // Two cases here : (choose one, uncomment it, comment the other).
        // 1. if you run the PHP scripts from inside of your email server :
        //'host' => 'localhost',
        // 2. if you run the PHP scripts from outside of your email server (or if you don't know)
        'host' => env('SMTP_HOST'),

        // The second is more robust (works from inside or outside the email server),
        // but the first may be faster (when applicable) since it doesn't go through DNS resolution.
        // If using full domain (method 2.), it should match the SPF entry in your DNS zones.
        // Learn more : https://www.nslookup.io/learning/mx-vs-spf-vs-dmarc-vs-dkim-vs-bimi/

        // The mailbox used to send emails through SMTP.
        // Treat it as a burner account for security purpose (aka no private communications).
        // It will also collect bouncing emails, so it may be a good idea to monitor it.
        'username' => env('SMTP_USER'),
        'password' => env('SMTP_PASS'),

        // SMTP port - We force usage of STARTTLS, so this should be working.
        'port' => (int) env('SMTP_PORT', 587),
    ),

    // Emails template settings
    'templates' => array(

        // Config for the default template, matching `default.php` from ./templates
        'default' => array(
            // List of people who will get contact form emails, as name => address
            // Who receives the messages. Comes from MAIL_TO in .env so the address
            // is never a placeholder left over from this example file; comma-separate
            // it there for several recipients.
            'receipients' => array_map('trim', explode(',', env('MAIL_TO', ''))),

            // String to prepend to the subject line
            'subject_prepend' => '[CONTACT] ',

            // PHPMailer ISO 639-1 language code (for translated error messages, etc.)
            // Available list : https://github.com/PHPMailer/PHPMailer/tree/master/language/
            'lang' => 'en',

            // Confirmation message. Displayed once the message has been successfully sent.
            'confirmation' => "<p>Success, message sent !</p><p>You should get a copy of the email in a few minutes in your own mailbox. If you don't, it means this server is blocked by your email provider. Try again with a different email.</p>",
        ),

        // Optional: create your own template: write a new `custom.php` file in ./templates.php,
        // then send a POST request to ./send-email.php?template=custom using an hidden <input name="template" value="custom">
        // tag in your HTML form.
        'custom' => array(
            // Mandatory fields (a template with no recipient is refused at runtime):
            'receipients' => array_map('trim', explode(',', env('MAIL_TO', ''))),
            'subject_prepend' => '',
            'lang' => '',
        ),

        // can add as many templates as you like
    ),

    // Note: this array is passed as the `$config` parameter to email templates.
    // You can add any custom key here that you need to access from templates.
);
