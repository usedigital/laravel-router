<?php


namespace UseDigital\LaravelRouter\Utils;


use Illuminate\Support\Str;

class RouterHelpers
{
    /**
     * @param array $array
     *
     * @return string|string[]|null
     */
    public static function shortArrayStructureString(array $array){
        $export = var_export($array, TRUE);
        $export = preg_replace("/^([ ]*)(.*)/m", '$1$1$2', $export);
        $array_trait = preg_split("/\r\n|\n|\r/", $export);
        $array_trait = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [NULL, ']$1', ' => ['], $array_trait);
        $export = join(PHP_EOL, array_filter(["["] + $array_trait));

        $export = str_replace("'",'"', $export);
        $export = str_replace("{",'".', $export);
        $export = str_replace("}",'."', $export);

        return $export;
    }
}
