<?php

$i = 0;
foreach(\tt\MeCab::morpheme('庭に二羽の鶏がいた') as $ward){
	$i++;
}
eq(8,$i);


$i = 0;
foreach(\tt\MeCab::morpheme('庭に二羽の鶏がいた',[9]) as $ward){
	eq(9,$ward->pos());
	$i++;
}
eq(3,$i);



