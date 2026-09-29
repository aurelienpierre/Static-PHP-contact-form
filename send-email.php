<?php
/**
 * Send an email through Sendmail from a POST HTTP request.
 */

$config = include('./src/config.php');
require_once("./src/utils.php");

// Credentials come from .env: say so plainly instead of failing at SMTP time.
$missing = env_missing(['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'ORIGINS', 'MAIL_TO']);
if ($missing) {
    http_response_code(500);
    exit('<p>Server configuration is incomplete: ' . implode(', ', $missing)
         . ' missing from .env. Please report it.</p>');
}

require 'libs/PHPMailer/src/Exception.php';
require 'libs/PHPMailer/src/PHPMailer.php';
require 'libs/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// The address param is a honeypot : should be empty if human user. Only bots will fill it like idiots.
// The return_to address should be set so the user doesn't end on our messaging page.

if (!authorize($config) || empty($_POST) || !empty($_POST['address']) || empty($_POST['return_to'])) {
    http_response_code(403);
    die();
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Sending an email</title>
<meta name="robots" content="noindex,nofollow">
</head>
<body>
<div style="display: flex; flex-basis: row; width: 500px; margin: 0 auto; align-items: center; height: 100vh; justify-content: center; font-size: 1.5em;">
<div>
<?php
$errors = [];
//print_r($_POST);
//print_r($_SERVER);

// Unpack query strings from request
if (empty($_POST['name']))    $errors[] = 'Name is empty';
if (empty($_POST['subject'])) $errors[] = 'Subject is empty';
if (empty($_POST['message'])) $errors[] = 'Message is empty';
if (empty($_POST['email']))   $errors[] = 'Email is empty';
else
{
    $email_domain = explode("@", $_POST['email'])[1];
    // Check that :
    // 1. the DNS resolves the domain of the email address (aka domain exists),
    // 2. the DNS entry has MX records (aka a mailserver is configured there).
    // 3. the email address exists -> impossible, we will know when sending the email, if it bounces.
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) || !checkdnsrr($email_domain) || !getmxrr($email_domain, $hosts))
        $errors[] = 'Email is invalid';
}

// The following are queried from server (PHP) at ./user-agent.php, then added to the hidden HTML forms by our Javascript,
// then passed-on to here through POST params.
// Using the same methods as in user-agent.php, we check consistency of fields, meaning that
// we got the request from an user-agent supporting JS, which discards lots of bots.
if (empty($_POST['ip']) || $_POST['ip'] != get_ip($config))
    $errors[] = 'Public IP is invalid';

if (empty($_POST['os']) || $_POST['os'] != getOS())
    $errors[] = 'OS is invalid';

if (empty($_POST['browser']) || $_POST['browser'] != getBrowser())
    $errors[] = 'Browser is invalid';

if (empty($_POST['lang']) || $_POST['lang'] != get_lang())
    $errors[] = 'Lang is invalid';

if (empty($_POST['return_to']) ||
    strtok($_POST['return_to'], '#')[0] != strtok($_SERVER['HTTP_REFERER'], '#')[0])
    // Remove anchors from return_to before comparing to HTTP_REFERER
    $errors[] = 'Origin is invalid';

/* Not supported by some browsers - needs investigation
if (empty($_POST['localip']))
    $errors[] = 'Local IP is invalid';
*/

if (empty($_POST['template']))
    $errors[] = 'Email template is not set';

if (!isset($config['templates'][$_POST['template']]))
    $errors[] = 'Email template is not configured';

if(!file_exists('./templates/' . $_POST['template'] . '.php'))
    $errors[] = 'Email template does not exist';

// Without this, PHPMailer reports success while the message goes nowhere.
elseif (empty(array_filter($config['templates'][$_POST['template']]['receipients'] ?? [])))
    $errors[] = 'Email template has no recipient';

if (!empty($errors))
{
    $allErrors = join('<br/>', $errors);
    $errorMessage = "<p style='color: red;'>{$allErrors}</p>";
    echo $errorMessage;
}
else
{
    try
    {
        $mail = new PHPMailer(true);
        $template = $config['templates'][$_POST['template']];

        $mail->setLanguage($template['lang']);

        // Server settings
        $mail->SMTPDebug = $config['debug'];                    // Enable verbose debug output : set to 1 or 2
        $mail->isSMTP();                                        // Send using SMTP
        $mail->Host       = $config['mailer']['host'];          // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                               // Enable SMTP authentication
        $mail->Username   = $config['mailer']['username'];      // SMTP username
        $mail->Password   = $config['mailer']['password'];      // SMTP password
        $mail->SMTPSecure = 'tls';                              // Enable implicit TLS encryption
        $mail->Port       = $config['mailer']['port'];          // TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
        $mail->CharSet    = "UTF-8";
        $mail->SMTPAutoTLS = true;

        // Recipients
        $mail->setFrom($mail->Username, $_POST['name']);        // "From" address needs to be on the DKIM sending domain for authentication.
        $mail->addReplyTo($_POST['email'], $_POST['name']);     // Reply to the actual sender's address.
        $mail->addCC($_POST['email'], $_POST['name']);          // Send a copy of the email to the sender, for achival purposes.

        // Add the actual receipient(s) of the email
        foreach ($template['receipients'] as $name => $addr)
            $mail->addAddress($addr, $name);

        // $mail->addBCC('bcc@example.com');

        $mail->Sender        = $mail->Username;                 // Address that will collect bounces
        $mail->DKIM_domain   = $mail->Host;
        $mail->DKIM_identity = $mail->Username;

        // Attachments
        //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
        //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

        // Content
        $mail->Subject = $template['subject_prepend'] . $_POST['subject'];

        // Fetch the proper template function. We checked for its validity above.
        require_once('./templates/' . $_POST['template'] . '.php');
        $mail->Body = email_body($mail, $config);

        // Finalize
        $mail->send();
        echo $template['confirmation'];

        // Redirect after success
        echo "<p>You will be redirected in 10 s…</p>";
        header("refresh:10;url=" . $_POST['return_to'] . "");
    }
    catch (Exception $e)
    {
        echo "<p>Message could not be sent. Mailer Error: {$mail->ErrorInfo}</p>";
    }
}
?>
</div>
</div>
</body>
</html>
