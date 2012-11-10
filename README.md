#DsSync

DsSync provdes rsync's web interface for deploy

## usage

### dependencies

- rsync
- svn（チェックアウト機能を使う場合）
- Pear Text_Diff（差分ビューア用、optional）

その他の依存関係はcomposer.json参照


### サーバーへの配備

上記依存関係が解決されていれば動きます。多分。
設定ファイルを記述してpublic/dssync.phpにアクセスしてください。


### 設定ファイル

設定ファイルはYAMLで書きます。  
基本的に必須となっているもの意外は省略可能。  
省略時はデフォルト値が使用されます。


#### グローバルオプション

- title:  
	シンク機能タイトル

- rsync_cmd:  
	rsyncコマンド指定。デフォルトはrsync

- from_path:  
	[必須]
	シンク元のパスを/で終わるように指定。
	
	例）/path/to/yourapp/

- exclude_dir_list:  
	リスト形式で指定
	デフォルトでシンク対象から除外するフォルダリスト。
	ここで指定した内容は常に有効（画面からはオフれない）
	

```
	- tmp
	- .svn
```


- exclude_file_list:
	リスト形式で指定
	デフォルトでシンク対象から除外するファイルリスト。
	パターン指定可能。
	
```
	- .*
	- .htaccess
```

- svn_cmd:
	SVNコマンド定義
	デフォルトはsvn

- svn_repository:
	省略時はcheckout機能を使わない場合は省略


- svn_target_path:
	SVNでエクスポートする先のパスを指定
	省略時はfrom_pathの値が使われます

- svn_user:
	SVNログインユーザー

- svn_password:
	SVNログインパスワード

#### サーバーオプション


- local_list:
	ローカルのシンク先をリスト形式で定義
	
	path:
	[必須]
	シンク先のパス
	
	rsync_options:
	rsync実行オプション、省略時はデフォルト「 -vruzalpcn --delete」
	
```	
	- path: /path/to/local/
	  rsync_options: --recursive --update --compress --archive --verbose -c --delete
```

- remote_list:
	リモートのシンク先をリスト形式で定義
	
	host:
	[必須]
	シンク先ホスト
	path:
	[必須]
	シンク先パス
	key_path:
	ssh用鍵ファイルパス
	known_hosts_path:
	ssh用known_hostsファイルへのパス

```
	- host: user@hostname
	  path: /path/to/remote/
	  key_path: /path/to/ssh_key
	  known_hosts_path: /pass/to/known_hosts
	  rsync_options: --recursive --update --compress --archive --verbose -c --delete
```




（設定例）

```
---
title: "release sample"
# rsyncコマンドを定義
rsync_cmd: rsync
# シンク元となるフォルダ、必ず/で終わるように
from_path: /path/to/from/
# シンク除外フォルダリスト
exclude_dir_list:
- tmp
- .svn
# シンク除外ファイルリスト
exclude_file_list:
- .htaccess
# svnコマンド定義
svn_cmd: svn
# svnレポジトリ
svn_repository: http://path/to/repo/
# シンク先パス、未定義の場合from_pathが使われる
svn_target_path: /path/to/from/
#svnユーザー、パスワード
svn_user: user
svn_password: pass
# ローカルへのシンクリスト
local_list:
- path: /path/to/local/
  rsync_options: --recursive --update --compress --archive --verbose -c --delete
# リモートへのシンクリスト
remote_list:
- host: user@exmaple.com
  path: /path/to/remote1
  key_path: /path/to/ssh_key
  rsync_options: --recursive --update --compress --archive --verbose -c --delete
- host: user@exmaple2.com
  path: /path/to/remote2
  key_path: /path/to/ssh_key
  rsync_options: --recursive --update --compress --archive --verbose -c --delete
...
```