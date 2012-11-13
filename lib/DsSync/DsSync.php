<?php
namespace DsSync;
/**
 * DsSync
 *
 * DsSyncメインクラス
 *
 * @author egmc
 */
class DsSync {
	
	// DsSyncバージョン
	const VERSION = "1.5";
	
	//rsyncコマンド
	private $rsync_cmd = "rsync";
	// シンク元パス
	private $from_path = "";
	//デフォルトオプション
	private $default_opptions = " -vuzac --delete";
	
	// -----------svninfo
	// svnコマンド
	private $svn_cmd = "svn export";
	// svnレポジトリ
	private $svn_repository;
	// svnユーザー
	private $svn_user;
	// svnパス
	private $svn_password;
	// svnターゲットパス、未定義時は$from_pathが使用される
	private $svn_target_path;
	// -----------svninfo
	
	// サーバーリスト
	private $server_list = array();
	
	// レポジトリ定義チェック
	private $is_repository_defined = false;
	
	// 除外フォルダリスト
	private $exclude_dir_list = array();
	
	// 除外ファイルリスト
	private $exclude_file_list = array();
	
	// クリアするフォルダリスト
	private $clear_dir_list = array();
	
	// クリアコマンド
	private $clear_cmd = "rm -fr %s";
	
	// コマンド実行結果一時格納用変数
	private $tmp_cmd_result = "";
	
	// シンク対象フォルダリスト
	private $sync_dir_list = array();
	
	// svn（export）デフォルトオプション
	private $svn_export_default_options = " --no-auth-cache --force --config-option servers:global:use-commit-times=yes  --native-eol LF";
	
	// メール通知関連---------
	// to_list
	private $mail_to_list = array();
	// from
	private $mail_from = "";
	// タイトル
	private $mail_subject = "sync result";
	
	/**
	 * mailer
	 *
	 * @var \Swift_Mailer
	 */
	protected $mailer = null;
	/**
	 *mail_message
	 *
	 * @var \Swift_Message
	 */
	protected $message = null;
	
	/**
	 * コンストラクタ
	 *
	 * @param array $options yamlより取得したオプションの配列
	 * @param $mailer (optional)メーラーオブジェクト（通知を使用する場合）
	 */
	public function __construct(array $options, \Swift_Mailer $mailer = null, \Swift_Message  $message = null) {
		
		if ($mailer !== null) {
			$this->mailer = $mailer;
		}
		if ($message !== null) {
			$this->message = $message;
		}
		$this->setOptions($options);

	}
	
