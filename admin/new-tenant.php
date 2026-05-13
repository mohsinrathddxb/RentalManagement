<?php

$pgnm='Co- Accomodation: Admit a tenant';
$error=' ';

ob_start();

require_once "functions/db.php";
require_once "functions/partition_helpers.php";
require_once "functions/tenant_helpers.php";
require_once "functions/country_options.php";
require_once "functions/errors.php";

session_start();

if (is_logged_in_temporary()) {
    require_admin_user();
    ensure_partition_tables($connection);
    ensure_tenant_schema($connection);

    if (isset($_POST['admitTenant'])) {
        $dateAdmitted = date('20y-m-d');
        $house = uncrack($_POST['house']);
        $partitionId = (int) uncrack($_POST['partition_id']);
        $tenantRows = isset($_POST['tenants']) && is_array($_POST['tenants']) ? $_POST['tenants'] : [];

        if (count($tenantRows) === 0) {
            header('location:new-tenant.php?state=4&save_error=1');
            exit();
        }

        $houseid = substr($house, 0, strpos($house, '_'));
        $noOfRooms = substr($house, strpos($house, '_') + 1, strlen($house));
        $timesnap = date('Y-m-d : H:i:s');

        $sq1 = "SELECT `house_name`,`rent_amount` FROM `houses` WHERE `houseID`='$houseid'";
        $rec_house = mysqli_query($conn, $sq1);
        $rec_item = mysqli_fetch_array($rec_house, MYSQLI_BOTH);

        $hsname = $rec_item['house_name'];
        $rentAmount = $rec_item['rent_amount'];

        $sq_partition = "SELECT `partition_number`,`rent_amount` FROM `house_partitions` WHERE `partition_id`='$partitionId' AND `house_id`='$houseid'";
        $rec_partition = mysqli_query($conn, $sq_partition);
        $partition_item = mysqli_fetch_array($rec_partition, MYSQLI_BOTH);

        if (!$partition_item) {
            header('location:new-tenant.php?state=4&partition_error=1');
            exit();
        }

        $partitionNumber = $partition_item['partition_number'];
        $rentAmount = $partition_item['rent_amount'];

        $sq_houses = "UPDATE `houses` SET `house_status`='Occupied' WHERE `houseID`='$houseid'";
        $sq_partition_update = "UPDATE `house_partitions` SET `partition_status`='Occupied' WHERE `partition_id`='$partitionId'";

        $tenantCountValue = count($tenantRows);
        $sql_transactions = "INSERT INTO `transactions` (`actor`,`time`,`description`)
            VALUES ('Admin ($username)', '$timesnap','$username admitted $tenantCountValue tenant(s) to $hsname partition $partitionNumber at $timesnap')";

        $mysqli->autocommit(FALSE);
        $status = true;
        $dateError = false;
        $accountWarning = false;

        foreach ($tenantRows as $tenantRow) {
            $tname = is_username($tenantRow['tname']);
            $temail = is_email($tenantRow['temail']);
            $idnum = uncrack($tenantRow['idnum']);
            $phoneCode = uncrack($tenantRow['phone_code']);
            $phoneLocal = uncrack($tenantRow['phone_local']);
            $prof = is_username($tenantRow['prof']);
            $tenantAddress = uncrack($tenantRow['tenant_address']);
            $tenantHomeCountryAddress = uncrack($tenantRow['tenant_home_country_address']);
            $tenantCountry = is_username($tenantRow['tenant_country']);
            $startDate = uncrack($tenantRow['start_date']);
            $endDate = uncrack($tenantRow['end_date']);

            if ($endDate !== '' && strtotime($startDate) > strtotime($endDate)) {
                $status = false;
                $dateError = true;
                break;
            }

            $phone = trim($phoneCode . ' ' . $phoneLocal);

            $sq_tenants = "INSERT INTO `tenants`
                (`houseNumber`,`partition_id`,`tenant_name`,`email`,`ID_number`,`profession`,`phone_number`,`tenant_address`,`tenant_home_country_address`,`tenant_country`,`start_date`,`end_date`,`tenant_status`,`dateAdmitted`)
                VALUES
                ('$houseid','$partitionId','$tname','$temail','$idnum','$prof','$phone','$tenantAddress','$tenantHomeCountryAddress','$tenantCountry','$startDate','$endDate','Active','$dateAdmitted')";

            if ($mysqli->query($sq_tenants)) {
                $tenantId = (int) $mysqli->insert_id;

                if ($tenantId <= 0) {
                    $tenantIdResult = mysqli_query($connection, "SELECT MAX(`tenantID`) AS latest_tenant_id FROM `tenants`");
                    if ($tenantIdResult && ($tenantIdRow = mysqli_fetch_assoc($tenantIdResult))) {
                        $tenantId = (int) $tenantIdRow['latest_tenant_id'];
                    }
                }

                if ($tenantId <= 0) {
                    $status = false;
                    break;
                }

                if (!ensure_tenant_user_account($connection, $tenantId, $tname, $temail, $phone)) {
                    $accountWarning = true;
                }
            } else {
                $status = false;
                break;
            }
        }

        $mysqli->query($sq_houses) ? null : $status = false;
        $mysqli->query($sq_partition_update) ? null : $status = false;
        $mysqli->query($sql_transactions) ? null : $status = false;

        if ($status) {
            $mysqli->commit();
            header('location:tenants.php?state=3' . ($accountWarning ? '&tenant_account_warning=1' : ''));
            exit();
        } else {
            $mysqli->rollback();
            header('location:new-tenant.php?state=4' . ($dateError ? '&date_error=1' : '&save_error=1'));
            exit();
        }
    }

    require "admin_header0.php";
    require "admin_left_panel.php";
    ?>

    <div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title"><?php echo 'Hey '.$username.'!';?></h4>
                </div>
                <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                    <ol class="breadcrumb">
                        <li><a href="index.php">Dashboard</a></li>
                        <li><a href="tenants.php">Tenants</a></li>
                        <li class="active">New</li>
                    </ol>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div><?php echo $error; ?></div>
                    <div class="white-box">
                        <h3 class="box-title m-b-0"><i class="fa fa-user fa-3x"></i> Admit A New Tenant</h3>
                        <p class="text-muted m-b-30 font-13"> Fill in the form below: </p>
                        <div class="row">
                            <div class="col-sm-12 col-xs-12">
                                <?php
                                if (isset($_GET["date_error"])) {
                                    echo '<div class="alert alert-danger"><a href="#" class="close" data-dismiss="alert" aria-label="close"></a><strong>DATE ERROR!! </strong><p>Start date cannot be greater than end date. End date can be left empty.</p></div>';
                                } elseif (isset($_GET["partition_error"])) {
                                    echo '<div class="alert alert-danger"><a href="#" class="close" data-dismiss="alert" aria-label="close"></a><strong>PARTITION ERROR!! </strong><p>The selected partition could not be found for this house.</p></div>';
                                } elseif (isset($_GET["save_error"])) {
                                    echo '<div class="alert alert-danger"><a href="#" class="close" data-dismiss="alert" aria-label="close"></a><strong>SAVE ERROR!! </strong><p>The tenant could not be saved. Please try again after refreshing the page.</p></div>';
                                }
                                ?>

                                <form action="new-tenant.php" method="post">
                                    <div class="form-group">
                                        <label for="house">House: *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-home"></i></div>
                                            <select required id="house" name="house" class="form-control">
                                                <option value="">**Select a house**</option>
                                                <?php
                                                $sq0="SELECT `houseID`,`house_name`,`house_status`, `number_of_rooms` FROM `houses` ORDER BY `houseID` DESC";
                                                $rec=mysqli_query($conn,$sq0);
                                                while ($row=mysqli_fetch_array($rec,MYSQLI_BOTH)) {
                                                    $house=$row['house_name'];
                                                    $hsid_rooms=$row['houseID'].'_'.$row['number_of_rooms'];
                                                    echo "<option value=\"$hsid_rooms\"> $house </option> ";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="partition_id">Partition inside house: *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-columns"></i></div>
                                            <select required id="partition_id" name="partition_id" class="form-control">
                                                <option value="">**Select a house first**</option>
                                                <?php
                                                $sqPartitions="SELECT hp.`partition_id`, hp.`house_id`, hp.`partition_number`, hp.`rent_amount`, hp.`partition_status`, h.`house_name` FROM `house_partitions` hp LEFT JOIN `houses` h ON hp.`house_id`=h.`houseID` ORDER BY h.`house_name`, hp.`partition_number`";
                                                $recPartitions=mysqli_query($conn,$sqPartitions);
                                                while ($partition=mysqli_fetch_array($recPartitions,MYSQLI_BOTH)) {
                                                    $partitionLabel=$partition['house_name'].' - '.$partition['partition_number'].' (Rent: '.$partition['rent_amount'].', '.$partition['partition_status'].')';
                                                    echo '<option value="'.$partition['partition_id'].'" data-house="'.$partition['house_id'].'">'.$partitionLabel.'</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <small class="text-muted">A partition can contain more than one active tenant.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="tenant_count">Number of tenants in this partition: *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-users"></i></div>
                                            <input type="number" min="1" max="20" value="1" required class="form-control" id="tenant_count">
                                        </div>
                                    </div>

                                    <div id="tenant-details" class="row"></div>

                                    <button type="submit" name="admitTenant" class="btn btn-success btn-lg waves-effect waves-light m-r-10 center"><i class="fa fa-plus-circle fa-lg"></i> Admit tenant(s)</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="right-sidebar">
                <div class="slimscrollright">
                    <div class="rpanel-title"> Service Panel <span><i class="ti-close right-side-toggle"></i></span> </div>
                    <div class="r-panel-body">
                        <ul>
                            <li><b>Layout Options</b></li>
                            <li><div class="checkbox checkbox-info"><input id="checkbox1" type="checkbox" class="fxhdr"><label for="checkbox1"> Fix Header </label></div></li>
                            <li><div class="checkbox checkbox-warning"><input id="checkbox2" type="checkbox" checked="" class="fxsdr"><label for="checkbox2"> Fix Sidebar </label></div></li>
                            <li><div class="checkbox checkbox-success"><input id="checkbox4" type="checkbox" class="open-close"><label for="checkbox4"> Toggle Sidebar </label></div></li>
                        </ul>
                        <ul id="themecolors" class="m-t-20">
                            <li><b>With Light sidebar</b></li>
                            <li><a href="javascript:void(0)" theme="default" class="default-theme">1</a></li>
                            <li><a href="javascript:void(0)" theme="green" class="green-theme">2</a></li>
                            <li><a href="javascript:void(0)" theme="gray" class="yellow-theme">3</a></li>
                            <li><a href="javascript:void(0)" theme="blue" class="blue-theme working">4</a></li>
                            <li><a href="javascript:void(0)" theme="purple" class="purple-theme">5</a></li>
                            <li><a href="javascript:void(0)" theme="megna" class="megna-theme">6</a></li>
                            <li><b>With Dark sidebar</b></li>
                            <br/>
                            <li><a href="javascript:void(0)" theme="default-dark" class="default-dark-theme">7</a></li>
                            <li><a href="javascript:void(0)" theme="green-dark" class="green-dark-theme">8</a></li>
                            <li><a href="javascript:void(0)" theme="gray-dark" class="yellow-dark-theme">9</a></li>
                            <li><a href="javascript:void(0)" theme="blue-dark" class="blue-dark-theme">10</a></li>
                            <li><a href="javascript:void(0)" theme="purple-dark" class="purple-dark-theme">11</a></li>
                            <li><a href="javascript:void(0)" theme="megna-dark" class="megna-dark-theme">12</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <footer class="footer text-center"> 2018 &copy; Company Admin </footer>
    </div>
    </div>

    <script src="../plugins/bower_components/jquery/dist/jquery.min.js"></script>
    <script src="bootstrap/dist/js/tether.min.js"></script>
    <script src="bootstrap/dist/js/bootstrap.min.js"></script>
    <script src="../plugins/bower_components/bootstrap-extension/js/bootstrap-extension.min.js"></script>
    <script src="../plugins/bower_components/sidebar-nav/dist/sidebar-nav.min.js"></script>
    <script src="js/jquery.slimscroll.js"></script>
    <script src="js/waves.js"></script>
    <script src="js/custom.min.js"></script>
    <script src="js/jasny-bootstrap.js"></script>
    <script src="../plugins/bower_components/styleswitcher/jQuery.style.switcher.js"></script>

    <script type="text/javascript">
    (function() {
        var houseSelect = document.getElementById('house');
        var partitionSelect = document.getElementById('partition_id');

        if (!houseSelect || !partitionSelect) {
            return;
        }

        var partitionOptions = Array.prototype.slice.call(partitionSelect.querySelectorAll('option[data-house]'));

        function filterPartitions() {
            var houseValue = houseSelect.value || '';
            var houseId = houseValue.split('_')[0];
            var hasVisiblePartition = false;

            partitionOptions.forEach(function(option) {
                var shouldShow = option.getAttribute('data-house') === houseId;
                option.style.display = shouldShow ? '' : 'none';
                option.disabled = !shouldShow;
                if (shouldShow) {
                    hasVisiblePartition = true;
                }
            });

            partitionSelect.value = '';
            partitionSelect.options[0].text = hasVisiblePartition ? '**Select partition**' : '**No partition for selected house**';
        }

        houseSelect.addEventListener('change', filterPartitions);
        filterPartitions();
    })();

    (function() {
        var form = document.querySelector('form[action="new-tenant.php"]');
        var tenantCount = document.getElementById('tenant_count');
        var tenantDetails = document.getElementById('tenant-details');
        var countryOptions = <?php echo json_encode(get_country_options('United Arab Emirates')); ?>;
        var phoneCodeOptions = <?php echo json_encode(get_phone_code_options('+971')); ?>;

        if (!form || !tenantCount || !tenantDetails) {
            return;
        }

        function tenantBlock(index) {
            return '' +
                '<div class="col-lg-6 col-md-6 col-sm-12">' +
                '<div class="panel panel-default tenant-person" style="border:1px solid #e4e7ea; min-height: 100%;">' +
                    '<div class="panel-heading"><strong>Tenant ' + (index + 1) + '</strong></div>' +
                    '<div class="panel-body">' +
                        '<div class="form-group"><label>Tenant Name: *</label><div class="input-group"><div class="input-group-addon"><i class="fa fa-pencil"></i></div><input type="text" name="tenants[' + index + '][tname]" class="form-control" placeholder="Enter tenant name" required></div></div>' +
                        '<div class="form-group"><label>Tenant Country: *</label><div class="input-group"><div class="input-group-addon"><i class="fa fa-globe"></i></div><select name="tenants[' + index + '][tenant_country]" class="form-control js-country-select" required>' + countryOptions + '</select></div></div>' +
                        '<div class="form-group"><label>Phone Number: *</label><div class="row">' +
                            '<div class="col-sm-5" style="padding-left:0;"><select name="tenants[' + index + '][phone_code]" class="form-control js-phone-code-select" required>' + phoneCodeOptions + '</select></div>' +
                            '<div class="col-sm-7" style="padding-right:0;"><input type="tel" name="tenants[' + index + '][phone_local]" class="form-control js-phone-input" placeholder="50 123 4567" required></div>' +
                        '</div></div>' +
                        '<div class="form-group"><label>EmiratesID or Passport number: *</label><div class="input-group"><div class="input-group-addon"><i class="fa fa-user"></i></div><input type="text" name="tenants[' + index + '][idnum]" class="form-control" placeholder="EmiratesID or Passport number..." required></div></div>' +
                        '<div class="form-group"><label>Address: *</label><div class="input-group"><div class="input-group-addon"><i class="fa fa-map-marker"></i></div><input type="text" name="tenants[' + index + '][tenant_address]" class="form-control" placeholder="Current address" required></div></div>' +
                        '<div class="form-group"><label>Tenant home country address: *</label><div class="input-group"><div class="input-group-addon"><i class="fa fa-home"></i></div><input type="text" name="tenants[' + index + '][tenant_home_country_address]" class="form-control" placeholder="Home country address" required></div></div>' +
                        '<div class="form-group"><label>Start Date: *</label><div class="input-group"><div class="input-group-addon"><i class="fa fa-calendar"></i></div><input type="date" name="tenants[' + index + '][start_date]" class="form-control js-start-date" required></div></div>' +
                        '<div class="form-group"><label>Expected End Date:</label><div class="input-group"><div class="input-group-addon"><i class="fa fa-calendar"></i></div><input type="date" name="tenants[' + index + '][end_date]" class="form-control js-end-date"></div><small class="text-muted">Optional</small></div>' +
                        '<div class="form-group"><label>Email: *</label><div class="input-group"><div class="input-group-addon"><i class="fa fa-at"></i></div><input type="email" name="tenants[' + index + '][temail]" class="form-control" placeholder="example@co-accomodation.com" required></div><small class="text-muted">This will be the tenant username for first login.</small></div>' +
                        '<div class="form-group"><label>Profession:</label><div class="input-group"><div class="input-group-addon"><i class="fa fa-briefcase"></i></div><input type="text" name="tenants[' + index + '][prof]" class="form-control" placeholder="e.g. Teacher"></div></div>' +
                    '</div>' +
                '</div>' +
                '</div>';
        }

        function renderTenantBlocks() {
            var count = parseInt(tenantCount.value, 10) || 1;
            count = Math.max(1, Math.min(20, count));
            tenantCount.value = count;
            var html = '';
            for (var index = 0; index < count; index++) {
                html += tenantBlock(index);
            }
            tenantDetails.innerHTML = html;
        }

        function validateDates() {
            Array.prototype.slice.call(tenantDetails.querySelectorAll('.tenant-person')).forEach(function(block) {
                var startDate = block.querySelector('.js-start-date');
                var endDate = block.querySelector('.js-end-date');
                if (startDate.value && endDate.value && startDate.value > endDate.value) {
                    endDate.setCustomValidity('End date must be on or after start date.');
                } else {
                    endDate.setCustomValidity('');
                }
            });
        }

        function syncPhoneSelection(block) {
            var countrySelect = block.querySelector('.js-country-select');
            var phoneCodeSelect = block.querySelector('.js-phone-code-select');
            var phoneInput = block.querySelector('.js-phone-input');
            if (!countrySelect || !phoneCodeSelect || !phoneInput) {
                return;
            }

            var selectedCountry = countrySelect.value;
            Array.prototype.slice.call(phoneCodeSelect.options).forEach(function(option) {
                if (selectedCountry && option.text.indexOf(selectedCountry) !== -1) {
                    phoneCodeSelect.value = option.value;
                }
            });

            if (!phoneInput.value.trim()) {
                phoneInput.setAttribute('placeholder', '50 123 4567');
            }
        }

        tenantCount.addEventListener('change', renderTenantBlocks);
        tenantDetails.addEventListener('change', function(event) {
            if (event.target.classList.contains('js-country-select')) {
                syncPhoneSelection(event.target.closest('.tenant-person'));
            }
            validateDates();
        });
        form.addEventListener('submit', validateDates);
        renderTenantBlocks();
        Array.prototype.slice.call(tenantDetails.querySelectorAll('.tenant-person')).forEach(syncPhoneSelection);
    })();
    </script>
</body>
</html>
<?php
} else {
    header('location:../index.php');
}
?>
