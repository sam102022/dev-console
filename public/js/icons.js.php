<?php

include_once dirname(__DIR__) . '/../autoload.php';

use App\service\IconService;

$icons = IconService::getInstance()->getAll();

header('Content-Type: application/javascript');
?>
window.icon = function(key) {
function getValueByPath(path, obj) {
return path.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : path), obj);
}

const icons = <?php echo json_encode($icons, JSON_UNESCAPED_UNICODE); ?>;
return getValueByPath(key, icons);
};