	/**
	 * オプションのセット、チェック処理
	 *
	 * @param array $options
	 */
	private function setOptions($options) {
		
		// オプションセット
		if (isset($options['rsync_cmd'])) {
			$this->rsync_cmd = $options['rsync_cmd'];
		}
		
		// シンク元情報セット
		if (!isset($options['from_path'])) {
			throw new DsSyncConfException("[from_path]が設定されてません");
		}
		$this->from_path = $options['from_path'];
		
		
		// レポジトリ情報セット---------------------------
		if (isset($options['svn_repository'])) {
			$this->svn_repository = $options['svn_repository'];
			$this->is_repository_defined = true;
		}
		// コマンド
		if (isset($options['svn_cmd'])) {
			$this->svn_cmd = $options['svn_cmd'];
		}
		// ユーザー
		if (isset($options['svn_user'])) {
			$this->svn_user = $options['svn_user'];
		}
		// パス
		if (isset($options['svn_password'])) {
			$this->svn_password = $options['svn_password'];
		}
		
		// svn_export_default_options
		if (isset($options['svn_export_default_options'])) {
			$this->svn_export_default_options  = $options['svn_export_default_options'];
		}
		
		// svnエクスポート先パス
		if (isset($options['svn_target_path'])) {
			$this->svn_target_path = $options['svn_target_path'];
		} else {
			$this->svn_target_path  =$this->from_path;
		}
		
		
		// 除外フォルダリスト
		if (isset($options['exclude_dir_list'])) {
			if (is_array($options['exclude_dir_list'])) {
				$this->exclude_dir_list = $options['exclude_dir_list'];
			} else {
				throw new DsSyncConfException("[exclude_dir_list]は配列で定義してね");
			}
		}
		// 除外ファイルリスト
		if (isset($options['exclude_file_list'])) {
			if (is_array($options['exclude_file_list'])) {
				$this->exclude_file_list = $options['exclude_file_list'];
			} else {
				throw new DsSyncConfException("[exclude_file_list]は配列で定義してね");
			}
		}
		// ローカルリスト
		if (isset($options['local_list']) && is_array($options['local_list']) ) {
			foreach ($options['local_list'] as $local) {
				$server = $local;
				$server['is_local'] = true;
				$server['sync_command'] = "";
				$server['response'] = "";
				
				if (!isset($local['path'])) {
					throw new DsSyncConfException("[local_list:path]が指定されてません");
				}
				if (!empty($local['rsync_options'])) {
					$server['rsync_options'] = $local['rsync_options'];
				} else {
					$server['rsync_options'] = $this->default_opptions;
				}
				$this->server_list[] = $server;
			}
		}
		//リモートリスト
		if (isset($options['remote_list']) && is_array($options['remote_list']) ) {
			foreach ($options['remote_list'] as $remote) {
				$server = $remote;
				$server['is_local'] = false;
				$server['sync_command'] = "";
				$server['response'] = "";
				
				if (!isset($remote['path'])) {
					throw new DsSyncConfException("[local_list:path]が指定されてません");
				}
				if (!empty($remote['rsync_options'])) {
					$server['rsync_options'] = $remote['rsync_options'];
				} else {
					$server['rsync_options'] = $this->default_opptions;
				}
				$this->server_list[] = $server;
			}
		}
		
		if (count($this->server_list) == 0) {
			throw new DsSyncConfException("シンク先が定義されていません");
		}
		
		// クリアフォルダリスト
		if (isset($options['clear_dir_list'])) {
			if (is_array($options['clear_dir_list'])) {
				$this->clear_dir_list = $options['clear_dir_list'];
			} else {
				throw new DsSyncConfException("[clear_dir_list]は配列で定義してね");
			}
		}
		
		// クリアコマンド
		if (isset($options['clear_cmd'])) {
			$this->clear_cmd = $options['clear_cmd'];
		}
		
		// シンク対象リスト
		if (isset($options['sync_dir_list'])) {
			if (is_array($options['sync_dir_list'])) {
				$this->sync_dir_list = $options['sync_dir_list'];
			} else {
				throw new DsSyncConfException("[sync_dir_list]は配列で定義してね");
			}
		}
		
		//メール関連オプション
		
		// 送信先
		if (isset($options['mail_to_list'])) {
			if (is_array($options['mail_to_list'])) {
				$this->mail_to_list = $options['mail_to_list'];
			} else {
				throw new DsSyncConfException("[mail_to_list]は配列で定義してね");
			}
			// メーラーがセットされてない
			if ($this->mailer === null) {
				throw new DsSyncException("[mail_to_list]が定義されていますが、メーラーがセットされていません");
			}
		}
		// from
		if (isset($options['mail_from'])) {
			$this->mail_from = $options['mail_from'];
		}
		
		// subject
		if (isset($options['mail_subject'])) {
			$this->mail_subject = $options['mail_subject'];
		}
		
	}
	
	/**
	 * rsyncバージョン文字列取得
	 *
	 */
	public function getRsyncVersion() {
		$tmp = explode("\n", shell_exec($this->rsync_cmd ." --version"));
		return $tmp[0];
	}
	
	/**
	 * 除外設定用フォルダリストの取得
	 *
	 * シンク元ルートパスからフォルダリストを取得するよ
	 */
	public function getDirList() {
		$ret = array();
		$fillist = scandir($this->from_path);
		foreach ($fillist as $file) {
			if (preg_match('/^\.{1,2}$/',$file)) {
				continue;
			}
			$path = $this->from_path .$file;
			if (is_dir($path)) {
				$ret[] = $file;
			}
		}
		return $ret;
	}
	
	/**
	 * dry-runを行い、結果を格納
	 */
	public function checkSync() {
		return $this->sync(true);
	}
	
	/**
	 * サーバーリストを取得する
	 *
	 * @return array サーバーリスト
	 */
	public function getServerList() {
		return $this->server_list;
	}
	
	/**
	 * 一個目のサーバー情報を取得
	 *
	 * ファイルリストで返却
	 *
	 */
	public function getFirstServerResultList() {
		return self::parseSyncList($this->server_list[0]['response']);
	}
	
	/**
	 * シンクコマンドを実行し、結果を格納する
	 *
	 *
	 * @param boolean $is_check true:チェック / false:実行
	 * @return object DsRsync
	 */
	public function sync($is_check = false) {
		
		if ($this->sync_dir_list) {
			// 相対パス指定であれば場合該当フォルダに移動
			$this->chdirToSourceDir();
		}
		
		// シンクコマンドを作成
		$this->buildSyncCmd($is_check);
		foreach ($this->server_list as &$server) {
			$server['response'] = shell_exec($server['sync_command']);
		}
		if ($this->mailer != null && $this->mail_to_list && $is_check === false) {
			// メール送信あり
			$this->sendResultMail();
		}
		
		
		return $this;
	}
	
