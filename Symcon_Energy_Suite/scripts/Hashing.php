<?php
/*
    Passwort Hashing
    Programmierer: Mike Dorr
    Projekt: HVG241 Meisterprüfung
*/

$password = "BFEBFE";
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Passwort: $password\n";
echo "Hash: $hash\n";
?>