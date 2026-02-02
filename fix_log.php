<?php
$file = 'app/Http/Controllers/Web/LeaveController.php';
$content = file_get_contents($file);
$updated = str_replace('\\Log::', 'Log::', $content);
file_put_contents($file, $updated);
echo "Fixed all \\Log:: instances\n";
?>