	/**
	 * レポジトリからエクスポート実行
	 *
	 * @return 実行結果
	 */
	public function svnExport() {
		
		return shell_exec($this->builldSvnExportCmd());
	}
	
	/**
	 * エクスポートコマンドを生成
	 *
	 * エクスポートコマンドを生成して返却する
	 *
	 * @return string コマンド文字列
	 */
	public function builldSvnExportCmd() {
		$cmdstr = "";
		$cmdstr .= $this->svn_cmd . " ";
		if (empty($this->svn_repository)) {
			throw new DsSyncException("SVNレポジトリが定義されてません");
		}
		$cmdstr.= $this->svn_repository;
		
		// チェックアウト先
		if (!empty($this->svn_target_path)) {
			$cmdstr.= " ". $this->svn_target_path;
		} else {
			$cmdstr.= " ". $this->from_path;
		}
		//ユーザー
		if (!empty($this->svn_user)) {
			$cmdstr.= ' --username="' . $this->svn_user . '"';
			$cmdstr.= ' --password="' . $this->svn_password . '"';
		}
		// svnオプションを追加
		$cmdstr.= $this->svn_export_default_options;
		
		return $cmdstr;
	}
	
	/**
	 * シンクコマンドを生成する
	 *
	 * @param boolean $is_check true:チェックモード
	 * @return DsRsync $this
	 */
	public function buildSyncCmd($is_check = false) {
		
		$dry_str = "";
		
		if ($is_check) {
			$dry_str = " --dry-run";
		}
		// コマンドの生成
		foreach ($this->server_list as &$server) {
			$cmd = $this->rsync_cmd . $dry_str. " ". $server['rsync_options'];
			
			if ($this->sync_dir_list) {
				$cmd .= " -R";
			}
			
			
			// 鍵の設定
			if (!empty($server['key_path'])) {
				$cmd .= " -e " . "\"ssh -i " . $server['key_path'] . "";
				if (!empty($server['known_hosts_path'])) {
					// known_hostsの設定
					$cmd .= " -o UserKnownHostsFile=" . $server['known_hosts_path'];
				}
				$cmd .= '"';
			}
			// excludeの設定
			if (is_array($this->exclude_dir_list)) {
				foreach ($this->exclude_dir_list as $ex) {
					$cmd .= " --exclude=" . $ex;
					if (!preg_match('/\/$/', $ex)) {
						$cmd .= "/";
					}
				}
			}
			if (is_array($this->exclude_file_list)) {
				foreach ($this->exclude_file_list as $ex) {
					$cmd .= " --exclude=" . $ex;
				}
			}
			// シンク元
			
			if ($this->sync_dir_list) {
				// 相対パス指定
				$cmd .= " " . implode(" ", $this->sync_dir_list);
			} else {
				$cmd .= " " . $this->from_path;
			}
			
			// シンク先
			if ($server['is_local']) {
				$cmd .= " " . $server['path'];
			} else {
				$cmd .= " " . $server['host'] . ":". $server['path'];
			}
			$server['sync_command'] = $cmd;
		}
		return $this;
	}
	
	/**
	 * フォルダ除外リストを追加
	 *
	 * @param $exclude_list 除外リスト
	 */
	public function addExcludeDirList($exclude_list) {
		if (isset($exclude_list)) {
			if (is_array($exclude_list)) {
				$this->exclude_dir_list = array_merge($this->exclude_dir_list, $exclude_list);
			}
		}
		return $this;
	}
	
	/**
	 * ファイル除外リストを追加
	 *
	 * @param unknown_type $exclude_list
	 */
	public function addExcludeFileList($exclude_list) {
		if (isset($exclude_list)) {
			if (is_array($exclude_list)) {
				$this->exclude_file_list = array_merge($this->exclude_file_list, $exclude_list);
			}
		}
		return $this;
	}
	
