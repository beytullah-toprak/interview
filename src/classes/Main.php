<?php

require_once __DIR__ . '/Home.php';

class Main
{
    public $router;

    public function __construct()
    {
        global $lang, $smarty;

        $lang = $_SESSION['lang'] ?? 'tr';

        if (isset($_GET['lang'])) {
            $lang = $_GET['lang'];
            $_SESSION['lang'] = $lang;
        }

        require_once __DIR__ . "/../languages/{$lang}.php";

        $smarty = new Smarty\Smarty();
        $this->router = new \Bramus\Router\Router();

        $smarty->setTemplateDir('src/templates');
        $smarty->setCompileDir('/tmp');

        $smarty->assign('LANG', $lang);
        $smarty->assign('langs', ['tr' => 'Türkçe', 'en' => 'English']);
    }

    public function run()
    {
        global $smarty;

        $this->router->get('/', function () {
            $home = new Home();
            $home->index();
        });

        $this->router->run();
        $smarty->display('index.html');
    }
}
