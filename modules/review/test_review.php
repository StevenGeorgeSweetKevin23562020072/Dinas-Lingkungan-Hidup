<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test Review Module<br>";
echo "PHP Version: " . phpversion() . "<br>";

require_once '../../includes/auth.php';
echo "Auth loaded ✓<br>";

require_once '../../includes/functions.php';
echo "Functions loaded ✓<br>";

echo "Current User: ";
print_r(getCurrentUser());

echo "<br><br>Database Connection: ";
if ($conn) {
    echo "Connected ✓";
} else {
    echo "Failed ✗";
}

echo "<br><br><a href='pending.php'>Go to Pending</a>";
?>