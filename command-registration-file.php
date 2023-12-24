<?php

// WP-CLIが利用可能な場合にのみ、カスタムコマンドを登録
if (class_exists('WP_CLI')) {
  WP_CLI::add_command('add_source_path', 'Itmar_NameSpace\Itmar_AddLazyPotFile');
}