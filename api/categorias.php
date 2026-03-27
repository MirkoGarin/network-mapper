<?php
header('Access-Control-Allow-Origin: *');
require 'db.php';
$db = getDB();
jsonResponse($db->query('SELECT * FROM categorias ORDER BY orden')->fetchAll());
