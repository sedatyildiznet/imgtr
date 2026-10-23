<?php
require_once __DIR__.'/db.php';
function healthy_relays(bool $write=false): array {$sql='SELECT * FROM relays WHERE enabled=1 AND status IN ("healthy","slow")'.($write?' AND write_allowed=1':' AND read_allowed=1').' ORDER BY FIELD(status,"healthy","slow"),latency ASC';return db()->query($sql)->fetchAll();}
