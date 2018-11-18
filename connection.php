<?php
$link = mysqli_connect ('localhost', 'user', 'password', 'database');

$charset = mysqli_set_charset ( $link , 'utf8mb4'); // utf8mb4 - to not have problems with smiles.
?>