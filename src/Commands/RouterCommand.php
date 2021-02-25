<?php

namespace UseDigital\LaravelRouter\Commands;

use App\Http\Controllers\Controller;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Finder\SplFileInfo;
use UseDigital\LaravelRouter\Models\RouteGroupModel;
use UseDigital\LaravelRouter\Models\RouteModel;
use UseDigital\LaravelRouter\Utils\PhpParser;

/**
 * Gerador de Rotas para o laravel
 * Comando: php artisan router
 * Prametros
 *
 * ** Controller
 * @prefix - Prefixo de URL para a rota - Padrao: caminho/do/controller/acao,
 *
 * ** Action
 * @name, @action, @url - Nome da rota (final da URL) - Padrao: nome da função, "/" quando index
 * @method - Metodos de entrada da rota - Padrao: get,post,
 *
 * ** Ambos
 * @middleware - Middlerare de segurança da rota - Padrao: auth,
 * @as - Apelido da rota - Padrao: caminho.do.controller.acao,
 * @noturl, @notroute, @notgenerate - Ignorar o Controller ou Action
 *
 */

class RouterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'router
                                {--no-verbose : No Verbose}
                                {--s|stats : Exibir estatisticas}
                                {--no-stats : Ocultar Estatisticas}';

    /**
     * @var bool
     */
    private $verbose, $stats;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gerar rotas baseadas no phpDOC dos Controllers';

    /**
     * Rotas WEB
     *
     * @var Collection|RouteModel[]|RouteGroupModel[]|mixed
     */
    public $rotas_web, $rotas_api;

    private $count_rotas_web = 0,
        $count_rotas_api = 0,
        $count_rotas = 0;

    /**
     * Controllers da app
     * @var Collection|SplFileInfo[]
     */
    private $controllers_web, $controllers_api;

    private $count_controllers_web = 0,
        $count_controllers_api = 0,
        $count_controllers = 0;

    /**
     * Parametros phpDOC a serem considerados
     * @var array|Collection
     */
    private $parameters = [];

    /**
     * Métodos a serem ignorados no Controller (métodos padrão, construct, etc)
     * @var array|Collection
     */
    private $ignore_methods = [];

    /**
     * Métodos padrão do BaseController Laravel
     * @var array|Collection
     */
    private $laravel_methods = [];

    /**
     * Termos chaves para ignorar um método via phpDOC
     * @var array|Collection
     */
    private $reject_terms = [];

    /**
     * @var ProgressBar
     */
    public $bar;

    /**
     * @var PhpParser
     */
    private $parser;

    private $steps = 8;

    public function __construct()
    {
        parent::__construct();

        $this->parameters = config("router.parameters");

        //Métodos padrão do laravel a serem ignorados
        $this->laravel_methods = get_class_methods(new Controller());
        $this->ignore_methods = collect($this->laravel_methods)->merge(config("router.ignore_methods"));

        //Termos chave para ignorar determinados metodos nos controllers
        $this->reject_terms = config("router.reject_terms");

        $this->rotas_web = collect();
        $this->rotas_api = collect();

        $this->parser = new PhpParser();
    }

    /**
     * @throws \ReflectionException
     */
    public function handle()
    {

        $this->verbose = $this->option("verbose") ? true : ($this->option("no-verbose") ? false : config("router.defaults.verbose"));
        $this->stats = $this->option("stats") ? true : ($this->option("no-stats") ? false : config("router.defaults.stats"));

        //Listar Controllers
        if($this->verbose) $this->info("Iniciando listagem de Controllers");

        $this->controllers_web = $this->getControllers();
        $this->controllers_api = $this->getControllers(config("router.api.path"), true);

        if($this->verbose) $this->info("Controllers encotrados: {$this->count_controllers_web}");

        //Listar Rotas
        $this->info("Iniciando processamento Controllers e Rotas");
        $this->bar = $this->output->createProgressBar($this->steps + $this->count_controllers);
        $this->bar->setFormat('[%bar%] %percent:3s%% %elapsed:6s%');
        //$this->bar->advance(3);


        $this->rotas_web = $this->getRoutes($this->controllers_web);
        $this->bar->advance();

        $this->rotas_api = $this->getRoutes($this->controllers_api);
        $this->bar->advance();

        //dump($this->rotas_api);

        if($this->verbose) $this->line("");
        if($this->verbose) $this->info("Gerando arquivos de rotas");

        $script_web = "";
        $script_api = "";

        if($this->rotas_web && $this->rotas_web->count())
            $script_web = $this->buildScript($this->rotas_web);
        $this->bar->advance();

        if($this->rotas_api && $this->rotas_api->count())
            $script_api = $this->buildScript($this->rotas_api);
        $this->bar->advance();



        $filesystem = new Filesystem();

        $file_web = base_path("routes".DIRECTORY_SEPARATOR.config("router.generated_files.web"));
        $file_api = base_path("routes".DIRECTORY_SEPARATOR.config("router.generated_files.api"));


        $filesystem->put($file_web, $script_web);
        $this->bar->advance();

        $filesystem->put($file_api, $script_api);
        $this->bar->advance();
        $this->bar->finish();

        //Todo: listar rotas geradas

        if($this->verbose) $this->info("Limpando CACHE de rotas");

        $this->line("");
        $this->call("route:clear");

        $this->info("Rotas Geradas com sucesso!");
    }

    public function buildScript($itens){

        //Carregar View Template
        $view_template = view('router::template', compact("itens"));

        $script = "<?php\n";
        $script .= "/**";
        $script .= "* Gerado automaticamente via Laravel Router";
        $script .= "*/\n\n";

        $script .= $view_template->render();

        return $script;
    }

    /**
     * @param Collection|SplFileInfo[] $controllers
     * @param string $path
     * @param bool   $api
     *
     * @return Collection
     * @throws \ReflectionException
     */
    public function getRoutes($controllers, $path = "", $api = false){

        //Todo: verbose

        $controllers_path = app_path("Http".DIRECTORY_SEPARATOR."Controllers");

        $path_final = $controllers_path . (($path) ? $path : "") . DIRECTORY_SEPARATOR;

        $retorno = collect();

        foreach($controllers as $dir => $controller){

            if(is_object($controller) && get_class($controller) == SplFileInfo::class){

                //Se for um controller

                //Exatrair nome da classe
                $controller_class = $this->parser->extractFileClass($controller->getPathname());
                $controller_namespace = $this->parser->extractFileNamespace($controller->getPathname());
                $classe = "{$controller_namespace}\\{$controller_class}";

                $controller_methods = get_class_methods($classe);
                $nome_rota = str_replace('App\Http\Controllers\\', "", $classe);

                //Pega os comentarios
                $class_phpdoc = (new ReflectionClass($classe))->getdoccomment();

                //Verifica se o controller deve ser gerado
                if($this->checkPhpDoc($class_phpdoc)) {

                    //Criar grupo do Controller
                    $group_add = new RouteGroupModel();
                    $group_add->title = $controller->getRelativePathname();
                    $class_phpdoc_params = $this->getPhpDocParams($class_phpdoc);

                    foreach($class_phpdoc_params as $param => $value){
                        $group_add->$param = $value;
                    }

                    //Definir parametros não definidos no phpDOC
                    if(!$group_add->prefix && !isset($class_phpdoc_params["prefix"])){
                        //Criar prefixo baseado no nome da Classe
                        $group_add->prefix = Str::lower($controller_class);
                        $group_add->prefix = str_replace("controller", "", $group_add->prefix);
                    }
                    if(!$group_add->as && !isset($class_phpdoc_params["as"])){
                        //Criar prefixo baseado no nome da Classe
                        $group_add->as = config("router.force_lowercase") ? Str::lower($controller_class) : $controller_class;
                        $group_add->as = str_replace("controller", "", $group_add->as) . ".";
                    }
                    if(!$group_add->middleware){
                        //Pegar Middleware padrão definida no config caso não seja definina no phpDOC
                        $group_add->middleware = config("router.defaults.middleware.".($api ? "api" : "web"));
                    }

                    //Remove metodos padroes
                    $controller_methods = array_diff($controller_methods, $this->ignore_methods->toArray());

                    //Passa metodo a metodo
                    foreach ($controller_methods as $method) {

                        //Pega os comentarios do metodo
                        $method_phpdoc = (new ReflectionClass($classe))->getMethod($method)->getdoccomment();

                        //Verifica se nao deve ser rejeitado
                        if($this->checkPhpDoc($method_phpdoc)) {

                            $route_add = new RouteModel();
                            $method_phpdoc_params = $this->getPhpDocParams($method_phpdoc);

                            foreach($method_phpdoc_params as $param => $value){
                                $route_add->$param = $value;
                            }

                            //Definir parametros não definidos no phpDOC
                            if(!$route_add->as){
                                $route_add->as = config("router.force_lowercase") ? Str::lower($method) : $method;
                            }
                            if(!$route_add->uses){
                                $route_add->uses = ($controller->getRelativePath() ? $controller->getRelativePath().DIRECTORY_SEPARATOR : "")."{$controller_class}@{$method}";
                            }
                            /*if(!$route_add->middleware){
                                //Pegar Middleware padrão definida no config caso não seja definina no phpDOC
                                $route_add->middleware = config("router.defaults.middleware.".($api ? "api" : "web"));
                            }*/

                            $method_url = $route_add->url ?? ($method == "index" ? "/" : $method);

                            //Parametros da URL
                            $method_reflection = new ReflectionMethod($classe, $method);
                            $method_reflection_params = $method_reflection->getParameters();

                            if($method_reflection_params && is_array($method_reflection_params)){

                                foreach($method_reflection_params as $param){

                                    $param_class = $param->getClass();
                                    if(!$param_class || !Str::contains($param_class, "Request")) {
                                        $method_url .= "/{".$param->name;
                                        $method_url .= ($param->isOptional()) ? "?}" : "}";
                                    }

                                }

                            }

                            $route_add->url = $method_url;



                            $group_add->itens->add($route_add);
                        }
                    }

                    $retorno->add($group_add);
                }


                //dd($path_final.$controller, $controller_class, $controller_namespace);

                $this->bar->advance();

            }else if(is_array($controller) || is_object($controller)){

                //Se for um diretorio

                //Criar grupo
                $group_add = new RouteGroupModel();
                $group_add->title = ($path ? $path.DIRECTORY_SEPARATOR : "").$dir;

                $path_collection = collect(explode("/",$path));


                $group_add->prefix = Str::lower($dir);
                $group_add->as = config("router.force_lowercase") ? Str::lower($dir.".") : $dir.".";
                $group_add->namespace = $dir;

                //Verifica se existem opções definidas no config.directories

                if(config("router.directories")->has($group_add->title)){
                    foreach(config("router.directories")->get($group_add->title) as $attribute => $value){
                        $group_add->$attribute = $value;
                    }
                }

                $group_add->itens = $this->getRoutes($controller, $path.DIRECTORY_SEPARATOR.$dir);

                $retorno->add($group_add);
            }
        }

        return $retorno;

    }

    public function getPhpDocParams($phpdoc){
        $retorno = [];
        //Padrao regex
        //$pattern = "#(@[a-zA-Z]+\s+[a-zA-Z0-9, ()_\.\/:]+)#";
        $pattern = "#(\@.+[^\n])#";

        //Separa
        preg_match_all($pattern, $phpdoc, $parametros_phpdoc, PREG_PATTERN_ORDER, 0);

        $parametros_phpdoc = $parametros_phpdoc[0];

        //Passa parametro a parametro
        foreach ($parametros_phpdoc as $parametro_phpdoc) {
            if (is_string($parametro_phpdoc)) {
                //Regex
                $rgx = '/\@\w+/im';

                //Valor do parametro
                $valor_param = preg_replace($rgx, '', $parametro_phpdoc);
                $valor_param = str_replace(" ", "", $valor_param);

                //Trata array
                if(Str::startsWith($valor_param, '[') && Str::endsWith($valor_param, ']')){
                    eval("\$valor_param = $valor_param;");
                }

                //Nome do parametro
                preg_match($rgx, $parametro_phpdoc, $nome_param);
                $nome_param = (isset($nome_param[0])) ? $nome_param[0] : null;
                $nome_param = strtolower(str_replace("@", "", $nome_param));

                if ($this->parameters->flatten()->contains($nome_param)) {

                    $config_param_name = $this->parameters->search(function(Collection $item) use($nome_param){
                        return $item->contains($nome_param);
                    });

                    $correct_param_name = $config_param_name ?? $nome_param;

                    //if($correct_param_name == "namespace") dump($valor_param);

                    $retorno[$correct_param_name] = $valor_param;
                }

            }

        }

        return $retorno;

    }

    public function getControllers($path = null, $api = false){

        $controllers_path = app_path("Http".DIRECTORY_SEPARATOR."Controllers");
        $path_final = $controllers_path . (($path) ? DIRECTORY_SEPARATOR.$path : "");

        $collection_name = $api ? "controllers_api" : "controllers_web";
        $count_name = "count_{$collection_name}";

        if($this->verbose)
            $this->info("Processando diretório: ".$path_final);

        //Pega apenas os arquivos no path atual
        $path_files = collect(File::files($path_final))->filter(function (SplFileInfo $item){
            return $item->getFilename() != "Controller.php";
        });

        $this->$count_name += $path_files->count();
        $this->count_controllers += $path_files->count();

        if($this->verbose)
            $this->line("Arquivos encontrados: ".$path_files->count());

        //Pega as pastas para entrar recursivamente
        $path_dirs = collect(File::directories($path_final))->map(function($item){
            return File::name($item);
        });

        if($this->verbose)
            $this->line("Sub-diretorios encontrados: ".$path_dirs->count());

        $retorno = $path_files ?? collect();

        //Recursividade
        foreach ($path_dirs as $dir)
        {
            if($dir != config("router.api.path"))
                $retorno->put($dir, $this->getControllers((($path) ? $path.DIRECTORY_SEPARATOR : "").$dir, $api));
        }

        return $retorno;
    }

    /**
     * @param $controller
     *
     * @return bool
     *
     * Verifica se o controller deve ser gerado
     */
    public function checkPhpDoc($phpdoc)
    {
        return !Str::contains($phpdoc, $this->reject_terms->map(function($item){
            return "@{$item}";
        })->toArray());
    }

    public function templateRoute(){
        return "//%action%\nRoute::match([%method%], '%action%', [
            %props%
        ]);\n\n";
    }

    public function templateGroup(){
        return "Route::group([%props%], function () {
        \n
                %content%
        });\n\n";
    }
}
