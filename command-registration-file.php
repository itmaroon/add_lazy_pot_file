<?php

if (!defined('WP_CLI') || !WP_CLI) {
  return;
}

require_once __DIR__ . '/src/Itmar_AddLazyPotFile.php';

WP_CLI::add_command(
  'add_source_path',
  'Itmar_NameSpace\Itmar_AddLazyPotFile'
);
