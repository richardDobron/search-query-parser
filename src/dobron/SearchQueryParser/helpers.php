<?php

namespace dobron\QueryTextParser;

function array_in_array(array $array):bool {
    foreach ($array as $key => $el) {
        if (is_array($el)) {
            return true;
        }
    }

    return false;
}