	/**
	 * シンク結果リストをパースする
	 *
	 * @param $r
	 */
	public static function parseSyncList($r) {
		$filelist = array();
		$filelist['delete'] = array();
		$filelist['sync'] = array();
		
		$lines    = explode("\n", $r);
		foreach($lines as $line) {
			if(preg_match('/^sending/', $line)) {
				continue;
			}
			if(preg_match('/^building/', $line)) {
				continue;
			}
			if(preg_match('/^\s*$/', $line)) {
				continue;
			}
			if(preg_match('/^sent/', $line)) {
				continue;
			}
			if(preg_match('/^total/', $line)) {
				continue;
			}
			
			if (preg_match('/\/$/', $line)) {
				//$filelist['folder'][] = $line;
				continue;
			} elseif (preg_match('/^deleting/', $line)){
				$filelist['delete'][] = preg_replace('/^deleting /', '', $line);
			} else {
				$filelist['sync'][] = $line;
			}
		}
		return $filelist;
	}
	
	/**
	 * レポジトリ定義の有無を返却します
	 *
	 */
	public function getIsRepositoryDefined() {
		return $this->is_repository_defined;
	}
	
	/**
	 * クリアコマンドを作成する
	 *
	 * @return クリアコマンド文字列
	 */
	public function buildClearCmd($target) {

		return sprintf($this->clear_cmd, $target);
	}
	
	/**
	 * クリア対象リストが定義されているかを判定
	 *
	 *@return boolean 対象リストあり:true
	 */
	public function isClearDirDefined() {
		return count($this->clear_dir_list) > 0;
	}
	
	/**
	 * ターゲットDIRのクリア
	 * 対象が未設定の場合は何もしない
	 *
	 * @return DsSync 自分自身
	 */
	public function clearTargetDir() {
		if ($this->isClearDirDefined()) {
			foreach ($this->clear_dir_list as $clear_dir) {
				// フォルダの数だけ実行
				$this->tmp_cmd_result .= shell_exec($this->buildClearCmd($this->svn_target_path .  $clear_dir));
			}
		}
		return $this;
	}
	
	/*
	 * シンク元フォルダに移動
	 *
	 * return  DsSync 自分自身
	 */
	public function chdirToSourceDir() {
		chdir($this->from_path);
		return $this;
	}
	
	/**
	 * 結果メール送信
	 *
	 * シンク実行結果をメールで送る
	 *
	 * @param boolean $force_mode 強制送信モード指定。結果がなくてもメールを送る
	 */
	private function sendResultMail($force_mode = false) {
		
		$body_str = "";
		
		// メール送信フラグ
		$sendflg = false;
		
		if ($force_mode) {
			// 強制送信
			$sendflg = true;
		}
		
		$first_flg = true;
		
		foreach ($this->server_list as $server) {
			
			$host = "";
			$path = "";
			
			if ($server['is_local']) {
				$host = "ローカル";
			} else {
				$host = $server['host'];
			}
			$path = $server['path'];
			
			$filelist = $this->parseSyncList($server['response']);
			if ($filelist['delete'] || $filelist['sync'])  {
				// 結果あり
				$sendflg = true;
			}
			if ($first_flg) {
				$body_str .= "******************************\n";
				// 初回に差分をチェック
				if ($filelist['sync']) {
					if ($filelist['sync']) {
						$body_str .= "追加or更新リスト\n";
						$body_str .= "---------------------------\n";
						foreach ($filelist['sync'] as $file) {
							$body_str .= "$file\n";
						}
						$body_str .= "---------------------------\n\n";
					}
				}
				if ($filelist['delete']) {
					if ($filelist['delete']) {
						$body_str .= "削除リスト\n";
						$body_str .= "---------------------------\n";
						foreach ($filelist['delete'] as $file) {
							$body_str .= "$file\n";
						}
						$body_str .= "---------------------------\n\n";
					}
				}
				$body_str .= "******************************\n";
				$body_str .= "\n";
				$body_str .= "\n";
				
				$first_flg = false;
			}
			
			$body_str .= "サーバー::$host\n";
			$body_str .= "パス::$path\n";
			$body_str .= "---------------------------\n";
			$body_str .= "\n";
			$body_str .= $server['response'];
			$body_str .= "\n";
			$body_str .= "\n";
			
		}
		
		if ($sendflg) {
			// 送信対象があれば送信
			foreach ($this->mail_to_list as $to) {
				$this->message->setTo($to);
			}
			$this->message->setSubject($this->mail_subject);
			$this->message->setBody($body_str);
			$this->message->setFrom($this->mail_from);
			//$this->mailer->sendEscapedMail($this->mail_to_list, $this->mail_subject, $body_str, $this->mail_from);
			$this->mailer->send($this->message);
		}
	}
}
/**
 * コンフィグエラー
 */
class DsSyncConfException extends \Exception{}

/**
 * その他のエラー
 */
class DsSyncException extends \Exception{}

