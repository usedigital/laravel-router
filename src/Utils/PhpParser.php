<?php
namespace UseDigital\LaravelRouter\Utils;

class PhpParser
{
    public function extractFileClass(string $file)
    {

        if(!file_exists($file))
            return false;

        $file_content = file_get_contents($file);

        $class = '';
        $i = 0;
        while (!$class) {
            $tokens = token_get_all($file_content);

            for (;$i<count($tokens);$i++) {
                if ($tokens[$i][0] === T_CLASS) {
                    for ($j=$i+1;$j<count($tokens);$j++) {
                        if ($tokens[$j] === '{') {
                            $class = $tokens[$i+2][1];
                        }
                    }
                }
            }
        }

        return $class;
    }

    public function extractFileNamespace(string $file)
    {
        $src = file_get_contents($file);
        $tokens = token_get_all($src);
        $count = count($tokens);
        $i = 0;
        $namespace = '';
        $namespace_ok = false;
        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                // Found namespace declaration
                while (++$i < $count) {
                    if ($tokens[$i] === ';') {
                        $namespace_ok = true;
                        $namespace = trim($namespace);
                        break;
                    }
                    $namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                }
                break;
            }
            $i++;
        }

        if (!$namespace_ok) {
            return null;
        } else {
            return $namespace;
        }
    }
}
