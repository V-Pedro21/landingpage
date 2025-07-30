<?php
session_start();
session_destroy();
header("Location: profesor.php");
exit();
?>
