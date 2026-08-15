# add_lazy_pot_file

WordPress プラグインの遅延ロードされた JavaScript チャンクについて、呼び出し元のソース参照を POT ファイルへ追加する WP-CLI コマンドです。

登録されるコマンドは次のとおりです。

```console
wp add_source_path <text-domain>
```

## このツールを使用する場面

Gutenberg ブロックなどで `React.lazy()` と dynamic `import()` を使用すると、ビルド後の JavaScript は次のように分割されます。

- 遅延ロードを呼び出す `index.js`
- 翻訳対象の文字列を含む `123.js` などのチャンクファイル

`wp i18n make-pot` が生成する POT ファイルでは、翻訳文字列のソース参照がチャンクファイルだけになる場合があります。その状態から JavaScript 翻訳用 JSON を生成すると、WordPress が実際にスクリプトを読み込む際のパスと翻訳データの対応が取れず、翻訳が表示されないことがあります。

このコマンドは、チャンクファイルを参照している各 gettext エントリーに、そのチャンクを呼び出している `index.js` の相対パスを追加します。

例えば、次の参照を含むエントリーがある場合、

```pot
#: build/123.js:1
```

呼び出し元を検出して次のようにします。

```pot
#: build/123.js:1
#: build/blocks/example/index.js
```

既に同じ参照がある場合は追加しないため、コマンドを複数回実行しても同じパスは重複しません。

## 対応しているビルド形式

ビルドツールによって `React` や webpack ランタイムの変数名が置き換えられても検出できるよう、次のような形式に対応しています。

```js
React.lazy(() => r.e(123).then(/* ... */));
React.lazy((() => r.e(123).then(/* ... */)));
React.lazy(() => Promise.all([r.e(123), r.e(456)]).then(/* ... */));
M.lazy(() => Promise.all([l.e(123), l.e(456)]).then(/* ... */));
(0, M.lazy)(() => Promise.all([l.e(123), l.e(456)]).then(/* ... */));
```

具体的には次を処理します。

- `React.lazy` のほか、`M.lazy` などに短縮・置換された識別子
- `(0,M.lazy)(...)` 形式
- `.e(123)` 形式の数値チャンク ID
- `Promise.all(...)` 内で読み込まれる複数チャンク
- 1つの `index.js` に含まれる複数の lazy 呼び出し

現在の探索範囲と前提は次のとおりです。

- 対象プラグインの `build` ディレクトリを再帰的に探索します。
- ファイル名が `index.js` のファイルを調べます。
- lazy 呼び出しから最初の `.then(...)` までにある `.e(<数値>)` をチャンクとして扱います。
- 対応するチャンクファイル名は `<数値>.js` であることを前提とします。
- `341.js` と `1341.js` は部分一致ではなく、別のファイル名として判定します。

上記と異なるランタイム表現、数値以外のチャンク名、または `index.js` 以外のエントリーファイルは現在の検出対象外です。

## 必要条件

- WP-CLI がインストールされ、`wp` コマンドを実行できること
- `wp package` サブコマンドを利用できること
- PHP 7.4 以上
- 対象が WordPress にインストールされたプラグインであること（有効化は不要です）
- プラグインヘッダーの `Text Domain` が設定されていること
- 対象プラグインにビルド済みの `build` ディレクトリがあること

WP-CLI 自体のインストール方法については、[WP-CLI の公式ドキュメント](https://make.wordpress.org/cli/handbook/guides/installing/)を参照してください。

## インストール

### 安定版

現在の安定版 `v1.1.0` は、次のコマンドでユーザーの WP-CLI 環境へインストールできます。このコマンドはWordPressサイト内ではなく、任意のディレクトリで実行できます。

```console
wp package install "https://github.com/itmaroon/add_lazy_pot_file/archive/refs/tags/v1.1.0.zip"
```

新しいバージョンが公開されている場合は、URL内の `v1.1.0` をそのタグへ置き換えてください。

WP-CLI 2.12.0 では、Packagist に公開されていても次の短縮名によるインストールが `shortened identifier not found` で失敗する場合があります。

```console
wp package install "itmar/add_lazy_pot_file:@stable"
```

その場合は、上記のGitHubタグZIPを指定する方法を使用してください。

### 開発版

既定ブランチの最新版を使用する場合は、Gitリポジトリを指定できます。

```console
wp package install "https://github.com/itmaroon/add_lazy_pot_file.git"
```

インストール結果を確認します。

```console
wp package list
wp help add_source_path
```

通常、パッケージはユーザーごとの `~/.wp-cli/packages/` にインストールされ、特定のWordPressサイト内にはコピーされません。一度インストールすれば、そのユーザーが操作できる複数のWordPressサイトで使用できます。

以前に `config.yml` の `require:` やプロジェクトの `wp-cli.yml` で `command-registration-file.php` を読み込んでいた場合は、その設定を削除してください。パッケージからの自動読み込みと重なると、コマンドが二重登録される可能性があります。

## 実行方法

### 1. POTファイルを生成する

対象プラグインのルートディレクトリで、先に `wp i18n make-pot` を実行します。

```console
wp i18n make-pot ./ languages/block-collections.pot --exclude="node_modules*/**"
```

POTファイル名は必ず次の形式にします。

```text
<text-domain>.pot
```

プラグインヘッダーの `Text Domain`、POTファイル名、次の手順で渡す引数は完全に一致させてください。

### 2. 遅延ロード元の参照を追加する

同じWordPress環境内で、Text Domainを引数にして実行します。

```console
wp add_source_path block-collections
```

ここで `block-collections` はプラグインのディレクトリ名ではなく、プラグインヘッダーに設定された `Text Domain` です。コマンドはそのText Domainを持つプラグインを検出し、プラグイン内から `block-collections.pot` を再帰的に検索して更新します。

### 対象サイトの指定方法

現在位置とは別のWordPressサイトを明示する場合は、WP-CLIの `--path` を使用します。

```console
wp --path="C:/path/to/wordpress" add_source_path block-collections
```

WP-CLIのエイリアスを設定している場合も使用できます。PowerShellでは `@develop` をクォートしてください。

```powershell
wp '@develop' add_source_path block-collections
```

エイリアスの例は次のとおりです。

```yaml
@develop:
  path: "C:/path/to/wordpress"
```

引数なしの `wp add_source_path ...` は、現在位置からWP-CLIが認識したWordPress環境を対象にします。WordPressとは無関係なディレクトリで実行した場合は、`This does not seem to be a WordPress installation` というエラーになります。

### 実行結果

参照を追加した場合は、追加数とPOTファイルのパスが表示されます。

```text
Success: Added 2 source reference(s): .../languages/block-collections.pot
```

既に必要な参照がすべて存在する場合は、POTファイルを上書きしません。

```text
Success: No new source references needed: .../languages/block-collections.pot
```

lazy呼び出しまたは対応するチャンクを検出できない場合は、次のように表示されます。

```text
No lazy function.
```

## 基本的な作業順序

```console
wp i18n make-pot ./ languages/block-collections.pot --exclude="node_modules*/**"
wp add_source_path block-collections
```

この処理で更新したPOTファイルを、通常の翻訳ファイル作成とJavaScript翻訳用JSON生成の工程に使用してください。`wp i18n make-pot` を再実行するとPOTファイルが作り直されるため、その後は `add_source_path` も再実行します。

## アンインストール

```console
wp package uninstall itmar/add_lazy_pot_file
```

## Support

不具合や要望は [GitHub Issues](https://github.com/itmaroon/add_lazy_pot_file/issues) へ報告してください。
