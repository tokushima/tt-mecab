<?php
/**
 * Wikipediaのタイトル一覧からdic用csvへ変換
 * CSV作成後、以下コマンドで辞書ファイルの作成
 * > /usr/local/Cellar/mecab/0.996/libexec/mecab/mecab-dict-index -d /usr/local/lib/mecab/dic/ipadic/ -u ./jawiki.dic -f utf-8 -t utf-8 ./jawiki.csv
 * 
 * mecab辞書ファイル作成後にmecabrcを編集してユーザ辞書を追加
 * 
 * @param string $out csv filename
 */

$dic_csv_file = \cmdman\Args::value($out);
$workfile = \ebi\WorkingStorage::tmpfile();

$b = new \ebi\Browser();
$b->do_download('http://dumps.wikimedia.org/jawiki/latest/jawiki-latest-all-titles-in-ns0.gz',$workfile);

\ebi\Util::mkdir(dirname($dic_csv_file));
$outfile = new \SplFileObject($dic_csv_file,'w');

$handle = gzopen($workfile,'r');

while(!gzeof($handle)){
	$buffer = gzgets($handle);
	$value = trim($buffer);
	
	if(!empty($value) && !ctype_digit($value)){
		$outfile->fputcsv(\tt\MeCab::csv_values($value,null,'名詞','固有名詞','一般','wikipedia'));
	}
}
gzclose($handle);

\cmdman\Std::println('Written '.realpath($dic_csv_file));

