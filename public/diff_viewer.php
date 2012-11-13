<?php
require dirname( __DIR__)  . "/vendor/autoload.php";
/**
 * DiffViewer
 *
 * 二つのファイルをパラメータで受け取り差分を表示します
 * そのうちもうちょっとまともな見た目にするかもしれません
 */
require_once 'Text/Diff.php';
require_once 'Text/Diff/Renderer.php';
require_once 'Text/Diff/Renderer/unified.php';
require_once 'Text/Diff/Renderer/inline.php';
require_once 'Text/Diff/Renderer/context.php';

//$f1 = "/home/mrpsync/www/gacha/gacha_top.php";
//$f2 = "/home/mrp-bk/www/game/top.php";
$f1 = $_GET['f1'];
$f2 =  $_GET['f2'];

echo "$f1 と $f2 の差分です↓";
echo "<br>\n";
echo "<br>\n";
echo "----------------------------------";


if (!is_readable($f1)) {
	echo "ファイル $f1 は読み込めません。権限とかチェックしてください";
	exit();
}
if (!is_readable($f2)) {
	echo "ファイル $f1 は読み込めないかシンク先にいないみたいです";
	exit();
}


// ファイルを読み込み
$file1 = file($f1);
$file2 = file($f2);

// diifを作成
$diff = new Text_Diff('auto', array($file1, $file2));

//$renderer = new Text_Diff_Renderer();
$renderer = new Text_Diff_Renderer_unified();
//$renderer = new Text_Diff_Renderer_inline;
//$renderer = new Text_Diff_Renderer_context;

$diff_str = $renderer->render($diff);

echo "<pre>";
echo $diff_str;
echo "</pre>";
echo "----------------------------------";
