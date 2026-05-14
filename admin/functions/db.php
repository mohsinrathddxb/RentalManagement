<?php
// error reporting. Disable these lines after deployment is stable.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*this file is the main connecor and handles data cleaning and issues of login

*/
// database connection variables
$host = 'localhost';
$user = 'root';
$usrpassword = '';
$database = 'Company';

// optional for sending SMS via sms.textsms.co.ke API
$sms_apiKey = "YourAPIKey";
$sms_partnerID = "YourPartinerID";
$sms_shortcode = "TextSMS";

// Telegram bot configuration
$telegram_bot_token = "8712334063:AAGwk3uU-9i4cmO4Xx7zGDAsXZ7B8bN6FIk";

/* DATABASE CONNECTIONS AS DEFINED IN VARIOUS PAGES */
global $connection, $mysqli, $conn;

// for OOP uses
$mysqli = new mysqli($host, $user, $usrpassword, $database);

// for imperative
$conn = mysqli_connect($host, $user, $usrpassword, $database);

// original connector alias
$connection = mysqli_connect($host, $user, $usrpassword, $database);

if (!$connection) {
    die("Cannot Establish A Secure Connection To The Host Server At The Moment!");
}

try {
    $db = new PDO(
        'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8',
        $user,
        $usrpassword
    );
} catch (Exception $e) {
    die('Cannot Establish A Secure Connection To The Host Server At The Moment!');
}

/*********************************************************
            other basic methods
**********************************************************/

function sendSMS($number, $message)
{
    global $sms_apiKey, $sms_partnerID, $sms_shortcode;

    $postData = [
        "apikey" => $sms_apiKey,
        "partnerID" => $sms_partnerID,
        "message" => $message,
        "shortcode" => $sms_shortcode,
        "mobile" => $number,
    ];

    $ch = curl_init("https://sms.textsms.co.ke/api/services/sendsms/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    curl_close($ch);
}

function uncrack($data)
{
    $data = (string) $data;
    $data = trim($data);
    $data = htmlspecialchars($data);
    $data = stripcslashes($data);

    $data = str_replace('"', '\\"', $data);
    $data = str_replace("'", "\\'", $data);

    return $data;
}

function money_to_cents($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }

    $value = str_replace([',', ' '], '', $value);
    return (int) round(((float) $value) * 100);
}

function cents_to_money($cents)
{
    return round(((int) $cents) / 100, 2);
}

function format_money_amount($value)
{
    $amount = round((float) $value, 2);
    if (abs($amount - round($amount)) < 0.00001) {
        return number_format(round($amount), 0, '.', '');
    }

    return number_format($amount, 2, '.', '');
}

function is_username($data)
{
    $data = uncrack($data);
    $data = strtolower($data);
    $data = ucwords($data);
    return $data;
}

function is_email($data)
{
    $data = uncrack($data);
    $data = strtolower($data);
    return $data;
}

function random_password()
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyz.ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890@%&';
    $pass = [];
    $alphaLength = strlen($alphabet) - 1;
    for ($i = 0; $i < 8; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass);
}

function is_logged_in_permanent()
{
    if (isset($_COOKIE["name"]) && isset($_COOKIE["tsc"]) && isset($_COOKIE["type"])) {
        global $uname, $tsc, $type, $funame;

        $uname = $_COOKIE["name"];
        $tsc = $_COOKIE["tsc"];
        $type = $_COOKIE["type"];
        $funame = substr($uname, 0, strpos($uname, " "));

        return true;
    }

    return false;
}

function is_logged_in_temporary()
{
    if (isset($_SESSION['email'])) {
        global $username, $userrole, $userfullname, $connection;

        $email = $_SESSION["email"];
        $username = substr($email, 0, strpos($email, "@"));
        $userrole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
        $userfullname = isset($_SESSION['name']) ? $_SESSION['name'] : $username;

        if ($connection && empty($userrole)) {
            $safeEmail = mysqli_real_escape_string($connection, $email);
            $result = mysqli_query($connection, "SELECT `name`, `role` FROM `admin` WHERE `email`='$safeEmail' LIMIT 1");
            if ($result && mysqli_num_rows($result) === 1) {
                $row = mysqli_fetch_assoc($result);
                $userfullname = $row['name'];
                $userrole = $row['role'];
                $_SESSION['name'] = $userfullname;
                $_SESSION['role'] = $userrole;
            }
        }

        return true;
    }

    return false;
}

function is_admin_user()
{
    global $userrole;
    if (empty($userrole)) {
        is_logged_in_temporary();
    }

    return in_array($userrole, ['level-0', 'level-1', 'level-2', 'level-3'], true);
}

function is_tenant_user()
{
    global $userrole;
    if (empty($userrole)) {
        is_logged_in_temporary();
    }

    return $userrole === 'user';
}

function get_logged_in_tenant_record()
{
    global $connection;

    if (!is_logged_in_temporary() || !is_tenant_user()) {
        return null;
    }

    $email = isset($_SESSION['email']) ? mysqli_real_escape_string($connection, $_SESSION['email']) : '';
    if ($email === '') {
        return null;
    }

    $queries = [
        "SELECT * FROM `tenantsView` WHERE `email`='$email' AND `tenant_status`='Active' ORDER BY `tenantID` DESC LIMIT 1",
        "SELECT * FROM `tenants` WHERE `email`='$email' AND `tenant_status`='Active' ORDER BY `tenantID` DESC LIMIT 1"
    ];

    foreach ($queries as $sql) {
        $result = @mysqli_query($connection, $sql);
        if ($result && mysqli_num_rows($result) === 1) {
            return mysqli_fetch_assoc($result);
        }
    }

    return null;
}

function require_admin_user()
{
    if (!is_logged_in_temporary() || !is_admin_user()) {
        header('location:index.php?restricted=1');
        exit();
    }
}

function require_tenant_user()
{
    if (!is_logged_in_temporary() || !is_tenant_user()) {
        header('location:index.php?restricted=1');
        exit();
    }
}
?>
