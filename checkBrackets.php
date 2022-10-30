<?php

$str = '[({()})]';

function checkBracket(string $str): string  {
    
    $res = ['не верно', 'верно'];
    
    $cnt = strlen($str);
    
    if ($cnt % 2 !== 0) {
        return $res[0];
    }
    
    $cnt /= 2;
    
    for ($i = 0; $i < $cnt; $i++) {
        if ($str[-$i-1] !== chr(ord($str[$i]) + 1) && $str[-$i-1] !== chr(ord($str[$i]) + 2)) {
            return $res[0];
        }
    }
    
    return $res[1];
}

print checkBracket($str);