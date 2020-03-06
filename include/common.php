<?php
define('MEMCACHE_LIFE_TIME',3600);//memcache缓存一小時

//载入mysql
include_once('mysql-connect.php');

//memcached
$mem = new Memcached;
$mem->addServer('memcached', 11211);

?>