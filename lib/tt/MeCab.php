<?php
namespace tt;
/**
 * 形態素解析
 *
 * インストール:
 *  > brew install mecab
 *  > brew install mecab-ipadic
 *  
 * Neologism dictionary for MeCab
 *  (mecab-ipadic-NEologd は、多数のWeb上の言語資源から得た新語を追加することでカスタマイズした MeCab 用のシステム辞書です。)
 *  https://github.com/neologd/mecab-ipadic-neologd/blob/master/README.ja.md
 *
 * 辞書を設定
 *  > vim /usr/local/etc/mecabrc
 *  
 * 辞書DIRの変更: 
 *  dicdir = /usr/local/lib/mecab/dic/mecab-ipadic-neologd
 * ユーザ辞書追加(カンマ区切りで複数指定可能): 
 *  userdic = /usr/local/lib/mecab/dic/ipadic/wikipedia.dic
 * 
 * @author tokushima
 * @see https://github.com/rsky/php-mecab
 */
class MeCab{
	protected $word;
	protected $pos;
	protected $reading;
	protected $prop1;
	protected $prop2;

	public function __construct($word,$pos,$reading,$prop1,$prop2){
		$this->word = $word;
		$this->pos = $pos;
		$this->reading = $reading;
		$this->prop1 = $prop1;
		$this->prop2 = $prop2;
	}
	/**
	 * 単語
	 * @return string
	 */
	public function word(){
		return $this->word;
	}
	/**
	 * ヨミ
	 * @return string
	 */
	public function reading(){
		return $this->reading;
	}
	/**
	 * 品詞
	 * @return integer
	 */
	public function pos(){
		return $this->pos;
	}
	/**
	 * 性質
	 * @return string
	 */
	public function prop1(){
		return $this->prop1;
	}
	/**
	 * 性質
	 * @return string
	 */
	public function prop2(){
		return $this->prop2;
	}

	public function pos_label(){
		switch($this->pos){
			case 1 : return '形容詞';
			case 2 : return '形容動詞';
			case 3 : return '感動詞';
			case 4 : return '副詞';
			case 5 : return '連体詞';
			case 6 : return '接続詞';
			case 7 : return '接頭辞';
			case 8 : return '接尾辞';
			case 9 : return '名詞';
			case 10 : return '動詞';
			case 11 : return '助詞';
			case 12 : return '助動詞';
			case 13 : return '特殊';
		}
		return null;
	}
	public function __toString(){
		return $this->word;
	}
	private static function feature2pos($fe){
		switch($fe){
			case '形容詞': return 1;
			case '形容動詞': return 2;
			case '感動詞': return 3;
			case '副詞': return 4;
			case '連体詞': return 5;
			case '接続詞': return 6;
			case '接頭辞': return 7;
			case '接尾辞': return 8;
			case '名詞': return 9;
			case '動詞': return 10;
			case '助詞': return 11;
			case '助動詞': return 12;
			default:
		}
		return 13;
	}
	
	/**
	 * 解析
	 * @param string $text
	 * @param integer[] $filter
	 *
	 * filter:
	 * 	1 : 形容詞
	 *  2 : 形容動詞
	 *  3 : 感動詞
	 *  4 : 副詞
	 *  5 : 連体詞
	 *  6 : 接続詞
	 *  7 : 接頭辞
	 *  8 : 接尾辞
	 *  9 : 名詞
	 *  10 : 動詞
	 *  11 : 助詞
	 *  12 : 助動詞
	 *  13 : 特殊（句読点、カッコ、記号など）
	 * @return \tt\MeCab[]
	 */
	public static function morpheme($text,$filter=[]){
		/**
		 * mecabコマンドパス
		 * @var string $cmd
		 */
		$mecab_cmd = \ebi\Conf::get('cmd');
		
		if(!empty($mecab_cmd)){
			$command = new \ebi\Command('echo "'.escapeshellcmd($text).'" | '.$mecab_cmd.' -p');
			
			foreach(explode(PHP_EOL,$command->stdout()) as $line){
				if($line == 'EOS'){
					break;
				}
				list($surface,$feature) = explode("\t",$line);
				$fe = explode(',',$feature);
				$pos = self::feature2pos($fe[0]);
				
				if(!empty($filter) && !in_array($pos,$filter)){
					continue;
				}
				yield new static(
					$surface,
					$pos,
					($fe[8] ?? $surface),
					$fe[1],
					(($fe[2] == '*') ? '' :  $fe[2])
				);
			}
		}else if(class_exists('\MeCab\Tagger')){
			foreach((new \MeCab\Tagger())->parseToNode($text) as $node){
				if($node->getPosId() > 0){
					$fe = explode(',',$node->getFeature());
					$pos = self::feature2pos($fe[0]);
					
					if(!empty($filter) && !in_array($pos,$filter)){
						continue;
					}
					yield new static(
						$node->getSurface(),
						$pos,
						($fe[8] ?? $node->getSurface()),
						$fe[1],
						(($fe[2] == '*') ? '' :  $fe[2])
					);
				}
			}
		}else{
			throw new \ebi\exception\BadMethodCallException('mecab not found');
		}
	}

	/**
	 * キーフレーズ
	 * @param string $text
	 * @return string[]
	 */
	public static function keyphrase($text,$filter=[9,10]){
		$result = [];
		$wordM = $wordD = '';
		$isd = in_array(10,$filter);
		$ism = in_array(9,$filter);

		$valid_func = function($text){
			$text = trim($text);
			if(!empty($text) && (mb_strlen($text) > 1 || preg_match('/[^ぁ-んァ-ヴーa-zA-Z0-9]/u',$text))){
				return true;
			}
			return false;
		};

		foreach(self::morpheme($text) as $m){
			if($m->pos() != 9){
				if($isd){
					if($m->pos() == 10){
						$wordD = $m->word();
					}else if($m->pos() == 12){
						$wordD = $wordD.$m->word();
					}else{
						if($valid_func($wordD)){
							$result[] = $wordD;
						}
						$wordD = '';
					}
				}
				if($valid_func($wordM)){
					$result[] = $wordM;
				}
				$wordM = '';
			}else if($m->pos() == 9){
				if($valid_func($wordD)){
					$result[] = $wordD;
				}
				if($ism){
					if($m->prop1() == '固有名詞'){
						$result[] = $m->word();
					}else if(ctype_alnum($m->word())){
						$result[] = $m->word();
					}else{
						$wordM = $wordM.$m->word();
					}
				}
				$wordD = '';
			}
		}
		if($valid_func($wordM)){
			$result[] = $wordM;
		}
		if($valid_func($wordD)){
			$result[] = $wordD;
		}
		return array_unique($result);
	}

	/**
	 * 辞書変換用の構成にする
	 * @return string
	 */
	public static function csv_values($value,$reading=null,$pos='名詞',$opt1='固有名詞',$opt2='一般',$origin=null){
		if(empty($opt1)){
			$opt1 = '*';
		}
		if(empty($opt2)){
			$opt2 = '*';
		}
		if(empty($origin)){
			$origin = '*';
		}
		return [$value,null,null,1,$pos,$opt1,$opt2,'*','*','*',$value,$reading,$reading,$origin];
	}
}