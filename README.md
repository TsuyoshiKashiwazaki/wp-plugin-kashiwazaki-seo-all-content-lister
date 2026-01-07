# Kashiwazaki SEO All Content Lister

![Version](https://img.shields.io/badge/version-1.0.1-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)

WordPress管理画面で全ての投稿・固定ページ・カスタム投稿タイプを一覧表示し、ソート・フィルター・CSV出力機能を提供する管理ツールです。

## Features

- **コンテンツ一覧表示**: 全ての投稿タイプを統合して一覧表示
- **ソート機能**: ID、タイトル、投稿日、更新日などでソート可能
- **フィルター機能**: 投稿タイプ、ステータス、カテゴリーでフィルタリング
- **カラム表示切替**: 表示するカラムをカスタマイズ可能（設定はブラウザに保存）
- **CSV出力**: 一覧データをCSV形式でエクスポート（UTF-8/Shift_JIS対応）
- **被リンク調査**: サイト内の内部リンク構造を解析し、各記事への被リンク元を表示
- **リンクマップ**: D3.jsによるフォースダイレクトグラフで内部リンク構造を視覚化

## Requirements

- WordPress 5.0以上
- PHP 7.2以上

## Installation

1. プラグインフォルダを `/wp-content/plugins/` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」からプラグインを有効化
3. 左メニューに「Kashiwazaki SEO All Content Lister」が追加されます

## Usage

### コンテンツ一覧
1. 管理画面左メニューから「Kashiwazaki SEO All Content Lister」→「コンテンツ一覧」をクリック
2. フィルターで投稿タイプやステータスを絞り込み
3. カラムヘッダーをクリックしてソート
4. 「CSV出力」で一覧をエクスポート

### 被リンク調査
1. 「被リンク調査を実行」ボタンをクリック
2. バックグラウンドで全記事の内部リンクをスキャン
3. 完了後、被リンク元カラムに各記事への被リンク数が表示されます

### リンクマップ
1. 「リンクマップ」サブメニューをクリック
2. D3.jsによるネットワークグラフで内部リンク構造を視覚化
3. ノードをクリックして選択、ズーム・ドラッグで操作
4. 選択した記事のリンク元（緑）・リンク先（青）がハイライト表示

## Author

柏崎剛 (Tsuyoshi Kashiwazaki)
- Website: https://www.tsuyoshikashiwazaki.jp
- Profile: https://www.tsuyoshikashiwazaki.jp/profile/

## License

GPL v2 or later
