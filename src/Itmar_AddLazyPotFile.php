<?php

namespace Itmar_NameSpace;


class Itmar_AddLazyPotFile
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
    $build_directory = $plugin_root_directory
      . DIRECTORY_SEPARATOR
      . 'build';
    $build_directory_iterator = new \RecursiveDirectoryIterator($build_directory);
    $build_iterator = new \RecursiveIteratorIterator($build_directory_iterator);

    $results = []; // 結果を格納する配列

    /*
    * React.lazy などの遅延ロード呼び出しを検出する。
    *
    * 対応例:
    * React.lazy((()=>r.e(123).then(...)))
    * React.lazy(()=>r.e(123).then(...))
    * React.lazy(()=>Promise.all([r.e(123), r.e(456)]).then(...))
    * M.lazy(()=>Promise.all([l.e(123), l.e(456)]).then(...))
    * (0,M.lazy)(()=>Promise.all([l.e(123), l.e(456)]).then(...))
    */
    $lazy_pattern = '/(?:(?:[A-Za-z_$][A-Za-z0-9_$]*\.)+lazy|\(\s*0\s*,\s*(?:[A-Za-z_$][A-Za-z0-9_$]*\.)*lazy\s*\))\s*\(/';

    /*
    * webpackのチャンク読込呼び出しを検出する。
    *
    * 対応例:
    * r.e(123)
    * l.e(456)
    * webpackRuntime.e(789)
    */
    $chunk_pattern = '/(?:[A-Za-z_$][A-Za-z0-9_$]*\.)+e\(\s*(\d+)\s*\)/';

    foreach ($build_iterator as $file) {
      if ($file->getFilename() !== 'index.js') {
        continue;
      }

      $content = file_get_contents($file->getRealPath());

      if ($content === false) {
        \WP_CLI::warning(
          "Unable to read index.js: {$file->getRealPath()}"
        );
        continue;
      }

      // 1つのindex.jsに複数のlazy呼び出しがある場合にも対応
      $lazy_count = preg_match_all(
        $lazy_pattern,
        $content,
        $lazy_matches,
        PREG_OFFSET_CAPTURE
      );

      if ($lazy_count === false || $lazy_count === 0) {
        continue;
      }

      // ディレクトリセパレータを正規化
      $normalized_current_dir = str_replace(
        '\\',
        '/',
        $plugin_root_directory
      );

      $normalized_file_path = str_replace(
        '\\',
        '/',
        $file->getRealPath()
      );

      // プラグインルートからの相対パス
      $relative_path = str_replace(
        $normalized_current_dir . '/',
        '',
        $normalized_file_path
      );

      for ($i = 0; $i < $lazy_count; $i++) {
        $lazy_start = $lazy_matches[0][$i][1];

        // 次のlazy呼び出しまでを現在の探索範囲とする
        $lazy_limit = isset($lazy_matches[0][$i + 1])
          ? $lazy_matches[0][$i + 1][1]
          : strlen($content);

        /*
         * webpackのdynamic importは、通常次の形で終わる。
         *
         * .then(r.bind(...))
         *
         * lazy開始位置から最初の.thenまでをチャンク探索対象にする。
         */
        $then_found = preg_match(
          '/\.then\s*\(/',
          $content,
          $then_match,
          PREG_OFFSET_CAPTURE,
          $lazy_start
        );

        if ($then_found !== 1) {
          continue;
        }

        $then_position = $then_match[0][1];

        // 次のlazy呼び出しより後のthenは対象外
        if ($then_position >= $lazy_limit) {
          continue;
        }

        $lazy_expression = substr(
          $content,
          $lazy_start,
          $then_position - $lazy_start
        );

        $chunk_count = preg_match_all(
          $chunk_pattern,
          $lazy_expression,
          $chunk_matches
        );

        if ($chunk_count === false || $chunk_count === 0) {
          continue;
        }

        foreach ($chunk_matches[1] as $chunk_id) {
          /*
             * 同じindex.js内で同じチャンクが複数回出ても、
             * 結果を重複登録しない。
             */
          $result_key = $chunk_id . '|' . $relative_path;

          $results[$result_key] = [
            'cash' => $chunk_id . '.js',
            'source' => $relative_path,
          ];
        }
      }
    }

    // 以降の既存処理で扱いやすい通常配列へ戻す
    $results = array_values($results);

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
        $pot_file_content = file_get_contents($pot_file_path);

        if ($pot_file_content === false) {
          \WP_CLI::error("Unable to read the file: {$pot_file_path}");
          return;
        }

        // 元のPOTファイルの改行コードを維持
        $newline = strpos($pot_file_content, "\r\n") !== false
          ? "\r\n"
          : "\n";

        $pot_file_lines = file(
          $pot_file_path,
          FILE_IGNORE_NEW_LINES
        );

        if ($pot_file_lines === false) {
          \WP_CLI::error("Unable to read the file: {$pot_file_path}");
          return;
        }

        $added_reference_count = 0;

        /**
         * 1つのgettextエントリーを処理する。
         *
         * 空行で区切られたmsgid単位で参照を確認するため、
         * 別のmsgidに同じindex.jsがあっても誤って省略しない。
         */
        $process_entry = function (array $entry_lines) use (
          $results,
          &$added_reference_count
        ) {
          $existing_reference_paths = [];
          $reference_basenames = [];
          $last_reference_index = null;

          foreach ($entry_lines as $index => $entry_line) {
            // ソース参照行だけを調べる
            if (strpos($entry_line, '#:') !== 0) {
              continue;
            }

            $last_reference_index = $index;

            $references = preg_split(
              '/\s+/',
              trim(substr($entry_line, 2))
            );

            if ($references === false) {
              continue;
            }

            foreach ($references as $reference) {
              if ($reference === '') {
                continue;
              }

              // build/example/index.js:1 の行番号部分を除去
              $reference_path = preg_replace(
                '/:\d+$/',
                '',
                $reference
              );

              if ($reference_path === null) {
                continue;
              }

              $reference_path = str_replace(
                '\\',
                '/',
                $reference_path
              );

              $existing_reference_paths[$reference_path] = true;
              $reference_basenames[basename($reference_path)] = true;
            }
          }

          $additional_reference_lines = [];

          foreach ($results as $result) {
            /*
         * 341.js と 1341.js を取り違えないよう、
         * 部分一致ではなくファイル名で比較する。
         */
            if (!isset($reference_basenames[$result['cash']])) {
              continue;
            }

            $source_path = str_replace(
              '\\',
              '/',
              $result['source']
            );

            // 同じmsgid内に既に参照があれば追加しない
            if (isset($existing_reference_paths[$source_path])) {
              continue;
            }

            $additional_reference_lines[$source_path]
              = '#: ' . $source_path;

            /*
         * 同じエントリー内で同じsourceが複数候補になった場合も、
         * 2回追加しないよう即時登録する。
         */
            $existing_reference_paths[$source_path] = true;
            $added_reference_count++;
          }

          if (
            count($additional_reference_lines) > 0
            && $last_reference_index !== null
          ) {
            array_splice(
              $entry_lines,
              $last_reference_index + 1,
              0,
              array_values($additional_reference_lines)
            );
          }

          return $entry_lines;
        };

        $updated_lines = [];
        $current_entry_lines = [];

        /*
        * POTは空行でmsgidごとのエントリーに分かれているため、
        * エントリー単位で処理する。
        */
        foreach ($pot_file_lines as $line) {
          if (trim($line) === '') {
            if (count($current_entry_lines) > 0) {
              $processed_lines = $process_entry(
                $current_entry_lines
              );

              foreach ($processed_lines as $processed_line) {
                $updated_lines[] = $processed_line;
              }

              $current_entry_lines = [];
            }

            // 元の空行を維持
            $updated_lines[] = '';
            continue;
          }

          $current_entry_lines[] = $line;
        }

        // ファイル末尾に空行がない場合の最後のエントリー
        if (count($current_entry_lines) > 0) {
          $processed_lines = $process_entry(
            $current_entry_lines
          );

          foreach ($processed_lines as $processed_line) {
            $updated_lines[] = $processed_line;
          }
        }

        // 追加対象がなければPOTを上書きしない
        if ($added_reference_count === 0) {
          \WP_CLI::success(
            "No new source references needed: {$pot_file_path}"
          );
          return;
        }

        $updated_content = rtrim(
          implode($newline, $updated_lines),
          "\r\n"
        ) . $newline;

        if (
          file_put_contents(
            $pot_file_path,
            $updated_content
          ) !== false
        ) {
          \WP_CLI::success(
            "Added {$added_reference_count} source reference(s): {$pot_file_path}"
          );
        } else {
          \WP_CLI::error(
            "Failed to update the file: {$pot_file_path}"
          );
        }
      } else {
        \WP_CLI::error("Unable to read the file: {$pot_file_path}");
      }
    }
  }
}
