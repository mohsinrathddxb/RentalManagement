<?php 

 
require_once "db.php";

session_start();
require_admin_user();

if (isset($_POST["id"])) {

	$id = $_POST["id"];

	$sql = "DELETE FROM `houses` WHERE houseID=?";

$stmt = $db->prepare($sql);


    try {
      $stmt->execute([$id]);
      header('Location:../houses.php?deleted');

      }

     catch (Exception $e) {
        $e->getMessage();
        echo "Error";
    }

}
else {
	header('Location:../houses.php?del_error');
}

	

?>
