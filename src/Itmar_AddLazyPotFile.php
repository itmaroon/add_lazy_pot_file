<?php
namespace Itmar_AddLazyPotFile;

if (defined('WP_CLI') && WP_CLI) {
  class Itmar_AddLazyPotFile extends WP_CLI_Command {
    public function __invoke( $args, $assoc_args ) {
    }
  }
  WP_CLI::add_command('add_source_path', 'Itmar_AddLazyPotFile\Itmar_AddLazyPotFile');
}