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
	
	public function __construct($word,$pos=9,$reading=null,$prop1=null,$prop2=null){
		$this->word = $word;
		$this->pos = $pos;
		$this->reading = $reading;
		$this->prop1 = $prop1;
		$this->prop2 = $prop2;
	}
	
	public function __toString(){
		return $this->word;
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
	 * 品詞 (part of speech)
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
	
	/**
	 * 品詞ラベル
	 * @return string
	 */
	public function pos_label(){
		return static::get_pos_label($this->pos);
	}
	
	/**
	 * 品詞ラベル
	 * @param integer $pos
	 * @return string
	 */
	public static function get_pos_label($pos){
		switch($pos){
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
	
	/**
	 * 品詞名から品詞IDを返す
	 * @param string $fe
	 * @return number
	 */
	protected static function feature2pos($fe){
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
		 * /usr/local/bin/mecab
		 * /usr/local/bin/mecab -u /etc/opt/user.dic
		 * @var string $cmd
		 */
		$mecab_cmd = \ebi\Conf::get('cmd');
		$text = trim($text);
		
		if(!empty($text)){
			if(empty($mecab_cmd)){
				throw new \ebi\exception\BadMethodCallException('Undefined mecab command');
			}
			if(strlen($text) > (256 * 1024)){
				throw new \ebi\exception\InvalidArgumentException('Argument exceeds the allowed length of 262144');
			}
			$command = new \ebi\Command('echo '.escapeshellarg($text).' | '.$mecab_cmd.' -p');
			
			$make = null;
			foreach(explode(PHP_EOL,$command->stdout()) as $rtn){
				if($rtn == 'EOS' || empty($rtn)){
					break;
				}
				list($surface,$feature) = explode("\t",$rtn);
				$fe = explode(',',$feature);
				
				if(sizeof($fe) > 2){
					$pos = static::feature2pos($fe[0]);
					
					if(!empty($filter) && !in_array($pos,$filter)){
						continue;
					}
					if($pos == 13){
						if(!isset($make) && ($surface == '{*' || $surface == '{%')){
							$make = ($surface[1] == '*') ? [9,'','一般'] : [10,'','自立'];
							continue;
						}else if(isset($make) && ($surface == '*}' || $surface == '%}')){
							list($pos,$surface) = $make;
							$fe[8] = $fe[2] = null;
							$fe[1] = $make[2];
							$make = null;
						}
					}
					if(isset($make)){
						$make[1] .= $surface;
						continue;
					}
					yield new static($surface,$pos,($fe[8] ?? $surface),$fe[1],(($fe[2] == '*') ? '' :  $fe[2]));
				}
			}
			if(isset($make)){
				yield new static($make[1],$make[0],$make[1],$make[2],'');
			}
		}
	}
	
	/**
	 * 文節の抽出
	 * @param \tt\MeCab[] $mecab_list
	 * @return array \tt\MeCab[][]
	 */
	public static function clause($mecab_list){
		$clause = [];
		$sentence = [];
		$prepos = null;
		
		if(is_string($mecab_list)){
			$mecab_list = static::morpheme($mecab_list);
		}
		foreach($mecab_list as $obj){
			$cpos = $obj->pos();
			
			if(!empty($sentence) &&
				(
					in_array($cpos,[1,3,4,6,9,10]) ||
					($cpos == 13 && ($obj->prop1() == '読点' || $obj->prop1() == '句点'))
				) &&
				!in_array($obj->prop1(),['非自立','接尾']) &&
				$prepos != $cpos
			){
				$clause[] = $sentence;
				$sentence = [];
			}
			$sentence[] = $obj;
			$prepos = ($obj->word() == '・') ? 9 : $cpos;
		}
		if(!empty($sentence)){
			$clause[] = $sentence;
		}
		return $clause;
	}
	
	/**
	 * 結合する
	 * @param \tt\MeCab[] $mecab_list
	 * @param integer[] $ignore part of speech to ignore
	 * @param \tt\MeCab
	 */
	public static function join($mecab_list,$ignore=[13]){
		$word = '';
		$result = null;
		
		foreach($mecab_list as $mecab){
			if(empty($ignore) || !in_array($mecab->pos(), $ignore)){
				if($result === null){
					$result = clone($mecab);
				}else{
					$word .= $mecab->word();
				}
			}
		}
		if($result !== null){
			$result->word .= $word;
			return $result;
		}
		throw new \ebi\exception\InvalidArgumentException();
	}
	
	/**
	 * 名詞としてマーキングする
	 * @param string $text
	 * @param array $nouns
	 * @param integer $pos
	 * @return string
	 */
	public static function making($text,array $nouns,$pos=9){
		$mark = ($pos == 9) ? '*' : '%';
		
		foreach($nouns as $noun){
			if(strpos($text,$noun) !== false){
				$text = str_replace($noun,' {'.$mark.$noun.$mark.'} ', $text);
			}
		}
		return $text;
	}
}
