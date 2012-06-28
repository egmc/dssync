<?php
/**
 * DsSyncクラス
 *
 * シンク関連処理をイロイロやる
 * @author eg
 *
 */
class DsSync {
	
	// DsSyncバージョン
	const VERSION = "1.0";
	
	//rsyncコマンド
	private $rsync_cmd = "rsync";
	// シンク元パス
	private $from_path = "";
	//デフォルトオプション
	private $default_opptions = " -vruzalpcn --delete";
	
	// -----------svninfo
	// svnコマンド
	private $svn_cmd = "svn";
	// svnレポジトリ
	private $svn_repository;
	// svnユーザー
	private $svn_user;
	// svnパス
	private $svn_password;
	// svnターゲットパス、未定義時は$from_pathが使用される
	private $svn_target_path;
	
	// サーバーリスト
	private $server_list = array();
	
	// レポジトリ定義チェック
	private $is_repository_defined = false;
	
	// 除外フォルダリスト
	private $exclude_dir_list = array();
	
	// 除外ファイルリスト
	private $exclude_file_list = array();
	
	
	
	/**
	 * コンストラクタ
	 *
	 * @param unknown_type $options
	 */
	public function __construct($options) {
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
		
		// svnエクスポート先パス
		if (isset($options['svn_target_path'])) {
			$this->svn_target_path = $options['svn_target_path'];
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
		$this->buildSyncCmd($is_check);
		foreach ($this->server_list as &$server) {
			$server['response'] = shell_exec($server['sync_command']);
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
		$cmdstr .= $this->svn_cmd . " export ";
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
		// export時上書き強制
		$cmdstr.= " --force";
		
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
			$cmd .= "";
			
			// 鍵の設定
			if (!empty($server['key_path'])) {
				$cmd .= " -e " . "\"ssh -i " . $server['key_path'] . "";
				if (!empty($server['known_hosts_path'])) {
					// known_hostsの設定
					$cmd .= " -o " . $server['known_hosts_path'];
				}
				$cmd .= '"';
			}
			// excludeの設定
			if (is_array($this->exclude_dir_list)) {
				foreach ($this->exclude_dir_list as $ex) {
					$cmd .= " --exclude=" . $ex;
				}
			}
			if (is_array($this->exclude_file_list)) {
				foreach ($this->exclude_file_list as $ex) {
					$cmd .= " --exclude=" . $ex;
				}
			}
			// シンク元
			$cmd .= " " . $this->from_path;
			
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
	
}
/**
 * コンフィグエラー
 */
class DsSyncConfException extends Exception{}

/**
 * その他のエラー
 */
class DsSyncException extends Exception{}

