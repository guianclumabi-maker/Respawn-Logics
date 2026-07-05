<?php
$res = @file_get_contents("http://127.0.0.1:8888/api/index.php?route=auth&action=current_user");
var_dump($res);
if ($res === false) {
    var_dump(error_get_last());
}
