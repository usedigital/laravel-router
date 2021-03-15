<?php
return [

    "controllers_namespace" => 'App\Http\Controllers',

    "force_lowercase" => true,

    /***
     * Arquivos que serão gerados pelo script para WEB e API
     */

    "defaults" => [

        'middleware' => [
            'api' => 'api',
            'web' => 'auth'
        ],

        "method" => "get",

        /**
         * Exibir estatisticas
         */
        "stats" => true,

        /**
         * Exibir verbose
         */
        "verbose" => false,
    ],

    /**
     * Caminho para os controllers "API", deve estar dentro de app/Http/Controllers
     */
    "api" => [
        "path" => "API",
        "prefix" => "api",
        "as" => "api.",
        "domain" => 'api.{config("app.domain")}',
        "namespace" => "API",
    ],

    /**
     * Caminho para os controllers "WEB", deve estar dentro de app/Http/Controllers
     */
    'web' => [
        'prefix' => '',
        'as' => '',
        'domain' => config('app.domain'),
        'middleware' => 'web'
    ],

    /**
     * Usar estrutura de pastas na estrutua dos links e nos alias. Ex: Controllers/Admin/HomeController.php will result in /admin/home/*
     * Se dinido como "false" o link será exatamente o definido no phpDoc
     */
    "use_folders_structure" => true, //

    /**
     * Parametros phpDOC a serem considerados, personalize aqui seus próprios parametros
     */
    "parameters" => collect([
        "middleware"    => collect(["middleware", "mw"]),
        "method"        => collect(["method", "mt"]),
        "as"            => collect(["as"]),
        "url"        => collect(["url","u"]),
        "name"        => collect(["action", "name", "a", "n"]),
        "prefix"        => collect(["prefix", "pre", "p"]),
        "domain"     => collect(["subdomain", "sd", "domain", "d"]),
        "namespace" => collect(["namespace", "ns"])
    ]),

    /**
     * Metodos a serem ignorados
     */
    "ignore_methods" => collect(["__construct"]), //Controller Methods to be ignored by the generator

    /**
     * Termos chave para ignorar determinados Controllers ou Metodos. Defina @reject_term no phpDOC para que o script ignore o controller ou metodo
     */
    "reject_terms" => collect(["noturl", "notroute", "notgenerate", "ignore", "i"]),

    "directories" => collect([
        "Dir" => [
            "prefix" => "",
            "as" => "",
            "domain" => "",
            "namespace" => "",
        ]
    ])
];
