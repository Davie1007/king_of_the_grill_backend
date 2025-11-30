<?php
$target = '/home/kingofth/lavarel/storage/app/public';  // The real folder
$link = '/home/kingofth/lavarel/public/storage';         // The link to create

if (file_exists($link)) {
    echo "The link already exists at: $link";
} else {
    if (symlink($target, $link)) {
        echo "✅ Symbolic link created successfully.<br>";
        echo "Link: $link<br>";
        echo "Target: $target<br>";
    } else {
        echo "❌ Error creating symbolic link. Check file permissions or paths.<br>";
        echo "Target: $target<br>";
        echo "Link: $link<br>";
        echo "PHP User: " . get_current_user();
    }
}
?>
