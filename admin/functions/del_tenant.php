<?php 

 
require_once "db.php";
require_once "tenant_helpers.php";
require_once "partition_helpers.php";
require_once "telegram_helpers.php";

ensure_partition_tables($connection);
ensure_tenant_schema($connection);

//action to delete a tenant.
if (isset($_POST["deleteTenant"])) {
  //collecting data
	$tenid = $_POST["tenID"];
  $numberOfRooms=$_POST["num"];
  $roomId=$_POST['hsID'];
  $hsState=$_POST["state"];
  $partitionId=isset($_POST["partition_id"]) ? (int) $_POST["partition_id"] : 0;
  $exitDate=isset($_POST["exit_date"]) ? uncrack($_POST["exit_date"]) : '';
  $startDateForExit=isset($_POST["tenant_start_date"]) ? uncrack($_POST["tenant_start_date"]) : '';
  $timesnap=date('Y-m-d : H:i:s');
  $deleteErrorQuery='delete_error_tenant='.(int) $tenid.'&delete_error_exit_date='.urlencode($exitDate).'&delete_error_start_date='.urlencode($startDateForExit);

  if ($partitionId && $exitDate === '') {
    header('Location:../tenants.php?exit_date_required=1&'.$deleteErrorQuery);
    exit();
  }

  if ($startDateForExit !== '' && $exitDate !== '' && strtotime($exitDate) < strtotime($startDateForExit)) {
    header('Location:../tenants.php?exit_date_error=1&'.$deleteErrorQuery);
    exit();
  }

  //A query to soft-delete tenant while keeping history
  $sq_tenants="UPDATE `tenants` SET `tenant_status`='Deleted&Moved_Out', `exit_date`='$exitDate' WHERE `tenants`.`tenantID`='$tenid'";
  $sql_transactions="INSERT into `transactions` (`actor`,`time`,`description`)
     VALUES ('Admin ($username)', '$timesnap','$username moved out tenant ID $tenid from partition ID $partitionId with exit date $exitDate at $timesnap')";

  $mysqli ->autocommit(FALSE);
  $status =true;

      //EXECUTE QUERRIES
  $mysqli->query($sq_tenants)?null: $status=false;
  $mysqli->query($sql_transactions)?null: $status=false;

  if ($status) {
    $activePartitionResult=$mysqli->query("SELECT COUNT(*) AS total FROM `tenants` WHERE `partition_id`='$partitionId' AND `tenant_status`='Active'");
    $activePartitionRow=$activePartitionResult ? $activePartitionResult->fetch_assoc() : ['total'=>0];
    $partitionStatus=((int)$activePartitionRow['total'] > 0) ? 'Occupied' : 'Vacant';
    $mysqli->query("UPDATE `house_partitions` SET `partition_status`='$partitionStatus' WHERE `partition_id`='$partitionId'")?null: $status=false;

    $activeHouseResult=$mysqli->query("SELECT COUNT(*) AS total FROM `tenants` WHERE `houseNumber`='$roomId' AND `tenant_status`='Active'");
    $activeHouseRow=$activeHouseResult ? $activeHouseResult->fetch_assoc() : ['total'=>0];
    $houseStatus=((int)$activeHouseRow['total'] > 0) ? 'Occupied' : 'Vacant';
    $mysqli->query("UPDATE `houses` SET `house_status`='$houseStatus' WHERE `houseID`='$roomId'")?null: $status=false;
  }
	

if ($status) {
                  #successful, commit changes
                  $mysqli ->commit();

                        //head to index and report as an error state.
                   header('Location:../tenants.php?deleted');
                   exit();
              }
            else
              {
                      #rollback changes
                    $mysqli -> rollback();
                    //return back to page with error state
                    header('Location:../tenants.php?del_error');
                    exit();
              }

}
//Request to update a tenant record
if (isset($_POST["editTenant"])) {
  //collect the data
  $tenid = uncrack($_POST["ten_id"]);
  $tname=is_username($_POST['tname']);
  $firstName=substr($tname, 0,strpos($tname, ' ')); //first name
  $temail=is_email($_POST['temail']);
  $idnum=uncrack($_POST['idnum']);
  $phoneCode=uncrack($_POST['phone_code']);
  $phoneLocal=uncrack($_POST['phone_local']);
  $phone=trim($phoneCode.' '.$phoneLocal);
  $prof=is_username($_POST['prof']);
  $telegramUsername=normalize_telegram_username(isset($_POST['telegram_username']) ? $_POST['telegram_username'] : '');
  $telegramChatId=normalize_telegram_chat_id(isset($_POST['telegram_chat_id']) ? $_POST['telegram_chat_id'] : '');
  $tenantAddress=uncrack($_POST['tenant_address']);
  $tenantHomeCountryAddress=uncrack($_POST['tenant_home_country_address']);
  $tenantCountry=is_username($_POST['tenant_country']);
  $startDate=uncrack($_POST['start_date']);
  $endDate=uncrack($_POST['end_date']);

  if ($endDate !== '' && strtotime($startDate) > strtotime($endDate)) {
    header('Location:../tenants.php?date_error=1');
    exit();
  }

  $timesnap=date('Y-m-d : H:i:s');

  //prepare SQL queries

  //update the tenant
  $sq_tenant="
        UPDATE `tenants` SET 
        `tenant_name` = '$tname', 
        `email` = '$temail', 
        `ID_number` = '$idnum', 
        `phone_number` = '$phone', 
        `telegram_username` = '$telegramUsername',
        `telegram_chat_id` = '$telegramChatId',
        `profession` = '$prof',
        `tenant_address` = '$tenantAddress',
        `tenant_home_country_address` = '$tenantHomeCountryAddress',
        `tenant_country` = '$tenantCountry',
        `start_date` = '$startDate',
        `end_date` = '$endDate'
        WHERE `tenants`.`tenantID` = '$tenid'";

  //report the update
    $sql_transactions="INSERT into `transactions` (`actor`,`time`,`description`)
     VALUES ('Admin ($username)', '$timesnap','$username updated tenant details for ($tname) at $timesnap')";

  //begin transactions
      $mysqli -> autocommit(FALSE);
      $status =true;

      //EXECUTE INDIVIDUAL QUERRIES
      $mysqli->query($sq_tenant)?null: $status=false;
      if ($status && !ensure_tenant_user_account($connection, (int) $tenid, $tname, $temail, $phone)) {
        $status=false;
      }
      $mysqli->query($sql_transactions)?null: $status=false;

      //check if successful
      if ($status) {
        $mysqli ->commit();

        //head to index and report as an error state.
        header('Location:../tenants.php?state=12');
        exit();
      }
      else
      {
        //rollback changes
        $mysqli -> rollback();
        //return back to page with error state
        header('Location:../tenants.php?state=13');
        exit();
      }
}
else {
	header('Location:../tenants.php?del_error');
	exit();
}

	

?>
