<?php
include_once('include/common.php');

$data = $db->get_one_memcache("SELECT * FROM cms_item_news where item_id = 2282490");
print_r($data);
?>