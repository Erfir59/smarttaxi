<?php
$drivers = PDO::getAvailableDrivers();
echo "Drivers PDO disponibles : ";
print_r($drivers);
if (in_array('mysql', $drivers)) {
    echo "\n✅ PDO MySQL OK !";
}
?>
