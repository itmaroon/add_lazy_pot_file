<?php

namespace Itmar_NameSpace;

if (defined('WP_CLI') && WP_CLI) {
  class Itmar_AddLazyPotFile extends \WP_CLI_Command
  {
    public function __invoke($args, $assoc_args)
    {
      // 引数からText Domainを取得
      list($text_domain) = $args;

      //Text Domainからプラグインのルートフォルダを検出する
      $all_plugins = get_plugins(); //すべてのプラグイン情報
      //フラグ等の初期化
      $found = false;
      $plugin_root_directory = '';

      foreach ($all_plugins as $plugin_path => $plugin_data) {
        if (isset($plugin_data['TextDomain']) && $plugin_data['TextDomain'] === $text_domain) {
          // プラグインのルートフォルダを見つけた
          $plugin_root_directory = WP_PLUGIN_DIR . '/' . dirname($plugin_path);
          \WP_CLI::line("Plugin directory found: {$plugin_root_directory}");
          $found = true;
          break;
        }
      }

      if (!$found) { //検出できなければ終了
        \WP_CLI::error("Plugin with text domain '{$text_domain}' not found.");
        die();
      }

      // buildディレクトリ内のindex.jsからReact.lazyで遅延読込しようとしているファイル名を検出
      $build_directory = $plugin_root_directory . '\build';
      $build_directory_iterator = new \RecursiveDirectoryIterator($build_directory);
      $build_iterator = new \RecursiveIteratorIterator($build_directory_iterator);

      $results = []; // 結果を格納する配列

      foreach ($build_iterator as $file) {
        if ($file->getFilename() === 'index.js') {

          // index.js ファイルの内容を読み込む
          $content = file_get_contents($file->getRealPath());
          $pattern = '/React\.lazy\(\(\(\*?\)=>(?:Promise\.all\(\[(.*?)\]\)|[a-z]\.e\((\d+)\))/';

          if (preg_match($pattern, $content, $matches)) {
            // ディレクトリセパレータを正規化
            $normalizedCurrentDir = str_replace('\\', '/', $plugin_root_directory);
            $normalizedFilePath = str_replace('\\', '/', $file->getRealPath());

            // 相対パスを計算
            $relative_path = str_replace($normalizedCurrentDir . '/', '', $normalizedFilePath);

            // Promise.allのケースとr.eの直接のケースを区別
            if (!empty($matches[2])) { // r.e(数字)のパターンの場合
              $results[] = [
                'cash' => trim($matches[2]) . '.js',
                'source' => $relative_path
              ];
            } else if (!empty($matches[1])) { // Promise.allのケース
              $items = explode(',', $matches[1]);
              foreach ($items as $item) {
                if (preg_match('/[a-z]\.e\((\d+)\)/', $item, $itemMatches)) {
                  $results[] = [
                    'cash' => trim($itemMatches[1]) . '.js',
                    'source' => $relative_path
                  ];
                }
              }
            }
          }
        }
      }

      //lazyロードファイルがなければ処理終了
      if (count($results) == 0) {
        \WP_CLI::line("No lazy function.");
        die(); // スクリプトを終了
      }

      //potファイルを検索して開く
      $pot_file_name = $text_domain . '.pot'; // 拡張子を追加

      $directory_iterator = new \RecursiveDirectoryIterator($plugin_root_directory, \RecursiveDirectoryIterator::SKIP_DOTS);
      $iterator = new \RecursiveIteratorIterator($directory_iterator);

      $found = false;

      foreach ($iterator as $file) {
        if ($file->getFilename() === $pot_file_name) {
          $content = file_get_contents($file->getRealPath());
          $found = true;
          break;
        }
      }

      if (!$found) {
        \WP_CLI::error("File not found: {$pot_file_name}");
      } else {
        // ここでの確認は、$file が正しいファイルを指しているかどうかを検証するため
        $pot_file_path = $file->getRealPath();

        if (file_exists($pot_file_path) && is_readable($pot_file_path)) {
          $pot_file_lines = file($pot_file_path, FILE_IGNORE_NEW_LINES); // 改行を含めずに行を読み込む
          $updated_lines = [];

          foreach ($pot_file_lines as $line) {
            $updated_lines[] = $line;

            foreach ($results as $result) {
              if (strpos($line, $result['cash']) !== false) {
                // cash 値を含む行の下に source 値を挿入
                $updated_lines[] = '#: ' . $result['source'];
              }
            }
          }
          // 変更後の内容でファイルを上書き
          if (file_put_contents($pot_file_path, implode("\n", $updated_lines)) !== false) {
            \WP_CLI::success("File successfully updated: {$pot_file_path}");
          } else {
            \WP_CLI::error("Failed to update the file: {$pot_file_path}");
          }
        } else {
          \WP_CLI::error("Unable to read the file: {$pot_file_path}");
        }
      }
    }
  }
}
