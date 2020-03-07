<?php
include_once('include/common.php');

$data = $db->get_one_memcache("SELECT * FROM tablename where id = 123456");
print_r($data);
?>
