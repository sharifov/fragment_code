<?php

function checkMoreSymbol(string $str): string {
	if (mb_strlen($str) < 3) {
		throw new Exception('Minimum 3 symbol!');
	}
	
	$splittedArray = str_split($str);
	
	$countSymbols = array_count_values($splittedArray);
	
	arsort($countSymbols);
	
	$countSymbols = array_keys($countSymbols);
	
	if (!isset($countSymbols[1])) {
		throw new Exception('Only one symbol finded!');
	}
	
	return $countSymbols[1];
}

function checkPolindrom(string $str): string {
	$res = 'палиндром';
	if ($str !== mb_strrev($str)) {
		$res = 'не ' . $res;
	}
	return $res;
}

function mb_strrev(string $str): string {
    $res = '';
    for ($i = mb_strlen($str); $i>=0; $i--) {
        $res .= mb_substr($str, $i, 1);
    }
    return $res;
}