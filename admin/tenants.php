<?php
    $pgnm="Co- Accomodation: View Tenants";
    $error=' ';

    //require the global file for errors
    require_once "functions/errors.php";
    
    ob_start();
    require_once "functions/db.php";
    require_once "functions/partition_helpers.php";
    require_once "functions/tenant_helpers.php";
    require_once "functions/country_options.php";

    // Initialize the session

    session_start();

    // If session variable is not set it will redirect to login page

    if(!isset($_SESSION['email']) || empty($_SESSION['email'])){

      header("location: login.php");

      exit;
    }
    if (is_logged_in_temporary()) {
        #allow access
    require_admin_user();
    ensure_partition_tables($connection);
    ensure_tenant_schema($connection);
    $canManageTenants = true;
    $deleteErrorTenantId = isset($_GET["delete_error_tenant"]) ? (int) $_GET["delete_error_tenant"] : 0;
    $deleteErrorExitDate = isset($_GET["delete_error_exit_date"]) ? $_GET["delete_error_exit_date"] : '';
    $deleteErrorStartDate = isset($_GET["delete_error_start_date"]) ? $_GET["delete_error_start_date"] : '';
    $showDeleteExitDateError = isset($_GET["exit_date_error"]);
    $showDeleteExitDateRequired = isset($_GET["exit_date_required"]);
    

    $email = $_SESSION['email'];

   /* $sql = "SELECT `tenantID`,`houseNumber`,`tenant_name`,`email`,`ID_number`,`profession`,`phone_number`,`dateAdmitted`,`agreement_file`, `house_name`,`number_of_rooms`,`house_status`,`rent_amount`,`houseID` FROM `tenants`LEFT join `houses` ON `tenants`.`houseNumber`=`houses`.`houseID`";
   */
   $sql="select * from `tenantsView` where `tenant_status`='Active'";

    $query = mysqli_query($connection, $sql);
    
    /*******************************************************
                    introduce the admin header
    *******************************************************/
    require "admin_header0.php";

    /*******************************************************
                    Add the left panel
    *******************************************************/
    require "admin_left_panel.php";
?>

    

        <!-- Page Content -->
        <div id="page-wrapper">
            <div class="container-fluid">
                <div class="row bg-title">
                    <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                        <h4 class="page-title"> Jambo <?php echo $username;?>,</h4> </div>
                    <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12"> 
                        <ol class="breadcrumb">
                            <li><a href="index.php">Dashboard</a></li>
                            <li><a href="#" class="active">Tenants</a></li>
                            <?php if ($canManageTenants) { ?><li><a href="new-tenant.php">New</a></li><?php } ?>
                            
                        </ol>
                    </div>
                    <!-- /.col-lg-12 -->
                </div>
                <!-- /row -->
                <div class="row">
                   
                    
                    <div class="col-sm-12">
                        <div class="white-box">

                        		<?php
                                echo $error;

                                if (isset($_GET["success"])) {
                                        echo 
                                        '<div class="alert alert-success" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>DONE!! </strong><p> The new tenant has been added successfully.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["deleted"])) {
                                        echo 
                                        '<div class="alert alert-warning" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>DELETED!! </strong><p> The tenant records have been successfully deleted.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["del_error"])) {
                                        echo 
                                        '<div class="alert alert-danger" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>ERROR!! </strong><p> There was an error during the deletion of this record. Please try again.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["date_error"])) {
                                        echo
                                        '<div class="alert alert-danger" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>DATE ERROR!! </strong><p> Start date cannot be greater than end date.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["exit_date_required"]) || isset($_GET["exit_date_error"])) {
                                        echo
                                        '<div class="alert alert-danger" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>DELETE NOT COMPLETED!! </strong><p>Please correct the exit date inside the tenant delete popup and try again.</p>
                                        </div>'
                                        ;
                                    }
								?>	

                            <h3 class="box-title m-b-0">Current tenants listing ( <x style="color: orange;"><?php echo @mysqli_num_rows($query);?></x> )</h3>
                            <p class="text-muted m-b-30">Export data to Copy, CSV, Excel, PDF & Print</p>
                            <div class="table-responsive">
                                <table id="example23" class="display nowrap" cellspacing="0" width="100%">

                                    <?php 

                                    if (@mysqli_num_rows($query)==0) {
                                                    echo "<i style='color:brown;'>No Tenants to Display:( </i> ";
                                                }
                                                else{

                                                    echo '
                                                    <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>House</th>
                                                        <th>Partition</th>
                                                        <th>email</th>
                                                        <th>EmiratesID / Passport</th>
                                                        <th>Profession</th>
                                                        <th>Phone Number</th>
                                                        <th>Address</th>
                                                        <th>Home Country Address</th>
                                                        <th>Country</th>
                                                        <th>Rent</th>
                                                        <th>Start Date</th>
                                                        <th>End Date</th>
                                                        <th>Admission Date</th>
                                                        <th>Agreement</th>
                                                        '.($canManageTenants ? '<th>Actions</th>' : '').'

                                                    </tr>
                                                </thead>

                                                <tfoot>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>House</th>
                                                        <th>Partition</th>
                                                        <th>email</th>
                                                        <th>EmiratesID / Passport</th>
                                                        <th>Profession</th>
                                                        <th>Phone Number</th>
                                                        <th>Address</th>
                                                        <th>Home Country Address</th>
                                                        <th>Country</th>
                                                        <th>Rent</th>
                                                        <th>Start Date</th>
                                                        <th>End Date</th>
                                                        <th>Admission Date</th>
                                                        <th>Agreement</th>
                                                        '.($canManageTenants ? '<th>Actions</th>' : '').'
                                                    </tr>
                                                </tfoot>
                                                <tbody>
                                                    ';
                                                }

                                        while ($row = @mysqli_fetch_array($query)) {
                                            $phoneParts = split_phone_number($row["phone_number"]);
                                            $deleteModalError = '';
                                            $deleteExitValue = '';
                                            if ($deleteErrorTenantId === (int) $row["tenantID"]) {
                                                $deleteExitValue = $deleteErrorExitDate;
                                                if ($showDeleteExitDateRequired) {
                                                    $deleteModalError = 'Please enter the tenant exit date before freeing the partition.';
                                                }
                                                elseif ($showDeleteExitDateError) {
                                                    $deleteModalError = 'Exit date cannot be earlier than the tenant start date.';
                                                }
                                            }
                                            // $noOfRooms = $row["number_of_rooms"];
                                             //$hsStatus=$row['house_status'];

                                    echo '
                                    

                                        <tr>
                                            <td>
                                                '.($canManageTenants ? '
                                                <a href="#" data-toggle="modal" data-target="#edit-modal'.$row["tenantID"].'" title="Edit '.$row["tenant_name"].' \'s details" style="color:#03a9f3; font-weight:600;">
                                            '.$row["tenant_name"].'
                                                </a>
                                                ' : $row["tenant_name"]).'
                                            </td>
                                            <td>'.$row["house_name"].'</td>
                                            <td>'.$row["partition_number"].'</td>
                                            <td>'.$row["email"].'</td>
                                            <td>'.$row["ID_number"].'</td>
                                            <td>'.$row["profession"].'</td>
                                            <td>'.$row["phone_number"].'</td>
                                            <td>'.$row["tenant_address"].'</td>
                                            <td>'.$row["tenant_home_country_address"].'</td>
                                            <td>'.$row["tenant_country"].'</td>
                                            <td>'.$row["rent_amount"].'</td>
                                            <td>'.$row["start_date"].'</td>
                                            <td>'.$row["end_date"].'</td>
                                            <td>'.$row["dateAdmitted"].'</td>
                                            <td>'.$row["agreement_file"].'</td>
                                            '.($canManageTenants ? '
                                            <td>
                                            <a href="#"><i class="fa fa-trash"  data-toggle="modal" data-target="#responsive-modal'.$row["tenantID"].'" title="Delete '.$row["tenant_name"].'" style="color:red;"></i>
                                            </a>

                                            ||

                                            <a href="#"><i class="fa fa-edit"  data-toggle="modal" data-target="#edit-modal'.$row["tenantID"].'" title="Edit '.$row["tenant_name"].' \'s details " style="color:#1332d9;"></i>
                                            </a>

                                            </td>
                                            ' : '').'
                                       

                                            '.($canManageTenants ? '
                                            <!-- /.modal Delete-->
                                            <div id="responsive-modal'.$row["tenantID"].'" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="display: none;">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                                                            <h4 class="modal-title">Are you really sure you want to permanently delete 
                                                            <br><strong>'.$row["tenant_name"].'</strong>\'s record?</h4>
                                                            </div>
                                                        <div class="modal-footer">

                                                        <form action="functions/del_tenant.php" method="post">
                                                        <input type="hidden" name="tenID" value="'.
                                                        $row["tenantID"].'"/>
                                                        <input type="hidden" name="num" value="'.
                                                        $row["number_of_rooms"].'"/>
                                                        <input type="hidden" name="state" value="'.
                                                        $row["house_status"].'"/>
                                                        <input type="hidden" name="hsID" value="'.
                                                        $row["houseID"].'"/>
                                                        <input type="hidden" name="partition_id" value="'.
                                                        $row["partition_id"].'"/>
                                                        <input type="hidden" name="tenant_start_date" value="'.
                                                        $row["start_date"].'"/>
                                                        <div class="form-group text-left">
                                                            <label for="exit_date'.$row["tenantID"].'">Exit Date: *</label>
                                                            <input type="date" id="exit_date'.$row["tenantID"].'" name="exit_date" value="'.$deleteExitValue.'" class="form-control js-exit-date" data-start-date="'.$row["start_date"].'" required>
                                                            <div class="text-danger small js-exit-date-error" style="'.($deleteModalError !== '' ? '' : 'display:none;').'">'.($deleteModalError !== '' ? $deleteModalError : '').'</div>
                                                            <small class="text-muted">Required before this partition can be marked vacant.</small>
                                                        </div>
                                                            <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="deleteTenant" class="btn btn-danger waves-effect waves-light">Delete and Forget</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div> 
                                            <!-- End Modal deleted -->' : '');



                                    if ($canManageTenants) {
                                    echo'

                            <!-- Modal to edit. -->
                            <div id="edit-modal'.$row["tenantID"].'" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="display: none;">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">

                                    <div class="col-sm-12 col-xs-12">
                                    <form action="functions/del_tenant.php" 
                                    method="post">
                                        
                                        <div class="form-group">
                                            <label for="hname">Tenant Name: *</label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-pencil"></i></div>
                                                <input type="text" name="tname" class="form-control"
                                                value="'.$row["tenant_name"].'" 
                                                id="hname" placeholder="Enter tenant name" required=""> </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="tenant_country">Tenant Country: *</label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-globe"></i></div>
                                                <select required name="tenant_country" class="form-control" id="tenant_country">
                                                    '.get_country_options($row["tenant_country"]).'
                                                </select> </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="phone">Phone Number: *</label>
                                            <div class="row">
                                                <div class="col-sm-5">
                                                    <select name="phone_code" class="form-control" required>
                                                        '.get_phone_code_options($phoneParts["code"]).'
                                                    </select>
                                                </div>
                                                <div class="col-sm-7">
                                                    <input type="tel" name="phone_local"
                                                    value="'.$phoneParts["number"].'"
                                                    class="form-control" id="phone" placeholder="50 123 4567" required="">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="idnum">EmiratesID or Passport number: *</label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-user"></i></div>
                                                <input type="text" required name="idnum"
                                                    value="'.$row["ID_number"].'" 
                                                 class="form-control" id="idnum" placeholder="EmiratesID or Passport number..." > </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="tenant_address">Address: *</label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-map-marker"></i></div>
                                                <input type="text" required name="tenant_address" class="form-control"
                                                    value="'.$row["tenant_address"].'"
                                                id="tenant_address" placeholder="Current address"> </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="tenant_home_country_address">Tenant home country address: *</label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-home"></i></div>
                                                <input type="text" required name="tenant_home_country_address" class="form-control"
                                                    value="'.$row["tenant_home_country_address"].'"
                                                id="tenant_home_country_address" placeholder="Home country address"> </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="start_date">Start Date: *</label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                                                <input type="date" required name="start_date" class="form-control js-start-date"
                                                    value="'.$row["start_date"].'"
                                                id="start_date"> </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="end_date">Expected End Date:</label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                                                <input type="date" name="end_date" class="form-control js-end-date"
                                                    value="'.$row["end_date"].'"
                                                id="end_date"> </div>
                                                <small class="text-muted">Optional</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="temail">Email: *</label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-at"></i></div>
                                                <input type="email" name="temail" class="form-control" 
                                                    value="'.$row["email"].'" 
                                                id="temail" placeholder="example@co-accomodation.com" required> </div>
                                                <small class="text-muted">This stays the tenant username for login.</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="prof">Profession:</label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-briefcase"></i></div>
                                                <input type="text" name="prof" class="form-control" 
                                                    value="'.$row["profession"].'"
                                                id="prof" placeholder="e.g. Teacher"> </div>
                                        </div>

                                        <input type="text" name="ten_id" class="form-control" 
                                                    value="'.$row["tenantID"].'" hidden>

                                        <button type="submit" name="editTenant" class="btn btn-success btn-lg waves-effect waves-light m-r-10 center"><i class="fa fa-plus-circle fa-lg"></i> Update</button>
                                    </form>
                                </div>

                                </div>
                                </div>
                                </div>
                                </div>

                                        ';
                                    }


                                    echo'

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


             


                <!-- /.row -->
                                <!-- .right-sidebar -->
                <div class="right-sidebar">
                    <div class="slimscrollright">
                        <div class="rpanel-title"> Service Panel <span><i class="ti-close right-side-toggle"></i></span> </div>
                        <div class="r-panel-body">
                            <ul>
                                <li><b>Layout Options</b></li>
                                <li>
                                    <div class="checkbox checkbox-info">
                                        <input id="checkbox1" type="checkbox" class="fxhdr">
                                        <label for="checkbox1"> Fix Header </label>
                                    </div>
                                </li>
                                <li>
                                    <div class="checkbox checkbox-warning">
                                        <input id="checkbox2" type="checkbox" checked="" class="fxsdr">
                                        <label for="checkbox2"> Fix Sidebar </label>
                                    </div>
                                </li>
                                <li>
                                    <div class="checkbox checkbox-success">
                                        <input id="checkbox4" type="checkbox" class="open-close">
                                        <label for="checkbox4"> Toggle Sidebar </label>
                                    </div>
                                </li>
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
                <!-- /.right-sidebar -->
            </div>
            <?php require "admin_footer.php"; ?>
    <script>
    $(document).ready(function() {
        $('#myTable').DataTable();
        $(document).ready(function() {
            var table = $('#example').DataTable({
                "columnDefs": [{
                    "visible": false,
                    "targets": 2
                }],
                "order": [
                    [2, 'asc']
                ],
                "displayLength": 25,
                "drawCallback": function(settings) {
                    var api = this.api();
                    var rows = api.rows({
                        page: 'current'
                    }).nodes();
                    var last = null;
                    api.column(2, {
                        page: 'current'
                    }).data().each(function(group, i) {
                        if (last !== group) {
                            $(rows).eq(i).before('<tr class="group"><td colspan="5">' + group + '</td></tr>');
                            last = group;
                        }
                    });
                }
            });
            // Order by the grouping
            $('#example tbody').on('click', 'tr.group', function() {
                var currentOrder = table.order()[0];
                if (currentOrder[0] === 2 && currentOrder[1] === 'asc') {
                    table.order([2, 'desc']).draw();
                } else {
                    table.order([2, 'asc']).draw();
                }
            });
        });
    });
    $('#example23').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
    $(document).on('submit', 'form', function() {
        var startDate = $(this).find('.js-start-date').val();
        var endDate = $(this).find('.js-end-date').val();
        var exitDateField = $(this).find('.js-exit-date');
        var exitDateError = $(this).find('.js-exit-date-error');
        var exitDate = exitDateField.val();
        var exitStartDate = exitDateField.data('start-date');

        if (startDate && endDate && startDate > endDate) {
            alert('Start date cannot be greater than end date.');
            return false;
        }

        if (exitDateError.length) {
            exitDateError.hide().text('');
        }

        if (exitDateField.length && !exitDate) {
            if (exitDateError.length) {
                exitDateError.text('Please enter the tenant exit date before freeing the partition.').show();
                exitDateField.focus();
            }
            return false;
        }

        if (exitDateField.length && exitDate && exitStartDate && exitDate < exitStartDate) {
            if (exitDateError.length) {
                exitDateError.text('Exit date cannot be earlier than the tenant start date.').show();
                exitDateField.focus();
            }
            return false;
        }

        return true;
    });
    <?php if ($deleteErrorTenantId > 0 && ($showDeleteExitDateError || $showDeleteExitDateRequired)) { ?>
    $('#responsive-modal<?php echo $deleteErrorTenantId; ?>').modal('show');
    <?php } ?>
    </script>
    <!--Style Switcher -->
    <script src="../plugins/bower_components/styleswitcher/jQuery.style.switcher.js"></script>
</body>

</html>
<?php
}
else{
    header('location:index.php');
}
?>
