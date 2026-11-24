```php
<?php

return [

    /* ==================== Headings ==================== */
    'title' => '言語管理',
    'menu_title' => '言語設定',
    'list' => '言語一覧',
    'create' => '新しい言語を追加',
    'create_first' => '最初の言語を追加',
    'edit' => '言語を編集',
    'edit_language' => '言語を編集: :name',
    'details' => '言語の詳細',
    'home' => 'ホーム',
    'settings' => '設定',
    'empty' => '現在のフィルターに一致する言語はありません',

    /* ==================== Form sections ==================== */
    'basic_info' => '基本情報',
    'regional_settings' => '地域設定',
    'appearance' => '外観とフォント',
    'status_options' => 'ステータス設定',

    /* ==================== Fields ==================== */
    'id' => 'ID',
    'name' => '言語名',
    'native_name' => 'ネイティブ名',
    'code' => '言語コード',
    'locale' => 'ロケール',
    'flag' => '国旗',
    'direction' => '文字方向',
    'sort_order' => '表示順',
    'status' => 'ステータス',
    'default' => 'デフォルト',
    'translation' => '翻訳',
    'translation_file' => '翻訳ファイル',
    'date_format' => '日付形式',
    'time_format' => '時刻形式',
    'number_format' => '数値形式',
    'timezone' => 'タイムゾーン',
    'calendar_type' => '暦の種類',
    'thousands_separator' => '桁区切り記号',
    'decimal_separator' => '小数点記号',
    'font_family' => 'フォント',
    'font_size' => 'フォントサイズ',
    'color' => 'アクセントカラー',
    'description' => '説明',
    'is_active' => '有効な言語',
    'is_default' => 'システムのデフォルト言語',
    'created_at' => '作成日時',
    'updated_at' => '最終更新日時',

    /* ==================== Values ==================== */
    'ltr' => '左から右 (LTR)',
    'rtl' => '右から左 (RTL)',
    'active' => '有効',
    'inactive' => '無効',
    'all' => 'すべて',
    'all_statuses' => 'すべてのステータス',
    'all_directions' => 'すべての文字方向',
    'uploaded' => 'アップロード済み',
    'none' => 'なし',
    'file_present' => 'サーバー上にファイルがあります',
    'file_missing' => 'サーバー上にファイルがありません',
    'no_translation_file' => 'この言語には翻訳ファイルがアップロードされていません。',
    'current_file' => '現在のファイル',
    'replace_translation' => '翻訳ファイルを置き換える',
    'upload_translation' => '翻訳ファイルをアップロード',

    /* ==================== Hints ==================== */
    'search' => '検索',
    'search_placeholder' => '名前、コード、またはロケールで検索',
    'code_hint' => 'ar、en、ur などの短いコードを入力してください。使用できるのは英字、数字、ハイフンのみです。',
    'locale_hint' => '空欄の場合は、言語コードが自動的に使用されます。',
    'flag_hint' => 'public/flags 内の国旗ファイル名（例: sa.svg）または絵文字を指定してください。',
    'translation_hint' => 'JSONファイルのみ、最大2 MB。resources/lang/{code}.json に保存されます。',
    'name_placeholder' => '例: 日本語',
    'native_name_placeholder' => '例: 日本語',
    'code_placeholder' => '例: ja',

    'default_language_note' => '別の言語をデフォルトに設定するまで、デフォルト言語を削除または無効にすることはできません。',

    'guidelines_title' => '言語ページのガイドライン',
    'guidelines' => [
        'ここですべてのシステム言語を管理できます：追加、編集、削除、ステータスの切り替え。',
        'デフォルト言語は1つだけ設定できます。新しい言語をデフォルトに設定すると、他の言語のデフォルト設定は自動的に解除されます。',
        '別の言語をデフォルトに設定するまで、デフォルト言語を削除または無効にすることはできません。',
        '無効化された言語はインターフェースの言語切り替えメニューには表示されませんが、データは保持されます。',
    ],

    /* ==================== Stats ==================== */
    'stats' => [
        'total' => '言語の総数',
        'active' => '有効な言語',
        'inactive' => '無効な言語',
        'rtl' => 'RTL言語',
    ],

    /* ==================== Actions ==================== */
    'actions' => '操作',
    'show' => '表示',
    'filter' => '検索',
    'reset' => 'リセット',
    'cancel' => 'キャンセル',
    'save' => '保存',
    'back' => '戻る',
    'delete' => '削除',
    'activate' => '有効化',
    'deactivate' => '無効化',
    'set_as_default' => 'デフォルトに設定',
    'default_requires_active' => '既定にする前に、この言語を有効にしてください。',
    'is_the_default' => 'デフォルト言語',
    'confirm_delete' => 'この言語を削除してもよろしいですか？',
    'confirm_set_default' => 'この言語をシステムのデフォルト言語に設定しますか？',
    'advanced' => '詳細検索',

    /* ==================== Messages ==================== */
    'messages' => [
        'created' => '言語「:name」を正常に作成しました。',
        'updated' => '言語「:name」を正常に更新しました。',
        'deleted' => '言語を正常に削除しました。',
        'status_changed' => '言語のステータスを正常に変更しました。',
        'set_default' => '「:name」がシステムのデフォルト言語になりました。',
        'default_saved' => 'デフォルト言語を正常に保存しました！',
        'unexpected_error' => '予期しないエラーが発生しました。もう一度お試しください。',
        'validation_errors' => '次のエラーを修正してください：',
    ],

    /* ==================== Validation ==================== */
    'validation' => [
        'code_unique' => 'この言語コードは別の言語ですでに使用されています。',
        'code_format' => '言語コードには英字、数字、ハイフンのみ使用できます。',
        'name_unique' => 'この言語名はすでに使用されています。',
        'locale_unique' => 'このロケールは別の言語ですでに使用されています。',
        'locale_format' => 'ロケールには英字、数字、ハイフンのみ使用できます。',
        'direction_in' => '文字方向は rtl または ltr のいずれかである必要があります。',
        'translation_file_json' => '翻訳ファイルは有効なJSONファイルである必要があります。',
    ],
];