#!/usr/local/bin/php
<?php
// DDNS UPDATE SCRIPT
// Created by Aoi.Kagase
class DDNS_UPDATER
{
	# 以下２ファイルの配置ディレクトリは好みに応じ設定
	# 1. 更新時IPアドレス保存ファイル
	protected $CURRENT_IP_FILE = "./current_ip_ddns";

	#  2. ログファイル
	protected $LOG_FILE        = "./ip_update_ddns.log";

	# 回線IP確認ページURL
	protected $REMOTE_ADDR_CHK = "http://checkip.dyndns.org";

	# DDNS更新ページURLとアカウント情報
	protected $DDNS_UPDATE     = [
		// ieserver
		// http://ieserver.net
		// ※ 現在サービス停止中のため利用不可
//		"IESERVER"  =>  [
//			'URL' => "https://ieserver.net/cgi-bin/dip.cgi?username=%s&domain=%s&password=%s&updatehost=1", 
//		 	'ACC' => [
//			 	['ACCOUNT' => '********', 'DOMAIN' => 'dip.jp',   'PASSWORD'  => '********'],
//			 	['ACCOUNT' => '********', 'DOMAIN' => 'dip.jp',   'PASSWORD'  => '********'],
//			 	['ACCOUNT' => '********', 'DOMAIN' => 'dip.jp',   'PASSWORD'  => '********'],
//			],
//		],

		// Free Dynamic DNS Service DDNS Now 
		// https://ddns.kuku.lu
		"DDNS Now"  => [
			'URL' => "https://f5.si/update.php?domain=%s&password=%s",
			'ACC' => [
				['ACCOUNT' => '********', 	'DOMAIN' => 'f5.si',	'PASSWORD' => '********'],
				['ACCOUNT' => '********', 	'DOMAIN' => 'f5.si',	'PASSWORD' => '********']
			]
		],

		// MyDNS.JP 
		// https://www.mydns.jp
		"MyDNS"     => [
			'URL' => "https://www.mydns.jp/login.html",
			'ACC' => [
				['ACCOUNT' => 'mydns******', 'DOMAIN' => 'server-on.net', 'PASSWORD' => '***********'],
				['ACCOUNT' => 'mydns******', 'DOMAIN' => 'server-on.net', 'PASSWORD' => '***********'],
			]
		],
	];

	protected $CURRENT_IP = '0.0.0.0';
	protected $NEW_IP     = '0.0.0.0';

	// ========================================================================
	// ログ出力
	// ========================================================================
	protected function output_log($status, $account, $domain)
	{
		if ($status == 200)
		{
			if ($fp = fopen($this->CURRENT_IP_FILE, 'w'))
			{
				fprintf($fp, $this->NEW_IP);
				fclose($fp);
			}
			if ($fp = fopen($this->LOG_FILE, 'aw'))
			{
				$TIME = time();
				fprintf($fp, "${TIME} ${account}.${domain} Updated %s to %s\n", $this->CURRENT_IP, $this->NEW_IP);
				fclose($fp);
			}
		}
		else
		{
			if ($fp = fopen($this->LOG_FILE, 'aw'))
			{
				$TIME = time();
				fprintf($fp, "${TIME} ${account}.${domain} Updated aborted %s to %s\n", $this->CURRENT_IP, $this->NEW_IP);
				fclose($fp);
			}
		}
	}

