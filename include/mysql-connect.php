<?php
include_once (dirname(__FILE__).'/../config/db.php');
include_once (dirname(__FILE__).'/../class/db_mysql.class.php');
$db = new db_mysql();
$db->connect($db_config['DB_HOST'],$db_config['DB_USER'],$db_config['DB_PWD'],$db_config['DB_NAME'],0);

?>
