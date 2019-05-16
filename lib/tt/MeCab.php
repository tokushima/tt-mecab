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
		return self::get_pos_label($this->pos);
	}
	
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
		 * /usr/local/bin/mecab 
		 * /usr/local/bin/mecab -u /etc/opt/user.dic
		 * @var string $cmd
		 */
		$mecab_cmd = \ebi\Conf::get('cmd');
		
		if(!empty($mecab_cmd)){
			$command = new \ebi\Command('echo "'.escapeshellcmd($text).'" | '.$mecab_cmd.' -p');
			
			foreach(explode(PHP_EOL,$command->stdout()) as $rtn){
				if($rtn == 'EOS' || empty($rtn)){
					break;
				}
				list($surface,$feature) = explode("\t",$rtn);
				$fe = explode(',',$feature);
				
				if(sizeof($fe) > 2){
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
	 * フレーズの抽出
	 * @param \tt\MeCab[] $mecab_list
	 * @return array \tt\MeCab[][]
	 */
	public static function phrases($mecab_list){
		$phrases = [];
		$sentence = [];
		$prepos = null;
		
		foreach($mecab_list as $obj){
			if(
				!empty($sentence) &&
				($obj->pos() == 1 || $obj->pos() == 3 || $obj->pos() == 6 || $obj->pos() == 9  || $obj->pos() == 10 || 
					($obj->pos() == 13 && ($obj->prop1() == '読点' || $obj->prop1() == '句点'))
				) &&
				!preg_match('/^(?:\xE3\x81[\x81-\xBF]|\xE3\x82[\x80-\x93]|ー)$/',$obj->word()) && // ひらがな一文字は無視
				!($prepos == 9 && ($obj->pos() == 9 || $obj->pos() == 10)) && 
				!($prepos == 10 && $obj->pos() == 10) 
			){
				$phrases[] = $sentence;
				$sentence = [];
			}
			
			if($obj->pos() != 13 || $obj->word() == '・'){
				$sentence[] = $obj;
				$prepos = ($obj->word() == '・') ? 9 : $obj->pos();
			}
		}
		if(!empty($sentence)){
			$phrases[] = $sentence;
		}
		return $phrases;
	}
	
	/**
	 * 結合する
	 * @param \tt\MeCab[] $mecab_list
	 * @param \tt\MeCab
	 */
	public static function join($mecab_list){
		list($first) = $mecab_list;
		$word = '';
		
		foreach($mecab_list as $mecab){
			$word .= $mecab->word();
		}
		$first->word = $word;
		
		return $first;
	}
}
