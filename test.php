<?php
require_once __DIR__ . '/vendor/autoload.php';

try {
    $instance = new Itmar_AddLazyPotFile\Itmar_AddLazyPotFile();
    echo "Class loaded successfully!";
} catch (Exception $e) {
    echo "Error loading class: " . $e->getMessage();
}