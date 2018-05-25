<?php

/**
 * PHPファイル群で「どの関数がどれくらい使われているか」の解析用ツール
 */
// XXX 現在「ディレクトリ指定」のみだなぁ。「１ファイル指定」でも動くように少し作り変えよう

// ごく軽くチェック
if (false === isset($argv[1])) {
    echo "baseになるpathを引数に渡してください\n";
    exit;
}
// basepathの取得
$base_path = $argv[1];
// ごく軽いチェック２
if (false === is_readable($base_path)) {
    echo "pathが存在しないっぽいんで確認ヨロです。\n";
    exit;
}

// データ集計用領域の確保
$data = [];
$global = 0;

// 使う配列各種
$target_ex = [
    'php' => 1, 
    'inc' => 1, 
];
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
];

// ファイル群の取得とぶん回し
$obj = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base_path, 
            FilesystemIterator::CURRENT_AS_FILEINFO
            | FilesystemIterator::SKIP_DOTS
            | FilesystemIterator::KEY_AS_PATHNAME
        )
    );


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
                // functionまたはclassとして存在するならカウント
                if (true === function_exists($token[1])) {
                    @$data["f:{$token[1]}"] ++;
                } else if (true === class_exists($token[1])) {
                    @$data["c:{$token[1]}"] ++;
                } else if (true === isset($magic_methods[$token[1]])) {
                    @$data["cm:{$token[1]}"] ++;
                }
            }
            // globalのカウント
           if ( (T_GLOBAL === $token[0])||( (T_VARIABLE === $token[0])&&('$GLOBALS' === $token[1]) ) ) {
               $global ++;
           }
        }
    }
}
// おおざっぱにsort
arsort($data);

// おおざっぱに出力
echo "use function\n";
var_dump($data);
echo "\nuse global\n";
var_dump($global);

