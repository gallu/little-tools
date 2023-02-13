<?php
declare(strict_types=1);
error_reporting(-1);

// XXX でかいのだとメモリが足らん事があるので(実行時注意)
ini_set('memory_limit', -1);

/**
 * PHPファイル群で「どの関数がどれくらい使われているか」の解析用ツール
 */
// XXX 現在「ディレクトリ指定」のみだなぁ。「１ファイル指定」でも動くように少し作り変えよう

// ごく軽くチェック
if (false === isset($argv[1])) {
    echo "baseになるpathを引数に渡してください\n";
    echo "第二引数に --json を入れると、出力がjsonになります。\n";
    exit;
}
// basepathの取得
$base_path = $argv[1];
// ごく軽いチェック２
if (false === is_readable($base_path)) {
    echo "pathが存在しないっぽいんで確認ヨロです。\n";
    exit;
}

// json出力の有無
if ('--json' === ($argv[2] ?? '')) {
    $json_flg = true;
} else {
    $json_flg = false;
}

// 使う配列各種
$target_ex = [
    'php' => 1, 
    'inc' => 1, 
];

// マジックメソッド名の一覧
$magic_methods = [
    '__construct' => 1,
    '__destruct' => 1,
    '__call' => 1,
    '__callStatic' => 1,
    '__get' => 1,
    '__set' => 1,
    '__isset' => 1,
    '__unset' => 1,
    '__sleep' => 1,
    '__wakeup' => 1,
    '__toString' => 1,
    '__invoke' => 1,
    '__set_state' => 1,
    '__clone' => 1,
    '__debugInfo' => 1,
    // 追加分
    '__serialize' => 1,
    '__unserialize' => 1,
];

// ファイル群の取得とぶん回し
try {
    // ディレクトリを指定された時(一応、こっちが本義)
    if (true === is_dir($base_path)) {
        $obj = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base_path, 
                    FilesystemIterator::CURRENT_AS_FILEINFO
                    | FilesystemIterator::SKIP_DOTS
                    | FilesystemIterator::KEY_AS_PATHNAME
                )
            );
    } else {
        // ファイル名を指定された時(一旦、場当たり)
        $obj = [];
        $obj[$base_path] = new SplFileInfo($base_path);
    }
} catch(Exception $e) {
    echo get_class($e), "\n";
    echo $e->getMessage(), "\n";
    exit;
}

// データ集計用領域の確保
$data = [];
$global_1 = 0;
$global_2 = 0;

// 各ファイルに対してぶん回し
foreach($obj as $filename => $file_obj){
    // XXX いったん、拡張子は .php と .inc のみを扱う
    if (false === isset($target_ex[$file_obj->getExtension()])) {
        continue;
    }
//echo $filename, "\n";

    // コードとみなしてパース
    $tokens = token_get_all(file_get_contents($filename));
    // 存在している関数 または class ならインクリメント
    $flg = false;
    foreach($tokens as $token) {
        if (true === is_array($token)) {
            //  使用関数のカウント
            if (T_STRING === $token[0]) {
                // 関数名とクラス名は「大文字小文字を区別しない」ので小文字でそろえておく
                $s = strtolower($token[1]);

                // functionまたはclassとして存在するならカウント
                if (true === function_exists($s)) {
                    @$data["f:{$s}"] ++;
                } else if (true === class_exists($s)) {
                    @$data["c:{$s}"] ++;
                } else if (true === isset($magic_methods[$s])) {
                    @$data["cm:{$s}"] ++;
                }
            }
            // globalのカウント
           if (T_GLOBAL === $token[0]) {
               $global_1 ++;
           }
           if ( (T_VARIABLE === $token[0])&&('$GLOBALS' === $token[1]) ) {
               $global_2 ++;
           }
        }
    }
}
// おおざっぱにsort
arsort($data);

// おおざっぱに出力
if (true === $json_flg) {
    echo json_encode($data), "\n";
} else {
    echo "use function\n";
    foreach($data as $k => $v) {
        echo "{$k}\t{$v}\n";
    }
    echo "\nuse global\n";
    echo "global使用\t{$global_1}\n";
    echo "\$GLOBALS使用\t{$global_2}\n";
}

