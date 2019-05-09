<?php
/**
 * 辞書CSVの正規化
 * 
 * 辞書の元になるファイル
 *  CSVファイルのフォーマット: 表層形,読み,品詞,品詞細分類1,品詞細分類2,品詞細分類3,コスト
 *  テキストファイルのフォーマット: 表層形
 * 
 * 正規化されたCSVファイルから以下コマンドで辞書ファイルの作成
 * > /usr/local/Cellar/mecab/0.996/libexec/mecab/mecab-dict-index -d /usr/local/lib/mecab/dic/mecab-ipadic-neologd -u ./original.dic -f utf-8 -t utf-8 ./original.csv
 *
 * mecab辞書ファイル作成後にmecabrcを編集してユーザ辞書を追加
 *
 * @param string $in 辞書の元になるCSV/TEXTファイル @['require'=>true]
 * @param string $out 正規化されたCSVファイル @['require'=>true]
 * @param boolean $verb 品詞が未定義の場合に動詞とする
 * @see http://www.unixuser.org/~euske/doc/postag/#chasen
 * @see http://dumps.wikimedia.org/jawiki/latest/jawiki-latest-all-titles-in-ns0.gz
 */
if(!is_file($in)){
	throw new \ebi\exception\NotFoundException($in.' not found');
}

\ebi\Util::mkdir(dirname($out));
$outfile = new \SplFileObject($out,'w');
$iscsv = preg_match('/\.csv$/i', $in);
$isverb = (boolean)$verb;

$func_putcsv = function($value,$data) use($outfile,$isverb){
	$value = trim($value);
	
	if(!empty($value) && !ctype_digit($value)){
		$reading = $data[1] ?? null; // 読み
		$pos = $data[2] ?? ($isverb ? '動詞' : '名詞'); // 品詞
		$opt1 = $data[3] ?? (($pos == '名詞') ? '固有名詞' : (($pos == '動詞') ? '*' : '*')); // 品詞細分類1
		$opt2 = $data[4] ?? (($opt1 == '固有名詞') ? '一般' : '*'); // 品詞細分類2
		$opt3 = $data[5] ?? '*'; // 品詞細分類3
		$cost = $data[6] ?? 1; // コスト
		$id = ($isverb ? 1 : null);
		
		$outfile->fputcsv([
			$value, // 表層形
			$id, // 左文脈ID
			$id, // 右文脈ID
			$cost, // コスト
			$pos, // 品詞
			$opt1, // 品詞細分類1
			$opt2, // 品詞細分類2
			$opt3, // 品詞細分類3
			'*', // 活用型
			'*', // 活用形
			$value, // 原型
			$reading, // 読み
			$reading, // 発音
			//$origin, // 追加エントリ
		]);
	}
};

if($iscsv){
	foreach(\ebi\Util::file_read_csv($in) as $data){
		$func_putcsv($data[0],$data);
	}
}else{
	$fp = fopen($in,'r');
	
	while(!feof($fp)){
		$func_putcsv(fgets($fp),[]);
	}
}

\cmdman\Std::println('Written '.realpath($out));
