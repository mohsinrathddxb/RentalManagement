<?php
ob_start();
session_start();

require_once "functions/db.php";
require_once "functions/country_options.php";
require_once "functions/admin_profile_helpers.php";

ensure_admin_profile_schema($connection);

$formError = '';
$formSuccess = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = is_username($_POST['full_name']);
    $emiratesId = uncrack($_POST['emirates_id']);
    $propertyAddress = uncrack($_POST['property_address']);
    $propertyDetails = uncrack($_POST['property_details']);
    $country = is_username($_POST['country']);
    $email = is_email($_POST['email']);
    $phoneNumber = trim(uncrack($_POST['phone_number']));
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $passwordConfirm = isset($_POST['password_confirm']) ? trim($_POST['password_confirm']) : '';

    if ($fullName === '' || $emiratesId === '' || $propertyAddress === '' || $propertyDetails === '' || $country === '' || $email === '' || $phoneNumber === '' || $password === '' || $passwordConfirm === '') {
        $formError = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Please enter a valid email address.';
    } elseif ($password !== $passwordConfirm) {
        $formError = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $formError = 'Password must be at least 6 characters long.';
    } else {
        $existing = mysqli_query($connection, "SELECT `id` FROM `admin` WHERE `email`='" . mysqli_real_escape_string($connection, $email) . "' LIMIT 1");
        if ($existing && mysqli_num_rows($existing) > 0) {
            $formError = 'An account with that email already exists.';
        } else {
            $documentPath = '';
            if (!empty($_FILES['property_document']['name'])) {
                $documentPath = upload_admin_property_document($_FILES['property_document']);
                if ($documentPath === false) {
                    $formError = 'Property document must be PDF, JPG, PNG, WEBP, DOC, or DOCX and below 8MB.';
                }
            }

            if ($formError === '') {
                $safeName = mysqli_real_escape_string($connection, $fullName);
                $safeEmiratesId = mysqli_real_escape_string($connection, $emiratesId);
                $safePropertyAddress = mysqli_real_escape_string($connection, $propertyAddress);
                $safePropertyDetails = mysqli_real_escape_string($connection, $propertyDetails);
                $safeCountry = mysqli_real_escape_string($connection, $country);
                $safeEmail = mysqli_real_escape_string($connection, $email);
                $safePhone = mysqli_real_escape_string($connection, $phoneNumber);
                $safeDocumentPath = mysqli_real_escape_string($connection, $documentPath);
                $passwordHash = mysqli_real_escape_string($connection, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));

                $sql = "
                    INSERT INTO `admin`
                    (`name`, `role`, `email`, `password`, `emirates_id`, `property_address`, `property_details`, `property_document`, `country`, `phone_number`)
                    VALUES
                    ('$safeName', 'level-0', '$safeEmail', '$passwordHash', '$safeEmiratesId', '$safePropertyAddress', '$safePropertyDetails', '$safeDocumentPath', '$safeCountry', '$safePhone')
                ";

                if (mysqli_query($connection, $sql)) {
                    header("Location: login.php?registered=1");
                    exit;
                }

                $formError = 'The account could not be created right now. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" type="image/svg+xml" href="../plugins/images/co-living-space-logo.svg">
    <title>Co- Accomodation Admin Sign Up</title>
    <link href="bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../plugins/bower_components/bootstrap-extension/css/bootstrap-extension.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/colors/blue.css" id="theme" rel="stylesheet">
    <link href="css/luxury-theme.css" rel="stylesheet">
    <style>
        html, body {
            min-height: 100%;
            overflow-y: auto;
        }

        body.signup-page {
            background: #edf1f5;
        }

        body.signup-page .login-register {
            position: relative;
            min-height: 100vh;
            height: auto;
            padding: 30px 15px 40px;
            display: block;
            overflow: visible;
            background: #edf1f5;
        }

        body.signup-page .login-box {
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            position: relative;
            top: auto;
            transform: none;
            left: auto;
        }

        body.signup-page .white-box {
            padding: 28px 32px;
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        body.signup-page .form-control {
            height: 42px;
        }

        body.signup-page textarea.form-control {
            height: auto;
            min-height: 110px;
            resize: vertical;
        }

        body.signup-page .signup-actions {
            margin-top: 10px;
        }

        @media (max-width: 767px) {
            body.signup-page .login-register {
                padding: 15px 10px 25px;
            }

            body.signup-page .white-box {
                padding: 20px 16px;
            }
        }
    </style>
</head>

<body class="signup-page">
    <div class="preloader">
        <div class="cssload-speeding-wheel"></div>
    </div>
    <section id="wrapper" class="login-register">
        <div class="login-box">
            <div class="white-box">
                <div class="login-brand">
                    <img src="../plugins/images/co-living-space-logo.svg" alt="Co-Living Space">
                </div>
                <form class="form-horizontal form-material" action="signup.php" method="post" enctype="multipart/form-data">
                    <h3 class="box-title m-b-20">Admin Sign Up</h3>
                    <p class="text-muted m-b-20">Create your admin account and then continue to login.</p>

                    <?php if ($formError !== '') { ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php } ?>

                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Full Name: *</label>
                            <input class="form-control" type="text" name="full_name" required placeholder="Enter full name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Emirates ID: *</label>
                            <input class="form-control" type="text" name="emirates_id" required placeholder="Enter Emirates ID" value="<?php echo isset($_POST['emirates_id']) ? htmlspecialchars($_POST['emirates_id'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Country: *</label>
                            <select name="country" class="form-control" required>
                                <option value="">Select country</option>
                                <?php echo get_country_options(isset($_POST['country']) ? $_POST['country'] : 'United Arab Emirates'); ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Phone Number: *</label>
                            <input class="form-control" type="tel" name="phone_number" required placeholder="+971 50 123 4567" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-12">
                            <label>Email ID: *</label>
                            <input class="form-control" type="email" name="email" required placeholder="Enter email address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            <small class="text-muted">This email becomes the login ID.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-12">
                            <label>Address Of The Properties: *</label>
                            <textarea class="form-control" name="property_address" rows="3" required placeholder="Enter property address or addresses"><?php echo isset($_POST['property_address']) ? htmlspecialchars($_POST['property_address'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                        </div>
                        <div class="form-group col-md-12">
                            <label>Major Property Details: *</label>
                            <textarea class="form-control" name="property_details" rows="4" required placeholder="Enter major property details"><?php echo isset($_POST['property_details']) ? htmlspecialchars($_POST['property_details'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                        </div>
                        <div class="form-group col-md-12">
                            <label>Property Documentation:</label>
                            <input class="form-control" type="file" name="property_document" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                            <small class="text-muted">Optional. PDF, JPG, PNG, WEBP, DOC, or DOCX up to 8MB.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Enter Password: *</label>
                            <input class="form-control" type="password" name="password" id="Password" required placeholder="Enter password">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Re-type Password: *</label>
                            <input class="form-control" type="password" name="password_confirm" id="ConfirmPassword" required placeholder="Re-type password">
                            <div id="msg" style="padding-top: 6px;"></div>
                        </div>
                    </div>

                    <div class="form-group text-center m-t-20 signup-actions">
                        <div class="col-xs-12">
                            <button class="btn btn-info btn-lg btn-block text-uppercase waves-effect waves-light" type="submit">Create Admin Account</button>
                        </div>
                    </div>
                    <div class="form-group text-center m-b-0">
                        <a href="login.php">Back to login</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
    <script src="../plugins/bower_components/jquery/dist/jquery.min.js"></script>
    <script src="bootstrap/dist/js/tether.min.js"></script>
    <script src="bootstrap/dist/js/bootstrap.min.js"></script>
    <script src="../plugins/bower_components/bootstrap-extension/js/bootstrap-extension.min.js"></script>
    <script src="../plugins/bower_components/sidebar-nav/dist/sidebar-nav.min.js"></script>
    <script src="js/jquery.slimscroll.js"></script>
    <script src="js/waves.js"></script>
    <script src="js/custom.min.js"></script>
    <script>
    $(document).ready(function(){
        $("#ConfirmPassword, #Password").on("keyup", function(){
            if ($("#Password").val() === "" && $("#ConfirmPassword").val() === "") {
                $("#msg").html("");
                return;
            }
            if ($("#Password").val() !== $("#ConfirmPassword").val()) {
                $("#msg").html("Password do not match").css("color","red");
            } else {
                $("#msg").html("Password matched").css("color","green");
            }
        });
    });
    </script>
</body>
</html>
