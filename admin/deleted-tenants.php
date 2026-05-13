<?php
$pgnm = "Co- Accomodation: Deleted / Moved Out Tenants";
$error = ' ';

require_once "functions/errors.php";
ob_start();
require_once "functions/db.php";
require_once "functions/partition_helpers.php";
require_once "functions/tenant_helpers.php";

session_start();

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header("location: login.php");
    exit;
}

if (is_logged_in_temporary()) {
    require_admin_user();
    ensure_partition_tables($connection);
    ensure_tenant_schema($connection);

    $sql = "
        SELECT
            t.`tenantID`,
            t.`tenant_name`,
            t.`email`,
            t.`ID_number`,
            t.`phone_number`,
            t.`tenant_country`,
            t.`start_date`,
            t.`end_date`,
            t.`exit_date`,
            t.`tenant_status`,
            h.`house_name`,
            COALESCE(hp.`rent_amount`, h.`rent_amount`) AS `rent_amount`,
            hp.`partition_number`
        FROM `tenants` t
        LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
        LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
        WHERE t.`tenant_status` = 'Deleted&Moved_Out'
        ORDER BY t.`tenantID` DESC
    ";

    $query = mysqli_query($connection, $sql);

    require "admin_header0.php";
    require "admin_left_panel.php";
?>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Moved Out Tenants</h4>
            </div>
            <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                <ol class="breadcrumb">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="tenants.php">Tenants</a></li>
                    <li class="active">Deleted / Moved Out</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="white-box">
                    <?php echo $error; ?>
                    <h3 class="box-title m-b-0">Deleted / moved out tenants ( <x style="color: orange;"><?php echo @mysqli_num_rows($query); ?></x> )</h3>
                    <p class="text-muted m-b-30">These tenants are kept in the database for admin history only.</p>
                    <div class="table-responsive">
                        <table id="example23" class="display nowrap" cellspacing="0" width="100%">
                            <?php
                            if (@mysqli_num_rows($query) == 0) {
                                echo "<i style='color:brown;'>No deleted or moved out tenants to display.</i> ";
                            } else {
                                echo '
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>House</th>
                                        <th>Partition</th>
                                        <th>Email</th>
                                        <th>EmiratesID / Passport</th>
                                        <th>Phone Number</th>
                                        <th>Country</th>
                                        <th>Rent</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Exit Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th>Name</th>
                                        <th>House</th>
                                        <th>Partition</th>
                                        <th>Email</th>
                                        <th>EmiratesID / Passport</th>
                                        <th>Phone Number</th>
                                        <th>Country</th>
                                        <th>Rent</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Exit Date</th>
                                        <th>Status</th>
                                    </tr>
                                </tfoot>
                                <tbody>
                                ';
                            }

                            while ($row = @mysqli_fetch_array($query)) {
                                echo '
                                <tr>
                                    <td>' . $row["tenant_name"] . '</td>
                                    <td>' . $row["house_name"] . '</td>
                                    <td>' . $row["partition_number"] . '</td>
                                    <td>' . $row["email"] . '</td>
                                    <td>' . $row["ID_number"] . '</td>
                                    <td>' . $row["phone_number"] . '</td>
                                    <td>' . $row["tenant_country"] . '</td>
                                    <td>' . $row["rent_amount"] . '</td>
                                    <td>' . $row["start_date"] . '</td>
                                    <td>' . $row["end_date"] . '</td>
                                    <td>' . $row["exit_date"] . '</td>
                                    <td>' . $row["tenant_status"] . '</td>
                                </tr>
                                ';
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require "admin_footer.php"; ?>
    <script>
    $('#example23').DataTable({
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
    });
    </script>
    <script src="../plugins/bower_components/styleswitcher/jQuery.style.switcher.js"></script>
</body>
</html>
<?php
} else {
    header('location:index.php');
}
?>
