<?php
use DsSync\DsSync;
use Symfony\Component\Yaml\Parser;

require dirname( __DIR__)  . "/vendor/autoload.php";
/**
 * DsSync
 *
 * 汎用シンクプログラム
 * 使い方は同梱のREADMEをご参照ください
 *
 * @author egmc
 */
ini_set('display_errors', 1);
//-------------setting
// conf
define('DS_SYNC_CONF', dirname(__DIR__) . "/conf/" . basename(__FILE__, '.php') . ".yaml");
// テンプレート
define('DS_SYNC_TEMPLATE_DIR', dirname(__DIR__) . "/template");
define('DS_SYNC_TEMPLATE_FILE', 'dssync.html');
//-------------setting


// --------ページ用パラメータ初期化
$data = array();
// リクエスト先
$data['self'] = $_SERVER['PHP_SELF'];
$data['diff_viewer'] = "diff_viewer.php";

// バージョン情報
$data['version_str'] = "";
// フォルダリスト
$data['dir_list'] = array();
// 除外リスト（conf）
$data['conf_exclude_file_list'] = array();
// 除外リスト（post）
$data['posted_exclude_file_list'] = array();
//レポジトリ定義の有無
$data['is_repoditory_defined'] = false;
// 比較可能
$data['is_diff_enabled'] = false;
//メッセージ
$data['messages'] = array();
$data['errors']  = array();
// --------ページ用パラメータ初期化

// モード判定
$mode = "init";
if (isset($_POST['mode'])) {
	$mode = $_POST['mode'];
}

//configから取得するパラメータ
$conf = array();
$param = $_POST;

$twigenv = new Twig_Environment(new Twig_Loader_Filesystem(DS_SYNC_TEMPLATE_DIR), array('cache' => false));
$template = $twigenv->loadTemplate(DS_SYNC_TEMPLATE_FILE);

// 設定ファイル読み込み
$parser = new Parser();
$conf = $parser->parse(file_get_contents(DS_SYNC_CONF));

// メーラーをセット

// シンク部品オブジェクト生成
$dssync = new DsSync($conf, Swift_Mailer::newInstance(Swift_MailTransport::newInstance()), Swift_Message::newInstance());

// タイトル
$data['title'] = $conf['title'];

// バージョン取得
// プログラムバージョン
$data['dssync_version'] ="DsSync version " .DsSync::VERSION;
//rsync
$data['rsync_version'] = $dssync->getRsyncVersion();
$dirlist = $dssync->getDirList();
$data['is_repoditory_defined'] = $dssync->getIsRepositoryDefined();

// dirlist作成
foreach ($dirlist as $dir ) {
	$rec['name'] = $dir;
	$rec['is_checked'] = false;
	if (in_array($dir, $conf['exclude_dir_list'])) {
		$rec['is_checked'] = true;
	} else if (!empty($param['exclude_dir_list']) && in_array($dir, $param['exclude_dir_list'])) {
		$rec['is_checked'] = true;
	}
	$data['dir_list'][] =  $rec;
}

// コマンド実行
switch ($mode) {
	case "reset":
		// リセット
		header("Location: http://". $_SERVER['SERVER_NAME'] .$_SERVER['PHP_SELF']);
		exit;
		break;
	
	case "checkout":
		//レポジトリからエクスポート
		$ret  = $dssync->clearTargetDir()->svnExport();
		$server_list = $dssync->getServerList();
		$data['server_list'] = $server_list;
		
		$data['messages'][] = "レポジトリからソースを上書きしました";
		break;
	
	case "check":
		//シンクチェック
		if (isset($param['exclude_dir_list'])) {
			$dssync->addExcludeFileList($param['exclude_dir_list']);
		}
		if (isset($param['exclude_file_list'])) {
			$dssync->addExcludeFileList($param['exclude_file_list']);
		}
		
		// チェック実行＆サーバーリスト取得
		$server_list = $dssync->clearTargetDir()->checkSync()->getServerList();
		$data['server_list'] = $server_list;
		
		$file_list = $dssync->getFirstServerResultList();
		
		//ローカル判定
		if ($server_list[0]['is_local']) {
			$data['is_diff_enabled']  = true;
			$data['diff_to_prefix']  = $server_list[0]['path'];
			$data['diff_from_prefix'] = $conf['from_path'];
		}
		
		$data['file_list'] = $file_list;
		
		$data['messages'][] = "除外するソースがある場合はチェックして再度checkボタンを。シンクしてよければsyncボタンを押しましょう。";
		
		if (!$file_list['delete'] && !$file_list['sync']) {
			$data['errors'][] = "シンク対象ソースがありません";
		}
		
		break;
	case "sync":
		if (isset($param['exclude_dir_list'])) {
			$dssync->addExcludeFileList($param['exclude_dir_list']);
		}
		if (isset($param['exclude_file_list'])) {
			$dssync->addExcludeFileList($param['exclude_file_list']);
		}
		
		// シンク実行＆サーバーリスト取得
		$server_list = $dssync->clearTargetDir()->sync()->getServerList();
		$data['server_list'] = $server_list;
		$file_list = $dssync->getFirstServerResultList();
		//$log->debug($file_list);
		
		//ローカル判定
		if ($server_list[0]['is_local']) {
			$data['is_diff_enabled']  = true;
			$data['diff_to_prefix']  = $server_list[0]['path'];
			$data['diff_from_prefix'] = $conf['from_path'];
		}
		
		$data['messages'][] = "ソースの同期を実行しました！";
		
		break;
	default:
		$server_list = $dssync->getServerList();
		$data['server_list'] = $server_list;
		break;
}

// リスト設定
$data['conf_exclude_file_list'] = $conf['exclude_file_list'];
if (!empty($param['exclude_file_list'])) {
	$data['posted_exclude_file_list'] = $param['exclude_file_list'];
}

$template->display($data);