	// ========================================================================
	// MAIN 関数
	// ========================================================================
	public function main()
	{
		// 前回更新時のIPアドレス取得
		if ($fp = fopen($this->CURRENT_IP_FILE, 'r'))
		{
			$this->CURRENT_IP = fgets($fp);
			fclose($fp);
		}

		// Curl初期化
		// 現在のIPアドレス取得
		$conn   = curl_init();
		curl_setopt($conn, CURLOPT_URL, $this->REMOTE_ADDR_CHK);
		curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($conn);
		if (!curl_errno($conn))
		{
			$http_code = curl_getinfo($conn, CURLINFO_RESPONSE_CODE);
			if ($http_code == 200)
				$this->NEW_IP = str_replace($res,"Current IP Address: ", "");
		}
		curl_close($conn);

		// 前回のIPと不一致の場合のみ実行する
		// ※MyDNSの場合、一定期間内に更新が掛からない場合はアカウント停止になるので問答無用で更新するよう条件をコメント化中（中にIF文移動すれば良いけど面倒だったので）
//		if ($this->NEW_IP !== "0.0.0.0" || $this->CURRENT_IP !== $this->NEW_IP)
		{
			// 複数のサービスを利用している場合、サービス分のループ
			foreach($this->DDNS_UPDATE as $PROVIDER => $SETTING)
			{
				switch($PROVIDER)
				{
					// ieserver (実行不可 上参照)
					// case 'IESERVER':
					// 	foreach($SETTING['ACC'] as $acc)
					// 	{
					// 		$conn = curl_init();
					// 		$url  = sprintf($SETTING['URL'], $acc['ACCOUNT'], $acc['DOMAIN'], $acc['PASSWORD']);
					// 		curl_setopt($conn, CURLOPT_URL, $url);
					// 		$res = curl_exec($conn);
					// 		if (!curl_errno($conn))
					// 		{
					// 			$http_code = curl_getinfo($conn, CURLINFO_RESPONSE_CODE);
					// 			$this->output_log($http_code, $acc['ACCOUNT'], $acc['DOMAIN']);
					// 		}
					// 		curl_close($conn);
					// 	}
					// 	break;
					
					case 'DDNS Now':
						// アカウント分のループ
						foreach($SETTING['ACC'] as $acc)
						{
							// Curlでアクセスして更新完了
							$conn = curl_init();
							$url  = sprintf($SETTING['URL'], $acc['ACCOUNT'], $acc['PASSWORD']);
							curl_setopt($conn, CURLOPT_URL, $url);
							$res = curl_exec($conn);
							if (!curl_errno($conn))
							{
								$http_code = curl_getinfo($conn, CURLINFO_RESPONSE_CODE);
								$this->output_log($http_code, $acc['ACCOUNT'], $acc['DOMAIN']);
							}
							curl_close($conn);
						}
						break;
					case 'MyDNS':
						// アカウント分のループ
						foreach($SETTING['ACC'] as $acc)
						{
							$conn = curl_init();
							curl_setopt($conn, CURLOPT_POST, 1);

							// アカウントログインがすんなり行かないのでCurlで頑張る
							// ログインが成功したらIP更新完了

							// curl_setopt($conn, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
							curl_setopt($conn, CURLOPT_USERNAME,"{$acc['ACCOUNT']}");
							curl_setopt($conn, CURLOPT_USERPWD,	"{$acc['ACCOUNT']}:{$acc['PASSWORD']}");
							curl_setopt($conn, CURLOPT_RETURNTRANSFER, TRUE);
							curl_setopt($conn, CURLOPT_HTTPHEADER,
				               array(
									"Content-Type: application/x-www-form-urlencoded;charset=\"utf-8\"",
	            					"Cache-Control: no-cache",
						            "Pragma: no-cache",
									"Authorization: Basic " . base64_encode($acc['ACCOUNT'].":".$acc['PASSWORD']),
								)
							);
							curl_setopt($conn, CURLOPT_URL, $SETTING['URL']);
							$res = curl_exec($conn);
							if (!curl_errno($conn))
							{
								$http_code = curl_getinfo($conn, CURLINFO_RESPONSE_CODE);
								$this->output_log($http_code, $acc['ACCOUNT'], $acc['DOMAIN']);
							}
							curl_close($conn);
						}
						break;
				}
			}
		}
	}
}

$main = new DDNS_UPDATER();
$main->main();
exit;

?>	
