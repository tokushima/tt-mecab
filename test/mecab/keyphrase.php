<?php
$wards = \tt\MeCab::keyphrase('庭に二羽の鶏がいた');
eq(4,sizeof($wards));

