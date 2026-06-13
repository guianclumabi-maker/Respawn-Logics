<?php
require "bootstrap/app.php";
echo json_encode(PermissionService::userPermissions($pdo, 796, "1"));
