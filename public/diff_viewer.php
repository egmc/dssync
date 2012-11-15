<?php
/**
 * DiffViewer
 *
 * @author egmc
 */
require dirname( __DIR__)  . "/vendor/autoload.php";
ini_set('display_errors', 0);
$f1 = $_GET['f1'];
$f2 =  $_GET['f2'];

// パーミッションチェック文字列
$perm1 = "";
$perm2 = "";

$data = array();

define('DS_SYNC_TEMPLATE_DIR', dirname(__DIR__) . "/template");
define('DS_SYNC_TEMPLATE_FILE', 'diff_viewer.html');

$data['title'] = "{$f1} と {$f2} の差分";
$data['messages'] = array();
$data['errors']  = array();
$data['diff_enabled'] = false;

$twigenv = new Twig_Environment(new Twig_Loader_Filesystem(DS_SYNC_TEMPLATE_DIR), array('cache' => false));
$template = $twigenv->loadTemplate(DS_SYNC_TEMPLATE_FILE);


if (!is_readable($f1)) {
	$data['errors'][]= "ファイル{$f1}は読み込めません。権限とかチェックしてください";
}
if (!file_exists($f2)) {
	$data['errors'][]= "ファイル{$f2}は存在しません";
} else if (!is_readable($f2)) {
	$data['errors'][]= "ファイル {$f2}は読み込めません";
}

if($data['errors']) {
	$template->display($data);
	exit;
}

$data['diff_enabled'] = true;

$data['f1'] = $f1;
$data['f2'] = $f2;
$data['perm1'] =  makePermStr($f1);
$data['perm2']  =  makePermStr($f2);


// ファイルを読み込み
$file1 = file($f1);
$file2 = file($f2);

$file1 = str_replace("\r", "[CR]", $file1);
$file1 = str_replace("\n", "[LF]", $file1);
$file1 = str_replace("[LF]", "[LF]\n", $file1);

$file2 = str_replace("\r", "[CR]", $file2);
$file2 = str_replace("\n", "[LF]", $file2);
$file2 = str_replace("[LF]", "[LF]\n", $file2);

// diifる
$diff = new Text_Diff('auto', array($file2, $file1));

$renderer = new Text_Diff_Renderer_unified();

$data['diff_lines'] = array();

foreach (explode("\n", $renderer->render($diff)) as $line) {
	$type = "none";
	if (strpos($line, '+') === 0) {
		$type = "add";
	} else if (strpos($line, '-') === 0) {
		$type = "remove";
	}
	$data['diff_lines'][] = array('type' => $type, 'line' => $line);
}

$template->display($data);

/**
 * パーミッション文字列作成
 *
 * @param $file
 */
function makePermStr($file) {
	
	$perms = fileperms($file);
	
	if (($perms & 0xC000) == 0xC000) {
	    // ソケット
	    $info = 's';
	} elseif (($perms & 0xA000) == 0xA000) {
	    // シンボリックリンク
	    $info = 'l';
	} elseif (($perms & 0x8000) == 0x8000) {
	    // 通常のファイル
	    $info = '-';
	} elseif (($perms & 0x6000) == 0x6000) {
	    // ブロックスペシャルファイル
	    $info = 'b';
	} elseif (($perms & 0x4000) == 0x4000) {
	    // ディレクトリ
	    $info = 'd';
	} elseif (($perms & 0x2000) == 0x2000) {
	    // キャラクタスペシャルファイル
	    $info = 'c';
	} elseif (($perms & 0x1000) == 0x1000) {
	    // FIFO パイプ
	    $info = 'p';
	} else {
	    // 不明
	    $info = 'u';
	}
	
	// 所有者
	$info .= (($perms & 0x0100) ? 'r' : '-');
	$info .= (($perms & 0x0080) ? 'w' : '-');
	$info .= (($perms & 0x0040) ?
	            (($perms & 0x0800) ? 's' : 'x' ) :
	            (($perms & 0x0800) ? 'S' : '-'));
	
	// グループ
	$info .= (($perms & 0x0020) ? 'r' : '-');
	$info .= (($perms & 0x0010) ? 'w' : '-');
	$info .= (($perms & 0x0008) ?
	            (($perms & 0x0400) ? 's' : 'x' ) :
	            (($perms & 0x0400) ? 'S' : '-'));
	
	// 全体
	$info .= (($perms & 0x0004) ? 'r' : '-');
	$info .= (($perms & 0x0002) ? 'w' : '-');
	$info .= (($perms & 0x0001) ?
	            (($perms & 0x0200) ? 't' : 'x' ) :
	            (($perms & 0x0200) ? 'T' : '-'));
	return $info;
}
