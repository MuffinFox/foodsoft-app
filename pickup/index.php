<?php
require_once __DIR__ . "/vendor/autoload.php";
$file = "usage-de.md";
// $url = "https://raw.githubusercontent.com/foodcoopsat/foodsoft-app/refs/heads/main/pickup/readme.md";
$url = "https://raw.githubusercontent.com/foodcoopsat/foodsoft-app/refs/heads/main/pickup/$file";
if ($_SERVER["HTTP_HOST"] == "localhost") {
    $url = $file;
}
$md = file_get_contents($url);
//$md = str_replace("*", "\*", $md);
$Parsedown = new Parsedown();
print "<html>";
print "<head><style>";
print "body {font: normal 16px Verdana, Arial, sans-serif;}" .
    "code{background-color: #eee;}";
print "</style></head>";
print "<body>";
print $Parsedown->text($md);
print "<hr>";
print "<p><i>" .
    "Diese Seite wird von " .
    "<a href='https://github.com/foodcoopsat/foodsoft-app/blob/main/pickup/$file'>" .
    "github.com/foodcoopsat/foodsoft-app/blob/main/pickup/$file</a> " .
    "übernommen." .
    "</i></p>";
print "</body></html>";

?>